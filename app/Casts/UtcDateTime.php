<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores any date time as UTC (optionally with milliseconds) and reads it back as UTC,
 * whatever timezone the value was given in. Display converts to the event timezone.
 */
class UtcDateTime implements CastsAttributes
{
    private string $format;

    public function __construct(string $precision = 'seconds')
    {
        $this->format = $precision === 'ms' ? 'Y-m-d H:i:s.v' : 'Y-m-d H:i:s';
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value);

        return $date->utc()->format($this->format);
    }
}
