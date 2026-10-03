<?php

namespace App\Timing;

final readonly class Calculation
{
    /**
     * @param  array<string, Result>  $results  keyed by bib
     * @param  list<string>  $unknownChips
     * @param  list<string>  $continuousChips
     */
    public function __construct(
        public array $results,
        public array $unknownChips,
        public array $continuousChips,
    ) {}
}
