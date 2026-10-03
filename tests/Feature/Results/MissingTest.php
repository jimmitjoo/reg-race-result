<?php

use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Event;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use App\Results\ResultList;
use Livewire\Volt\Volt;

function missingEvent(): array
{
    $event = Event::factory()->create(['date' => '2026-10-03', 'timezone' => 'Europe/Stockholm']);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'start_at' => '2026-10-03 08:00:00', 'min_time_seconds' => 600]);
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return [$event, $class];
}

it('lists timed runners without a finish, not finishers, DNS or untimed classes', function () {
    [$event, $class] = missingEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'c1', 'bib' => 1]);
    ChipRead::create(['event_id' => $event->id, 'reader' => '240', 'chip' => 'c1', 'read_at' => '2026-10-03 08:40:00', 'unit' => 1, 'antenna' => 1]);
    Registration::factory()->for($class)->create(['bib' => 1, 'first_name' => 'Finished']);
    Registration::factory()->for($class)->create(['bib' => 2, 'first_name' => 'Saknad']);
    Registration::factory()->for($class)->create(['bib' => 3, 'first_name' => 'Startadeinte', 'status' => 'dns']);
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'timed' => false]);
    Registration::factory()->for($kids)->create(['bib' => 100, 'first_name' => 'Barnet']);

    Volt::test('events.missing', ['event' => $event])
        ->assertSee('Saknad')
        ->assertDontSee('Finished')
        ->assertDontSee('Startadeinte');
});

it('sets DNF, DQ with an internal note, and can undo', function () {
    [$event, $class] = missingEvent();
    $registration = Registration::factory()->for($class)->create(['bib' => 2]);
    $page = Volt::test('events.missing', ['event' => $event]);

    $page->call('setStatus', $registration->id, 'dq', 'Sprang fel bana');
    expect($registration->fresh())->status->toBe('dq')->note->toBe('Sprang fel bana');

    $page->call('setStatus', $registration->id, 'registered');
    expect($registration->fresh()->status)->toBe('registered');

    $page->call('setStatus', $registration->id, 'bogus')->assertHasErrors();
    expect($registration->fresh()->status)->toBe('registered');
});

it('adds a manual finish time from the paper, in the event timezone', function () {
    [$event, $class] = missingEvent();
    $registration = Registration::factory()->for($class)->create(['bib' => 2]);

    Volt::test('events.missing', ['event' => $event])
        ->call('addTime', $registration->id, '10:41:07')
        ->assertHasNoErrors();

    expect($registration->fresh()->manual_finish_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-10-03 08:41:07')
        ->and(ResultList::for($event)[$class->id]->rows[0]->time)->toBe('41:07');
});

it('rejects an invalid manual time', function () {
    [$event, $class] = missingEvent();
    $registration = Registration::factory()->for($class)->create(['bib' => 2]);

    Volt::test('events.missing', ['event' => $event])
        ->call('addTime', $registration->id, '99:00')
        ->assertHasErrors();
});

it('strikes children in untimed classes', function () {
    [$event] = missingEvent();
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'timed' => false]);
    $child = Registration::factory()->for($kids)->create(['bib' => 100]);

    Volt::test('events.missing', ['event' => $event])->call('setStatus', $child->id, 'dns');

    expect($child->fresh()->status)->toBe('dns')
        ->and(ResultList::for($event)[$kids->id]->rows)->toBe([]);
});

it('cannot touch registrations of another event', function () {
    [$event] = missingEvent();
    $foreign = Registration::factory()->create();

    Volt::test('events.missing', ['event' => $event])
        ->call('setStatus', $foreign->id, 'dnf')
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('registered');
});
