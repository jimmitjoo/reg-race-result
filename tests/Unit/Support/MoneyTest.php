<?php

use App\Support\Money;
use Tests\TestCase;

uses(TestCase::class);

it('converts major units to minor units', function (string $major, int $minor) {
    expect(Money::toMinor($major))->toBe($minor);
})->with([['250', 25000], ['99.50', 9950], ['99,50', 9950], ['1 200', 120000], ['0.1', 10]]);

it('formats money in the currency and locale', function () {
    app()->setLocale('sv');

    $nbsp = "\u{00A0}";

    expect(Money::format(25000, 'SEK'))->toBe("250,00{$nbsp}kr")
        ->and(Money::format(9950, 'EUR'))->toBe("99,50{$nbsp}€");
});
