<?php

namespace App\Support;

use App\Models\RaceClass;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Swedish formats, one place for all of them: "lördag 31 december 2026", "kl 10.00", "31 dec 2026". */
final class Format
{
    /** "lördag 31 december 2026" */
    public static function date(CarbonInterface $date): string
    {
        return $date->locale(app()->getLocale())->translatedFormat(__('format.date'));
    }

    /** "31 dec 2026" */
    public static function shortDate(CarbonInterface $date): string
    {
        // Carbon writes Swedish month abbreviations with a full stop ("dec."); Swedish lists drop it.
        return str_replace('.', '', $date->locale(app()->getLocale())->translatedFormat(__('format.short_date')));
    }

    /** "kl 10.00", or "kl 10.03.30" when the seconds matter (a start in waves). */
    public static function time(CarbonInterface $time): string
    {
        return __('format.time', ['time' => $time->format(__($time->second ? 'format.clock_seconds' : 'format.clock'))]);
    }

    /** "31 dec 2026 kl 10.00" */
    public static function dateTime(CarbonInterface $moment): string
    {
        return self::shortDate($moment).' '.self::time($moment);
    }

    /**
     * Classes in the organizer's order where it is set, else by start time, then by name the way people
     * read numbers: P5, P7, P9, P11, not P11 before P5.
     *
     * @param  iterable<RaceClass>  $classes
     * @return Collection<int, RaceClass>
     */
    public static function classOrder(iterable $classes): Collection
    {
        $name = fn (RaceClass $class) => preg_replace('/\s+/u', '', $class->name);

        return collect($classes)
            ->sort(fn (RaceClass $a, RaceClass $b) => ($a->position ?? PHP_INT_MAX) <=> ($b->position ?? PHP_INT_MAX)
                ?: $a->start_at->getTimestamp() <=> $b->start_at->getTimestamp() ?: strnatcasecmp($name($a), $name($b)))
            ->values();
    }
}
