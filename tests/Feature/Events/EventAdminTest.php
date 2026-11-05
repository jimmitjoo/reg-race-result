<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\User;
use App\Support\Money;
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
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'Män 10 km', 'start_at' => '2026-10-03 08:03:30']);
    $class->race->priceSteps()->create(['until' => '2026-10-01', 'amount' => 0]);

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertSee('Män 10 km')
        ->assertSee('10:03:30')
        ->assertSee(route('events.timing', $event));
});

it('sends users who do not belong to an organizer to the club step', function () {
    $this->actingAs(User::factory()->create(['organizer_id' => null]))
        ->get(route('events.index'))
        ->assertRedirect(route('club.create'));
});

it('creates a race with price steps and an on-site price in major units', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $this->actingAs($user);

    $page = Volt::test('events.show', ['event' => $event])
        ->set('raceName', 'Lilla Sylvesterloppet')
        ->call('saveRace')
        ->assertHasNoErrors();

    $race = Race::sole();
    expect($race)->name->toBe('Lilla Sylvesterloppet')->onsite_price->toBeNull();

    $page->set("onsiteAmount.{$race->id}", '150 kr')->call('saveOnsitePrice', $race->id)->assertHasNoErrors();
    expect($race->fresh()->onsite_price)->toBe(15000);
    $page->call('removeOnsitePrice', $race->id);
    expect($race->fresh()->onsite_price)->toBeNull();

    $page->set("priceUntil.{$race->id}", '2026-11-30')->set("priceAmount.{$race->id}", '99.50')->call('addPriceStep', $race->id)->assertHasNoErrors();
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
    RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id]);

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
        ->call('saveRace')
        ->assertHasNoErrors();

    expect($race->fresh())->type->toBe('Terräng')
        ->and(Race::count())->toBe(1);
});

it('only accepts SFIF race types', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $this->actingAs($user);

    Volt::test('events.show', ['event' => $event])->set('raceName', 'X')->set('raceType', 'Bana')->call('saveRace')->assertHasErrors(['raceType']);
});

it('accepts a price written with kr, and shows an error for a missing date', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $race = Race::factory()->for($event)->create();
    RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id]);
    $this->actingAs($user);

    $page = Volt::test('events.show', ['event' => $event])
        ->set("priceAmount.{$race->id}", '250 kr')
        ->call('addPriceStep', $race->id)
        ->assertHasErrors(["priceUntil.{$race->id}"])
        ->assertSee(__('validation.required', ['attribute' => __('events.price_until_label')]));
    expect($race->priceSteps()->count())->toBe(0);

    $page->set("priceUntil.{$race->id}", '2026-11-30')->call('addPriceStep', $race->id)->assertHasNoErrors();
    expect($race->priceSteps()->sole()->amount)->toBe(25000);
});

it('rejects a start time like 25:00 instead of failing', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $race = Race::factory()->for($event)->create();
    $this->actingAs($user);

    foreach (['25:00', '10:75'] as $time) {
        Volt::test('events.show', ['event' => $event])
            ->set('raceId', $race->id)
            ->set('className', 'X')
            ->set('startTime', $time)
            ->call('saveClass')
            ->assertHasErrors(['startTime']);
    }
});

it('shows the public link and whether registration is open or ended per race', function () {
    $this->travelTo('2026-11-15 12:00:00');
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $open = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet']);
    $open->priceSteps()->createMany([['until' => '2026-11-30', 'amount' => 25000], ['until' => '2026-12-30', 'amount' => 30000]]);
    $ended = Race::factory()->for($event)->create(['name' => 'Gamla loppet']);
    $ended->priceSteps()->create(['until' => '2026-11-01', 'amount' => 10000]);
    $event->races->each(fn ($race) => RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id]));

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertSee(route('public.event', ['organizer' => $event->organizer->slug, 'event' => $event->slug]))
        ->assertSee(__('events.registration_open', ['date' => '2026-12-30', 'price' => Money::format(25000, 'SEK')]))
        ->assertSee(__('events.registration_ended', ['date' => '2026-11-01']));
});

it('walks a new event through race, classes and price before showing the race day tools', function () {
    $user = organizerUser();
    $event = Event::factory()->create(['organizer_id' => $user->organizer_id]);
    $this->actingAs($user);

    $page = Volt::test('events.show', ['event' => $event])
        ->assertSee(__('events.setup_race_help'))
        ->assertDontSee(__('events.new_class'))
        ->assertDontSee(route('events.timing', $event));

    $page->set('raceName', '10 km')->call('saveRace')->set('raceName', 'Barnloppet')->call('saveRace');
    $first = Race::where('name', '10 km')->sole();
    $page->assertSee(__('events.setup_class_help'))
        ->assertDontSee(__('events.new_race'))
        ->assertSet('raceId', $first->id);

    $page->set('className', 'Kvinnor 10 km')->set('startTime', '10:00')->call('saveClass')->assertHasNoErrors()
        ->assertSee(__('events.new_class_in', ['race' => 'Barnloppet']))
        ->assertSet('raceId', Race::where('name', 'Barnloppet')->value('id'));

    $page->set('className', 'Barn')->set('startTime', '09:00')->call('saveClass')
        ->assertSee(__('events.setup_price_help'))
        ->assertSee(__('events.registration_closed'));

    // A race with only an on-site price is set up too.
    Race::where('name', 'Barnloppet')->update(['onsite_price' => 5000]);
    $page->set("priceUntil.{$first->id}", '2026-12-30')->set("priceAmount.{$first->id}", '0')->call('addPriceStep', $first->id)
        ->assertDontSee(__('events.setup_title'))
        ->assertSee(route('events.timing', $event))
        ->assertSee(__('events.new_race'));
});
