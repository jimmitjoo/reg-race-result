<?php

namespace App\Timing;

use App\Models\ChipRead;
use App\Models\Event;
use App\Models\Registration;

final class EventResults
{
    public static function for(Event $event): Calculation
    {
        $chipsByBib = $event->chips()->pluck('code', 'bib');

        $entries = $event->registrations()
            ->with('raceClass')
            ->whereRelation('raceClass', 'timed', true)
            ->whereNotNull('bib')
            ->get()
            ->map(fn (Registration $registration) => new Entry(
                bib: (string) $registration->bib,
                chip: (string) ($chipsByBib[$registration->bib] ?? ''),
                startAt: $registration->raceClass->start_at,
                minTimeSeconds: $registration->raceClass->min_time_seconds,
                dns: $registration->status === 'dns',
            ))
            ->all();

        $reads = $event->chipReads()
            ->get()
            ->map(fn (ChipRead $read) => new Read($read->chip, $read->read_at, $read->reader, $read->unit, $read->antenna))
            ->all();

        return ResultCalculator::calculate($reads, $entries);
    }
}
