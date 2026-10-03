<?php

namespace App\Results;

use App\Models\RaceClass;

final readonly class ClassResults
{
    /** @param  list<ResultRow>  $rows */
    public function __construct(
        public RaceClass $raceClass,
        public bool $timed,
        public array $rows,
    ) {}
}
