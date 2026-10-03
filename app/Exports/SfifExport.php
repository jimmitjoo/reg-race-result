<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Results\AgeGroup;
use App\Results\ResultList;
use App\Timing\EventResults;
use App\Timing\ResultStatus;

/**
 * Result file for Svenska Friidrottsförbundet (friidrottsstatistik.se), per the
 * "Specifikation resultatlistor för långlopp" (2024-10-24): one CSV for the whole event,
 * placing per distance and gender, DNS/DNF/DQ in the result column, emailed to resultat@friidrott.se.
 * Age groups only for races that award age group placings ("eventuella åldersklassplaceringar").
 */
final class SfifExport
{
    public const COLUMNS = [
        'type', 'race_name', 'city', 'date', 'organizer', 'distance', 'gender', 'course_measurer', 'date_of_measurement',
        'placing', 'agegroup', 'agegroupplacing', 'extra', 'firstname', 'lastname', 'country', 'club', 'birthdate', 'yb', 'result', 'netto',
    ];

    /** The federation's template for races that include an SM (Swedish championship), with SM and veteran SM placings. */
    public const SM_COLUMNS = [
        'type', 'race_name', 'city', 'date', 'organizer', 'distance', 'gender', 'course_measurer', 'date_of_measurement',
        'placing', 'smplacing', 'agegroup', 'agegroupplacing', 'vsmagegroupplacing', 'firstname', 'lastname', 'country', 'club', 'birthdate', 'yb', 'result', 'netto',
    ];

    /** Road distances that need a course measured by a federation measurer. */
    private const MEASURED_DISTANCES = [5000, 10000, 21097, 21100, 42195, 42200, 50000, 100000];

    public static function csv(Event $event): string
    {
        $columns = $event->races()->where('championship', 'SM')->exists() ? self::SM_COLUMNS : self::COLUMNS;
        $lines = [implode(';', $columns)];
        foreach (self::rows($event) as $row) {
            $lines[] = implode(';', array_map(fn ($column) => str_replace([';', "\r", "\n"], ' ', (string) ($row[$column] ?? '')), $columns));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    public static function filename(Event $event): string
    {
        $name = $event->races()->orderBy('id')->value('name') ?? $event->name;

        return $event->date->format('ymd')." {$event->city} {$name}.csv";
    }

    public static function distance(int $meters): string
    {
        return rtrim(rtrim(number_format(round($meters / 1000, 1), 1, '.', ''), '0'), '.').' km';
    }

    /** @return list<array<string, string>> */
    private static function rows(Event $event): array
    {
        $timing = EventResults::for($event)->results;
        $year = $event->date->year;
        $championship = [];
        foreach (ResultList::for($event) as $classResults) {
            foreach ($classResults->rows as $resultRow) {
                $championship[$resultRow->bib] = $resultRow;
            }
        }
        $groups = [];

        $classes = $event->raceClasses()->with(['race', 'registrations'])->where('timed', true)->get();
        foreach ($classes as $class) {
            foreach ($class->registrations as $registration) {
                $result = $timing[(string) $registration->bib] ?? null;
                $status = in_array($registration->status, ['dns', 'dnf', 'dq'], true) ? strtoupper($registration->status) : null;
                if (! $status && $result?->status !== ResultStatus::Finished) {
                    continue;
                }

                $gender = $registration->gender ?? $class->gender ?? '';
                $groups[$class->distance_meters.'|'.$gender][] = [
                    'class' => $class,
                    'registration' => $registration,
                    'gender' => $gender,
                    'status' => $status,
                    'seconds' => $status ? null : $result->elapsedSeconds,
                    'finishAt' => $status ? null : $result->finishAt,
                ];
            }
        }

        uksort($groups, function ($a, $b) {
            [$distanceA, $genderA] = explode('|', $a);
            [$distanceB, $genderB] = explode('|', $b);

            return [(int) $distanceA, $genderA] <=> [(int) $distanceB, $genderB];
        });

        $rows = [];
        foreach ($groups as $entries) {
            $statusOrder = ['DNF' => 1, 'DQ' => 2, 'DNS' => 3];
            usort($entries, fn ($a, $b) => [$statusOrder[$a['status'] ?? ''] ?? 0, $a['seconds'], $a['finishAt'], $a['registration']->bib]
                <=> [$statusOrder[$b['status'] ?? ''] ?? 0, $b['seconds'], $b['finishAt'], $b['registration']->bib]);

            $placings = self::placings(array_filter($entries, fn ($e) => ! $e['status']), fn ($e) => 'all');
            // The SM template has age groups for every veteran when the race has veteran SM placings.
            $ageGroupOf = fn ($e) => $e['class']->race?->age_groups || ($e['class']->race?->championship === 'SM' && $e['class']->race->championship_veterans)
                ? self::ageGroup($e['registration'], $e['gender'], $year)
                : null;
            $agePlacings = self::placings(array_filter($entries, fn ($e) => ! $e['status']), $ageGroupOf);

            foreach ($entries as $index => $entry) {
                $resultRow = $entry['status'] ? null : ($championship[$entry['registration']->bib] ?? null);
                $kind = $entry['class']->race?->championship;
                $rows[] = self::row($event, $entry, $placings[$index] ?? null, $agePlacings[$index] ?? null, $ageGroupOf($entry)) + [
                    'extra' => $kind === 'DM' && $resultRow?->championshipPlacing ? "DM {$resultRow->championshipPlacing}" : '',
                    'smplacing' => $kind === 'SM' ? $resultRow?->championshipPlacing : null,
                    'vsmagegroupplacing' => $kind === 'SM' ? $resultRow?->veteranPlacing : null,
                ];
            }
        }

        return $rows;
    }

    /** Placing per group key, equal times sharing a placing. Keys of the result follow the input. */
    private static function placings(array $finishers, callable $groupOf): array
    {
        $placings = [];
        $counters = [];
        foreach ($finishers as $index => $entry) {
            $group = $groupOf($entry);
            if ($group === null) {
                continue;
            }
            $counter = &$counters[$group];
            $counter ??= ['count' => 0, 'placing' => 0, 'seconds' => null];
            $counter['count']++;
            if ($entry['seconds'] !== $counter['seconds']) {
                $counter['placing'] = $counter['count'];
                $counter['seconds'] = $entry['seconds'];
            }
            $placings[$index] = $counter['placing'];
            unset($counter);
        }

        return $placings;
    }

    private static function ageGroup(Registration $registration, string $gender, int $year): ?string
    {
        return $registration->birth_date ? AgeGroup::for($registration->birth_date->year, $gender, $year) : null;
    }

    private static function row(Event $event, array $entry, ?int $placing, ?int $agePlacing, ?string $ageGroup): array
    {
        /** @var RaceClass $class */
        $class = $entry['class'];
        /** @var Registration $registration */
        $registration = $entry['registration'];
        $race = $class->race;
        $measured = $race?->type === 'Väg' && in_array($class->distance_meters, self::MEASURED_DISTANCES, true);

        return array_combine(array_diff(self::COLUMNS, ['extra']), [
            $race?->type ?? 'Väg',
            $race?->name ?? $event->name,
            $event->city,
            $event->date->toDateString(),
            $event->organizer->name,
            $class->distance_meters ? self::distance($class->distance_meters) : '',
            $entry['gender'],
            $measured ? ($race->course_measurer ?: 'Ej kontrollmätt') : '',
            $measured && $race->course_measurer && $race->measured_on ? $race->measured_on->format('ymd') : '',
            $placing,
            $entry['status'] ? '' : $ageGroup,
            $entry['status'] || ! $ageGroup ? '' : $agePlacing,
            $registration->first_name,
            $registration->last_name,
            $registration->country,
            $registration->club,
            $registration->birth_date?->toDateString(),
            '',
            $entry['status'] ?? ResultList::time($entry['seconds']),
            '',
        ]);
    }
}
