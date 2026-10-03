<?php

namespace App\Timing;

final readonly class ImportSummary
{
    /** @param  list<string>  $rejected */
    public function __construct(
        public int $inserted,
        public int $duplicates,
        public array $rejected,
    ) {}
}
