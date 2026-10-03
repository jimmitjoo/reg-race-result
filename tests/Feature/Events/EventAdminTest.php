<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
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
    $this->get(route('events.timing', $event))->assertNotFound();
    $this->postJson(route('events.timing.reads', $event), ['reader' => '240', 'lines' => ''])->assertNotFound();
});

it('adds a class with its start time in the event timezone and the minimum time in minutes', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id, 'date' => '2026-10-03', 'timezone' => 'Europe/Stockholm']);
    $this->actingAs($user);

    $race = Race::factory()->for($event)->create();

    Volt::test('events.show', ['event' => $event])
        ->set('raceId', $race->id)
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
        ->assertSee(route('events.timing', $event));
});

it('has every events translation in Swedish and English', function () {
    expect(array_keys(require lang_path('sv/events.php')))->toEqualCanonicalizing(array_keys(require lang_path('en/events.php')));
});

it('forbids users who do not belong to an organizer', function () {
    $this->actingAs(User::factory()->create(['organizer_id' => null]))
        ->get(route('events.index'))
        ->assertForbidden();
});

it('creates a race with price steps and an on-site price in major units', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $this->actingAs($user);

    $page = Volt::test('events.show', ['event' => $event])
        ->set('raceName', 'Lilla Sylvesterloppet')
        ->set('onsitePrice', '150')
        ->call('saveRace')
        ->assertHasNoErrors();

    $race = Race::sole();
    expect($race)->name->toBe('Lilla Sylvesterloppet')->onsite_price->toBe(15000);

    $page->call('addPriceStep', $race->id, '2026-11-30', '99.50')->assertHasNoErrors();
    expect($race->priceSteps()->sole())->amount->toBe(9950)->until->toDateString()->toBe('2026-11-30');

    $page->call('removePriceStep', $race->priceSteps()->sole()->id);
    expect($race->priceSteps()->count())->toBe(0);
});

it('puts a class in a race of the event', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $race = Race::factory()->for($event)->create();
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])
        ->set('raceId', $race->id)
        ->set('className', 'P11 1.3 km')
        ->set('startTime', '10:04:30')
        ->call('saveClass')
        ->assertHasNoErrors();

    expect(RaceClass::sole())->race_id->toBe($race->id)->event_id->toBe($event->id);
});

it('does not accept a race from another event', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])
        ->set('raceId', Race::factory()->create()->id)
        ->set('className', 'X')
        ->set('startTime', '10:00')
        ->call('saveClass')
        ->assertHasErrors(['raceId']);
});

it('shows races with their prices', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $race = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet', 'onsite_price' => 35000]);
    $race->priceSteps()->create(['until' => '2026-11-30', 'amount' => 25000]);

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertSee('Sylvesterloppet')
        ->assertSee('250')
        ->assertSee('350');
});

it('preselects the race when there is one', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $race = Race::factory()->for($event)->create();
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])->assertSet('raceId', $race->id);
});

it('stores the city of a new event', function () {
    $this->actingAs(organizerUser());

    Volt::test('events.index')->set('name', 'Sylvesterloppet 2026')->set('date', '2026-12-31')->set('city', 'Kalmar')->call('create');

    expect(Event::sole()->city)->toBe('Kalmar');
});

it('stores and edits the race type and course measurement', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $this->actingAs($user);

    $page = Volt::test('events.show', ['event' => $event])
        ->set('raceName', 'Sylvesterloppet')
        ->set('raceType', 'Väg')
        ->set('courseMeasurer', 'Carl-Gustaf Nilsson')
        ->set('measuredOn', '2024-10-11')
        ->set('ageGroups', true)
        ->call('saveRace')
        ->assertHasNoErrors();

    $race = Race::sole();
    expect($race)->age_groups->toBeTrue()->type->toBe('Väg')->course_measurer->toBe('Carl-Gustaf Nilsson')->measured_on->toDateString()->toBe('2024-10-11');

    $page->call('editRace', $race->id)
        ->assertSet('raceName', 'Sylvesterloppet')
        ->assertSet('ageGroups', true)
        ->set('raceType', 'Terräng')
        ->set('onsitePrice', '350')
        ->call('saveRace')
        ->assertHasNoErrors();

    expect($race->fresh())->type->toBe('Terräng')->onsite_price->toBe(35000)
        ->and(Race::count())->toBe(1);
});

it('only accepts SFIF race types', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])->set('raceName', 'X')->set('raceType', 'Bana')->call('saveRace')->assertHasErrors(['raceType']);
});
