<?php

namespace App\Timing;

final readonly class ChipList
{
    /**
     * @param  list<string>|null  $header
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public ?array $header,
        public array $rows,
        public ?int $chipColumn,
        public ?int $bibColumn,
    ) {}
}
