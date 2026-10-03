<?php

use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Event;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Results\ResultList;
use App\Results\ResultRow;

function resultsEvent(): array
{
    $event = Event::factory()->create(['timezone' => 'Europe/Stockholm', 'date' => '2026-10-03']);
    $race = Race::factory()->for($event)->create();
    $women = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'start_at' => '2026-10-03 09:00:00', 'min_time_seconds' => 600]);

    return [$event, $women];
}

function runner(RaceClass $class, int $bib, ?string $finish, array $attributes = []): Registration
{
    Chip::create(['event_id' => $class->event_id, 'code' => "c{$bib}", 'bib' => $bib]);
    if ($finish) {
        ChipRead::create(['event_id' => $class->event_id, 'reader' => '240', 'chip' => "c{$bib}", 'read_at' => "2026-10-03 {$finish}", 'unit' => 1, 'antenna' => 1]);
    }

    return Registration::factory()->for($class)->create(['bib' => $bib, 'birth_date' => '1991-04-03', 'club' => 'Högby IF'] + $attributes);
}

it('places finishers by time with shared placings on equal times', function () {
    [$event, $class] = resultsEvent();
    runner($class, 32, '09:38:33.100', ['first_name' => 'Beatrice', 'last_name' => 'Lejnegård']);
    runner($class, 13, '09:38:47.000');
    runner($class, 26, '09:48:13.500');
    runner($class, 27, '09:48:13.900');

    $rows = ResultList::for($event)[$class->id]->rows;

    expect(array_map(fn (ResultRow $r) => [$r->placing, $r->bib, $r->time], $rows))->toBe([
        [1, 32, '38:34'],
        [2, 13, '38:47'],
        [3, 26, '48:14'],
        [3, 27, '48:14'],
    ])
        ->and($rows[0]->name)->toBe('Beatrice Lejnegård')
        ->and($rows[0]->birthYear)->toBe('91')
        ->and($rows[0]->club)->toBe('Högby IF');
});

it('formats times over an hour with hours', function () {
    [$event, $class] = resultsEvent();
    runner($class, 1, '11:37:42.000');

    expect(ResultList::for($event)[$class->id]->rows[0]->time)->toBe('2:37:42');
});

it('puts DNF and DQ last without placing, and leaves out DNS and runners without a finish', function () {
    [$event, $class] = resultsEvent();
    runner($class, 1, '09:40:00.000');
    runner($class, 2, '09:41:00.000', ['status' => 'dq', 'note' => 'Sprang fel bana']);
    runner($class, 3, null, ['status' => 'dnf']);
    runner($class, 4, null, ['status' => 'dns']);
    runner($class, 5, null);

    $rows = ResultList::for($event)[$class->id]->rows;

    expect(array_map(fn (ResultRow $r) => [$r->placing, $r->bib, $r->time], $rows))->toBe([
        [1, 1, '40:00'],
        [null, 3, 'DNF'],
        [null, 2, 'DQ'],
    ]);
});

it('lists everyone in untimed classes as participated, without times', function () {
    [$event] = resultsEvent();
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'F11 1.3 km', 'timed' => false]);
    Registration::factory()->for($kids)->create(['bib' => 102, 'first_name' => 'Ida', 'last_name' => 'Hasselbom']);
    Registration::factory()->for($kids)->create(['bib' => 103, 'status' => 'dns']);

    $list = ResultList::for($event)[$kids->id];

    expect($list->timed)->toBeFalse()
        ->and(array_map(fn (ResultRow $r) => [$r->placing, $r->bib, $r->time], $list->rows))->toBe([[null, 102, null]]);
});

it('follows a changed start time', function () {
    [$event, $class] = resultsEvent();
    runner($class, 1, '09:40:00.000');
    $class->update(['start_at' => '2026-10-03 09:03:30']);

    expect(ResultList::for($event)[$class->id]->rows[0]->time)->toBe('36:30');
});
