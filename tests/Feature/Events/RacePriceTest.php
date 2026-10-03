<?php

use App\Models\Event;
use App\Models\Race;
use Carbon\CarbonImmutable;

function raceWithSteps(): Race
{
    $race = Race::factory()->for(Event::factory()->create(['timezone' => 'Europe/Stockholm']))->create(['onsite_price' => 35000]);
    $race->priceSteps()->createMany([
        ['until' => '2026-12-30', 'amount' => 30000],
        ['until' => '2026-11-30', 'amount' => 25000],
    ]);

    return $race;
}

it('uses the first price step that is still valid', function (string $at, ?int $expected) {
    expect(raceWithSteps()->priceAt(CarbonImmutable::parse($at, 'Europe/Stockholm')))->toBe($expected);
})->with([
    'early' => ['2026-10-01 12:00', 25000],
    'last minute of the first step, event time' => ['2026-11-30 23:59', 25000],
    'day after' => ['2026-12-01 00:00', 30000],
    'after the last step: online registration closed' => ['2026-12-31 08:00', null],
]);

it('reads the step date in the event timezone, not UTC', function () {
    // 23:30 UTC on 30 November is 00:30 on 1 December in Stockholm: the December price applies.
    expect(raceWithSteps()->priceAt(CarbonImmutable::parse('2026-11-30 23:30', 'UTC')))->toBe(30000);
});

it('has a separate on-site price', function () {
    expect(raceWithSteps()->onsite_price)->toBe(35000);
});
