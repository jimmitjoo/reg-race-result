<?php

use App\Results\AgeGroup;

it('derives the age group from the birth year, as Swedish athletics does', function (int $born, string $gender, ?string $expected) {
    expect(AgeGroup::for($born, $gender, 2026))->toBe($expected);
})->with([
    [1991, 'K', 'K35'], [1984, 'K', 'K40'], [1950, 'M', 'M75'], [1995, 'M', null],
    [2004, 'M', 'M22'], [2007, 'K', 'F19'], [2008, 'M', 'P19'], [2010, 'K', 'F16'], [2014, 'M', 'P12'], [2015, 'K', null],
]);
