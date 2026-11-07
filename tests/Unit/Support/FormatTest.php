<?php

use App\Models\RaceClass;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(fn () => app()->setLocale('sv'));

it('writes dates and times the Swedish way', function () {
    $start = CarbonImmutable::parse('2026-12-31 10:00:00', 'Europe/Stockholm');

    expect(Format::date($start))->toBe('torsdag 31 december 2026')
        ->and(Format::shortDate($start))->toBe('31 dec 2026')
        ->and(Format::time($start))->toBe('kl 10.00')
        ->and(Format::time($start->setTime(10, 3, 30)))->toBe('kl 10.03.30')
        ->and(Format::dateTime($start))->toBe('31 dec 2026 kl 10.00');
});

it('orders classes by start, then by name the way numbers are read', function () {
    $class = fn (string $name, string $start = '2026-12-31 11:00:00') => new RaceClass(['name' => $name, 'start_at' => $start]);
    $classes = [$class('P11 1 km'), $class('F9 1 km'), $class('P 13 2 km'), $class('P5/F5 400m'), $class('P9 1 km'), $class('10 km Män', '2026-12-31 10:00:00')];

    expect(Format::classOrder($classes)->pluck('name')->all())
        ->toBe(['10 km Män', 'F9 1 km', 'P5/F5 400m', 'P9 1 km', 'P11 1 km', 'P 13 2 km']);
});
