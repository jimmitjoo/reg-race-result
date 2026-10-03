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
            ->with('registrations')
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

        $rows = [];
        $placing = 0;
        $previous = null;
        foreach ($finishers as $index => [$registration, $result]) {
            $placing = $result->elapsedSeconds === $previous ? $placing : $index + 1;
            $previous = $result->elapsedSeconds;
            $rows[] = self::row($registration, $placing, self::time($result->elapsedSeconds));
        }

        $byBib = fn ($a, $b) => $a->bib <=> $b->bib;
        usort($out['dnf'], $byBib);
        usort($out['dq'], $byBib);

        return [...$rows, ...$out['dnf'], ...$out['dq']];
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

    private static function row(Registration $registration, ?int $placing, ?string $time): ResultRow
    {
        return new ResultRow(
            $placing,
            $registration->bib,
            trim("{$registration->first_name} {$registration->last_name}"),
            $registration->birth_date?->format('y'),
            $registration->club,
            $time,
            $registration->first_name,
            $registration->last_name,
            $registration->country,
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
