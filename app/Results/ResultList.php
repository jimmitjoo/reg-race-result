<?php

namespace App\Results;

use App\Models\Event;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Timing\EventResults;
use App\Timing\Result;
use App\Timing\ResultStatus;

/**
 * The published result list per class: finishers placed by time (equal times share a placing),
 * then DNF and DQ. DNS and runners without a finish are left out. Untimed classes list everyone
 * who took part, without times.
 */
final class ResultList
{
    /** @return array<int, ClassResults> keyed by class id, in start order */
    public static function for(Event $event): array
    {
        $timing = EventResults::for($event)->results;

        return $event->raceClasses()
            ->with(['race', 'registrations.clubRecord'])
            ->orderBy('start_at')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (RaceClass $class) => [$class->id => new ClassResults(
                $class,
                $class->timed,
                $class->timed ? self::timedRows($class, $timing) : self::participants($class),
            )])
            ->all();
    }

    /** @param  array<string, Result>  $timing */
    private static function timedRows(RaceClass $class, array $timing): array
    {
        $finishers = [];
        $out = ['dnf' => [], 'dq' => []];

        foreach ($class->registrations as $registration) {
            if (isset($out[$registration->status])) {
                $out[$registration->status][] = self::row($registration, null, strtoupper($registration->status));
            } elseif ($registration->status !== 'dns' && ($result = $timing[(string) $registration->bib] ?? null)?->status === ResultStatus::Finished) {
                $finishers[] = [$registration, $result];
            }
        }

        usort($finishers, fn ($a, $b) => [$a[1]->elapsedSeconds, $a[1]->finishAt] <=> [$b[1]->elapsedSeconds, $b[1]->finishAt]);

        $race = $class->race;
        $year = $class->event->date->year;
        $groupOf = fn (Registration $r) => $r->birth_date ? AgeGroup::for($r->birth_date->year, $r->gender ?? $class->gender ?? '', $year) : null;
        $eligible = fn (Registration $r) => $race?->eligibleForChampionship($r->clubRecord) ?? false;

        $seconds = array_map(fn ($finisher) => $finisher[1]->elapsedSeconds, $finishers);
        $registrations = array_column($finishers, 0);
        $placings = self::placeWithin($registrations, $seconds, fn () => 'all');
        $agePlacings = $race?->age_groups ? self::placeWithin($registrations, $seconds, $groupOf) : [];
        $championship = $race?->championship ? self::placeWithin($registrations, $seconds, fn ($r) => $eligible($r) ? 'all' : null) : [];
        $veteranGroupOf = fn ($r) => $race?->championship_veterans && $eligible($r) && AgeGroup::isVeteran($groupOf($r)) ? $groupOf($r) : null;
        $veterans = self::placeWithin($registrations, $seconds, $veteranGroupOf);

        $rows = [];
        foreach ($finishers as $index => [$registration, $result]) {
            $rows[] = self::row(
                $registration,
                $placings[$index],
                self::time($result->elapsedSeconds),
                ageGroup: isset($agePlacings[$index]) ? $groupOf($registration) : null,
                ageGroupPlacing: $agePlacings[$index] ?? null,
                championshipPlacing: $championship[$index] ?? null,
                veteranGroup: isset($veterans[$index]) ? $veteranGroupOf($registration) : null,
                veteranPlacing: $veterans[$index] ?? null,
            );
        }

        $byBib = fn ($a, $b) => $a->bib <=> $b->bib;
        usort($out['dnf'], $byBib);
        usort($out['dq'], $byBib);

        return [...$rows, ...$out['dnf'], ...$out['dq']];
    }

    /**
     * Placing within groups (null group = not placed), equal times sharing a placing.
     *
     * @param  list<Registration>  $registrations  sorted by time
     * @param  list<int>  $seconds
     * @return array<int, int> placing by index
     */
    private static function placeWithin(array $registrations, array $seconds, callable $groupOf): array
    {
        $placings = [];
        $groups = [];
        foreach ($registrations as $index => $registration) {
            $group = $groupOf($registration);
            if ($group === null) {
                continue;
            }
            $state = $groups[$group] ?? ['count' => 0, 'placing' => 0, 'seconds' => null];
            $state['count']++;
            if ($seconds[$index] !== $state['seconds']) {
                $state = ['count' => $state['count'], 'placing' => $state['count'], 'seconds' => $seconds[$index]];
            }
            $groups[$group] = $state;
            $placings[$index] = $state['placing'];
        }

        return $placings;
    }

    private static function participants(RaceClass $class): array
    {
        return $class->registrations
            ->where('status', '!=', 'dns')
            ->sortBy('bib')
            ->map(fn (Registration $registration) => self::row($registration, null, null))
            ->values()
            ->all();
    }

    private static function row(
        Registration $registration,
        ?int $placing,
        ?string $time,
        ?string $ageGroup = null,
        ?int $ageGroupPlacing = null,
        ?int $championshipPlacing = null,
        ?string $veteranGroup = null,
        ?int $veteranPlacing = null,
    ): ResultRow {
        // A runner who asked not to be shown keeps the placing but loses the name (GDPR, #13).
        $hidden = $registration->hidden_at !== null;

        return new ResultRow(
            $placing,
            $registration->bib,
            $hidden ? __('privacy.anonymous') : trim("{$registration->first_name} {$registration->last_name}"),
            $hidden ? null : $registration->birth_date?->format('y'),
            $hidden ? null : $registration->club,
            $time,
            $hidden ? __('privacy.anonymous') : $registration->first_name,
            $hidden ? '' : $registration->last_name,
            $hidden ? null : $registration->country,
            $ageGroup,
            $ageGroupPlacing,
            $championshipPlacing,
            $veteranGroup,
            $veteranPlacing,
        );
    }

    /** 39:42 or 2:37:42, as on Swedish result lists and in the SFIF file. */
    public static function time(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }
}
