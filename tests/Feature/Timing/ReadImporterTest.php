<?php

use App\Models\ChipRead;
use App\Models\Event;
use App\Timing\ReadImporter;

it('stores reads in UTC, interpreting box time in the event timezone', function () {
    $event = Event::factory()->create(['timezone' => 'Europe/Stockholm']);

    ReadImporter::import($event, '241', "704\t2024-12-31 11:09:58.877\t1\t4\r\n");

    $read = ChipRead::sole();
    expect($read->chip)->toBe('704')
        ->and($read->reader)->toBe('241')
        ->and($read->unit)->toBe(1)
        ->and($read->antenna)->toBe(4)
        ->and($read->read_at->utc()->format('Y-m-d H:i:s.v'))->toBe('2024-12-31 10:09:58.877');
});

it('can import the same file again without duplicates', function () {
    $event = Event::factory()->create();
    $file = file_get_contents(base_path('tests/Fixtures/rfid/sylvesterloppet-2024-192.168.1.241.txt'));

    $first = ReadImporter::import($event, '241', $file);
    $second = ReadImporter::import($event, '241', $file);

    expect($first->inserted)->toBe(3634)
        ->and($second->inserted)->toBe(0)
        ->and($second->duplicates)->toBe(3634)
        ->and(ChipRead::count())->toBe(3634);
});

it('reports rejected lines', function () {
    $summary = ReadImporter::import(Event::factory()->create(), '240', "garbage\n");

    expect($summary->rejected)->toBe(['garbage']);
});
