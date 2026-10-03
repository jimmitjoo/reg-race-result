<?php

namespace App\Support;

use Illuminate\Support\Number;

final class Money
{
    /** "99.50" or "99,50" in major units to minor units (9950). */
    public static function toMinor(string $major): int
    {
        return (int) round((float) str_replace([',', ' '], ['.', ''], $major) * 100);
    }

    public static function format(int $minor, string $currency): string
    {
        return Number::currency($minor / 100, in: $currency, locale: app()->getLocale());
    }
}
