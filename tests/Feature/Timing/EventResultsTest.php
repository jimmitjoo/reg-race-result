<?php

use App\Models\Chip;
use App\Models\Event;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Timing\EventResults;
use App\Timing\ReadImporter;
use App\Timing\ResultStatus;
use Carbon\CarbonImmutable;

function nattloppet2024(): RaceClass
{
    $event = Event::factory()->create(['name' => 'Hagbloms Nattloppet 2024', 'timezone' => 'Europe/Stockholm']);
    ReadImporter::import($event, '241', file_get_contents(base_path('tests/Fixtures/rfid/hagbloms-nattloppet-2024-192.168.1.241.txt')));

    return RaceClass::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kvinnor 4.7 km',
        'start_at' => CarbonImmutable::parse('2024-08-23 22:06:00', 'Europe/Stockholm'),
        'min_time_seconds' => 720,
    ]);
}

function register(RaceClass $class, int $bib, ?string $chip = null, string $status = 'registered'): Registration
{
    Chip::create(['event_id' => $class->event_id, 'code' => $chip ?? (string) $bib, 'bib' => $bib]);

    return Registration::factory()->for($class)->create(['bib' => $bib, 'status' => $status]);
}

it('calculates results for an event from stored reads and chips', function () {
    $class = nattloppet2024();
    foreach ([367, 57, 307] as $bib) {
        register($class, $bib);
    }

    $results = EventResults::for($class->event)->results;

    expect($results['367']->elapsedSeconds)->toBe(18 * 60)
        ->and($results['57']->elapsedSeconds)->toBe(18 * 60 + 15)
        ->and($results['307']->elapsedSeconds)->toBe(18 * 60 + 21);
});

it('uses the chip mapped to the bib', function () {
    $class = nattloppet2024();
    register($class, 1001, chip: '367');

    expect(EventResults::for($class->event)->results['1001']->elapsedSeconds)->toBe(18 * 60);
});

it('treats DNS registrations as DNS', function () {
    $class = nattloppet2024();
    register($class, 367, status: 'dns');

    expect(EventResults::for($class->event)->results['367']->status)->toBe(ResultStatus::Dns);
});

it('leaves untimed classes out', function () {
    $class = nattloppet2024();
    $kids = RaceClass::factory()->create(['event_id' => $class->event_id, 'timed' => false]);
    Registration::factory()->for($kids)->create(['bib' => 2000]);

    expect(EventResults::for($class->event)->results)->not->toHaveKey('2000');
});

it('marks a registration without a chip as missing', function () {
    $class = nattloppet2024();
    Registration::factory()->for($class)->create(['bib' => 555]);

    expect(EventResults::for($class->event)->results['555']->status)->toBe(ResultStatus::Missing);
});
