<?php

use App\Models\Event;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

function uncollectedEvent(array $bibs): array
{
    $event = Event::factory()->create();
    $class = RaceClass::factory()->create(['event_id' => $event->id]);
    foreach ($bibs as $bib => $status) {
        Registration::factory()->for($class)->create(['bib' => $bib, 'status' => $status]);
    }
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return [$event, $class];
}

it('lays out the bibs one ten per column, with empty slots and without empty tens', function () {
    [$event] = uncollectedEvent([563 => 'registered', 565 => 'registered', 571 => 'registered', 1002 => 'registered']);

    $columns = Volt::test('events.uncollected', ['event' => $event])->viewData('columns');

    expect(array_keys($columns))->toBe([560, 570, 1000])
        ->and($columns[560])->toBe([null, null, null, 563, null, 565, null, null, null, null])
        ->and($columns[570][1])->toBe(571)
        ->and($columns[1000][2])->toBe(1002);
});

it('starts with current DNS marked and saves the marked bibs as DNS', function () {
    [$event, $class] = uncollectedEvent([560 => 'registered', 561 => 'dns', 562 => 'registered', 563 => 'dnf']);

    Volt::test('events.uncollected', ['event' => $event])
        ->assertSet('marked', [561])
        ->set('marked', [560, 562])
        ->call('save');

    $status = fn ($bib) => Registration::where('bib', $bib)->value('status');
    expect($status(560))->toBe('dns')
        ->and($status(561))->toBe('registered')
        ->and($status(562))->toBe('dns')
        ->and($status(563))->toBe('dnf');
});

it('does not change DNF or DQ even if marked', function () {
    [$event] = uncollectedEvent([563 => 'dq']);

    Volt::test('events.uncollected', ['event' => $event])->set('marked', [563])->call('save');

    expect(Registration::where('bib', 563)->value('status'))->toBe('dq');
});
