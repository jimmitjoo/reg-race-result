<?php

namespace App\Timing;

use Carbon\CarbonImmutable;

final readonly class Read
{
    public function __construct(
        public string $chip,
        public CarbonImmutable $readAt,
        public string $reader,
        public int $unit,
        public int $antenna,
    ) {}
}
