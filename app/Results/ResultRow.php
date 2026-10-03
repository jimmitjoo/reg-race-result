<?php

namespace App\Results;

final readonly class ResultRow
{
    public function __construct(
        public ?int $placing,
        public ?int $bib,
        public string $name,
        public ?string $birthYear,
        public ?string $club,
        public ?string $time,
        public string $firstName = '',
        public string $lastName = '',
        public ?string $country = null,
    ) {}
}
