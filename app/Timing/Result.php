<?php

namespace App\Timing;

use Carbon\CarbonImmutable;

final readonly class Result
{
    public function __construct(
        public string $bib,
        public ResultStatus $status,
        public ?CarbonImmutable $finishAt = null,
        public ?int $elapsedSeconds = null,
    ) {}
}
