<?php

namespace App\Timing;

use Carbon\CarbonImmutable;

final class ResultCalculator
{
    /**
     * Reads closer together than this belong to the same passage over the mats.
     * A chip read on both sides of the minimum time is lying by the mats, since no runner can be there then.
     * Runners who stay on the mats after finishing are fine: their first read is the finish.
     */
    public const PASSAGE_GAP_SECONDS = 30;

    /**
     * @param  list<Read>  $reads  from all readers, in any order
     * @param  list<Entry>  $entries  timed entries only
     */
    public static function calculate(array $reads, array $entries): Calculation
    {
        $readsByChip = [];
        foreach ($reads as $read) {
            $readsByChip[$read->chip][] = $read;
        }
        foreach ($readsByChip as &$chipReads) {
            usort($chipReads, fn (Read $a, Read $b) => $a->readAt <=> $b->readAt);
        }
        unset($chipReads);

        $results = [];
        $knownChips = [];
        $continuous = [];
        foreach ($entries as $entry) {
            $knownChips[$entry->chip] = true;
            $earliest = $entry->startAt->addSeconds($entry->minTimeSeconds);
            $chipReads = $readsByChip[$entry->chip] ?? [];
            $raceReads = array_values(array_filter($chipReads, fn (Read $read) => $read->readAt >= $earliest));

            if (! $entry->dns && self::isReadAcross($chipReads, $earliest)) {
                $continuous[] = $entry->chip;
                $raceReads = [];
            }

            $results[$entry->bib] = self::result($entry, $raceReads);
        }
        sort($continuous);

        $unknown = array_values(array_diff(array_map('strval', array_keys($readsByChip)), array_keys($knownChips)));

        return new Calculation($results, $unknown, $continuous);
    }

    /** @param  list<Read>  $raceReads  sorted, after the minimum time; a manual finish time wins */
    private static function result(Entry $entry, array $raceReads): Result
    {
        if ($entry->dns) {
            return new Result($entry->bib, ResultStatus::Dns);
        }

        $finish = $entry->manualFinishAt ?? ($raceReads[0] ?? null)?->readAt;

        if ($finish === null) {
            return new Result($entry->bib, ResultStatus::Missing);
        }

        return new Result(
            $entry->bib,
            ResultStatus::Finished,
            $finish,
            (int) ceil($entry->startAt->diffInMilliseconds($finish) / 1000),
        );
    }

    /** @param  list<Read>  $chipReads */
    private static function isReadAcross(array $chipReads, CarbonImmutable $moment): bool
    {
        $before = $after = false;
        foreach ($chipReads as $read) {
            $seconds = $moment->diffInSeconds($read->readAt);
            $before = $before || ($seconds < 0 && $seconds >= -self::PASSAGE_GAP_SECONDS);
            $after = $after || ($seconds >= 0 && $seconds <= self::PASSAGE_GAP_SECONDS);
        }

        return $before && $after;
    }
}
