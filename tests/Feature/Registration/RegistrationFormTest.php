<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->travelTo(now()->setTimezone('Europe/Stockholm')->setDate(2026, 11, 15)->setTime(12, 0));
    $this->mock(StripeCheckout::class)
        ->shouldReceive('create')
        ->andReturn(['id' => 'cs_test', 'url' => 'https://checkout.stripe.com/test']);
});

function openEvent(): array
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet 2026', 'slug' => 'sylvesterloppet-2026', 'date' => '2026-12-31']);
    $race = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet']);
    $race->priceSteps()->createMany([['until' => '2026-11-30', 'amount' => 25000], ['until' => '2026-12-30', 'amount' => 30000]]);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'distance_meters' => 10000]);

    return [$organizer, $event, $race, $class];
}

function fillForm($page, RaceClass $class)
{
    return $page
        ->set('raceClassId', $class->id)
        ->set('firstName', 'Anna')
        ->set('lastName', 'Karlsson')
        ->set('birthDate', '1991-04-03')
        ->set('gender', 'K')
        ->set('club', 'Högby IF')
        ->set('email', 'anna@example.com')
        ->set('phone', '070-123 45 67');
}

it('shows the event with its open classes and current prices to anyone', function () {
    [, , , $class] = openEvent();

    $this->get('/hogby-if/sylvesterloppet-2026')
        ->assertOk()
        ->assertSee('Sylvesterloppet 2026')
        ->assertSee('Kvinnor 10 km')
        ->assertSee('250')
        ->assertDontSee('ersonnummer');
});

it('only finds an event under its own organizer', function () {
    openEvent();
    $other = Organizer::factory()->create(['slug' => 'studenterna']);

    $this->get('/studenterna/sylvesterloppet-2026')->assertNotFound();
    $this->get('/hogby-if/finns-inte')->assertNotFound();
});

it('registers a runner with the price valid today and sends them to payment', function () {
    [, $event, , $class] = openEvent();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.stripe.com/test');

    expect(Registration::sole())
        ->race_class_id->toBe($class->id)
        ->first_name->toBe('Anna')
        ->last_name->toBe('Karlsson')
        ->birth_date->toDateString()->toBe('1991-04-03')
        ->gender->toBe('K')
        ->club->toBe('Högby IF')
        ->email->toBe('anna@example.com')
        ->price->toBe(25000)
        ->paid_at->toBeNull();
});

it('trims names and club', function () {
    [, , , $class] = openEvent();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('firstName', ' Monica ')
        ->set('club', 'Högby if ')
        ->call('register');

    expect(Registration::sole())->first_name->toBe('Monica')->club->toBe('Högby if');
});

it('validates the form', function () {
    openEvent();

    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->set('email', 'not-an-email')
        ->set('birthDate', '19800221')
        ->set('gender', 'X')
        ->call('register')
        ->assertHasErrors(['raceClassId', 'firstName', 'lastName', 'birthDate', 'gender', 'email']);
});

it('does not accept a class whose race is closed', function () {
    [, , $race, $class] = openEvent();
    $this->travelTo(now()->setDate(2026, 12, 31));

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->call('register')
        ->assertHasErrors(['raceClassId']);
});

it('does not accept a class from another event', function () {
    [, , , $class] = openEvent();
    $foreign = RaceClass::factory()->create();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('raceClassId', $foreign->id)
        ->call('register')
        ->assertHasErrors(['raceClassId']);
});

it('requires turning 17 during the year for a half marathon or longer', function (string $birthDate, bool $ok) {
    [, $event, $race] = openEvent();
    $half = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Halvmaraton', 'distance_meters' => 21097]);

    $page = fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $half)
        ->set('birthDate', $birthDate)
        ->call('register');

    $ok ? $page->assertHasNoErrors() : $page->assertHasErrors(['birthDate']);
})->with([
    'turns 17 in December' => ['2009-12-31', true],
    'turns 16' => ['2010-01-01', false],
]);

it('shows the confirmation only with a valid signature', function () {
    [, $event, , $class] = openEvent();
    $registration = Registration::factory()->for($class)->create(['first_name' => 'Anna', 'last_name' => 'Karlsson']);

    $this->get(URL::signedRoute('public.registration', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026', 'registration' => $registration]))
        ->assertOk()
        ->assertSee('Anna Karlsson')
        ->assertSee('Kvinnor 10 km');

    $this->get("/hogby-if/sylvesterloppet-2026/registrations/{$registration->id}")->assertForbidden();
});

it('creates event slugs from the name, unique per organizer', function () {
    $organizer = Organizer::factory()->create();

    $first = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Ölands Tjurrus 2027', 'slug' => null]);
    $second = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Ölands Tjurrus 2027', 'slug' => null]);

    expect($first->slug)->toBe('olands-tjurrus-2027')->and($second->slug)->toBe('olands-tjurrus-2027-2');
});

it('stores the official club name and link when the typed club is in the federation list', function () {
    [, , , $class] = openEvent();
    $club = Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF', 'district' => 'Småland']);

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('club', 'högby if ')
        ->call('register');

    expect(Registration::sole())->club->toBe('Högby IF')->club_id->toBe($club->id);
});

it('keeps a home town as free text', function () {
    [, , , $class] = openEvent();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('club', 'Kalmar')
        ->call('register');

    expect(Registration::sole())->club->toBe('Kalmar')->club_id->toBeNull();
});

it('suggests the federation clubs in the form', function () {
    openEvent();
    Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF']);

    $this->get('/hogby-if/sylvesterloppet-2026')->assertSee('<datalist id="clubs">', false)->assertSee('<option value="Högby IF">', false);
});
