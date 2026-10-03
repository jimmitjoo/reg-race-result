<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\RaceClass;
use App\Models\User;
use Livewire\Volt\Volt;

function organizerUser(?Organizer $organizer = null): User
{
    return User::factory()->create(['organizer_id' => ($organizer ?? Organizer::factory()->create())->id]);
}

it('lists only the events of the signed in organizer', function () {
    $user = organizerUser();
    Event::factory()->create(['organizer_id' => $user->organizer_id, 'name' => 'Ekerumsloppet 2026']);
    Event::factory()->create(['name' => 'Någon annans lopp']);

    $this->actingAs($user)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSee('Ekerumsloppet 2026')
        ->assertDontSee('Någon annans lopp');
});

it('creates an event for the organizer in its timezone', function () {
    $organizer = Organizer::factory()->create(['timezone' => 'Europe/Oslo']);
    $this->actingAs(organizerUser($organizer));

    Volt::test('events.index')
        ->set('name', 'Sylvesterloppet 2026')
        ->set('date', '2026-12-31')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.show', Event::sole()));

    expect(Event::sole())
        ->organizer_id->toBe($organizer->id)
        ->timezone->toBe('Europe/Oslo')
        ->date->toDateString()->toBe('2026-12-31');
});

it('requires a name and a date', function () {
    $this->actingAs(organizerUser());

    Volt::test('events.index')->call('create')->assertHasErrors(['name' => 'required', 'date' => 'required']);
});

it('hides other organizers events', function () {
    $event = Event::factory()->create();
    $this->actingAs(organizerUser());

    $this->get(route('events.show', $event))->assertNotFound();
    $this->get(route('timing.show', $event))->assertNotFound();
    $this->postJson(route('timing.reads.store', $event), ['reader' => '240', 'lines' => ''])->assertNotFound();
});

it('adds a class with its start time in the event timezone and the minimum time in minutes', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id, 'date' => '2026-10-03', 'timezone' => 'Europe/Stockholm']);
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])
        ->set('className', 'Kvinnor 10 km')
        ->set('distanceMeters', 10000)
        ->set('gender', 'K')
        ->set('timed', true)
        ->set('startTime', '10:03:30')
        ->set('minTimeMinutes', 12)
        ->call('saveClass')
        ->assertHasNoErrors();

    $class = RaceClass::sole();
    expect($class)
        ->name->toBe('Kvinnor 10 km')
        ->distance_meters->toBe(10000)
        ->gender->toBe('K')
        ->timed->toBeTrue()
        ->min_time_seconds->toBe(720)
        ->and($class->start_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-10-03 08:03:30');
});

it('edits a class', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id, 'date' => '2026-10-03', 'timezone' => 'Europe/Stockholm']);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'F11 1.3 km', 'timed' => true]);
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])
        ->call('editClass', $class->id)
        ->assertSet('className', 'F11 1.3 km')
        ->set('timed', false)
        ->set('startTime', '10:04:30')
        ->call('saveClass')
        ->assertHasNoErrors();

    expect($class->fresh())
        ->timed->toBeFalse()
        ->and($class->fresh()->start_at->utc()->format('H:i:s'))->toBe('08:04:30');
});

it('cannot edit a class of another event', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $foreign = RaceClass::factory()->create();
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])
        ->call('editClass', $foreign->id)
        ->assertNotFound();
});

it('shows classes with local start times and links to timing', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id, 'timezone' => 'Europe/Stockholm']);
    RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'Män 10 km', 'start_at' => '2026-10-03 08:03:30']);

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertSee('Män 10 km')
        ->assertSee('10:03:30')
        ->assertSee(route('timing.show', $event));
});

it('has every events translation in Swedish and English', function () {
    expect(array_keys(require lang_path('sv/events.php')))->toEqualCanonicalizing(array_keys(require lang_path('en/events.php')));
});

it('forbids users who do not belong to an organizer', function () {
    $this->actingAs(User::factory()->create(['organizer_id' => null]))
        ->get(route('events.index'))
        ->assertForbidden();
});
