<?php

namespace App\Timing;

final readonly class ParseResult
{
    /**
     * @param  list<Read>  $reads
     * @param  list<string>  $rejected
     */
    public function __construct(
        public array $reads,
        public array $rejected,
    ) {}
}
