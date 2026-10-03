<?php

namespace App\Timing;

use Carbon\CarbonImmutable;

final readonly class Entry
{
    public function __construct(
        public string $bib,
        public string $chip,
        public CarbonImmutable $startAt,
        public int $minTimeSeconds,
        public bool $dns = false,
    ) {}
}
