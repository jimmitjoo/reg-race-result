<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Payments\StripeCheckout;
use App\Support\Money;
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
        ->set('participants.0.raceClassId', $class->id)
        ->set('participants.0.firstName', 'Anna')
        ->set('participants.0.lastName', 'Karlsson')
        ->set('participants.0.birthDate', '1991-04-03')
        ->set('participants.0.gender', 'K')
        ->set('participants.0.club', 'Högby IF')
        ->set('email', 'anna@example.com')
        ->set('phone', '070-123 45 67')
        ->set('acceptTerms', true);
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
        ->set('participants.0.firstName', ' Monica ')
        ->set('participants.0.club', 'Högby if ')
        ->call('register');

    expect(Registration::sole())->first_name->toBe('Monica')->club->toBe('Högby if');
});

it('validates the form', function () {
    openEvent();

    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->set('email', 'not-an-email')
        ->set('participants.0.birthDate', '1980-13-45')
        ->set('participants.0.gender', 'X')
        ->call('register')
        ->assertHasErrors(['participants.0.raceClassId', 'participants.0.firstName', 'participants.0.lastName', 'participants.0.birthDate', 'participants.0.gender', 'email']);
});

it('does not accept a class whose race is closed', function () {
    [, , $race, $class] = openEvent();
    $this->travelTo(now()->setDate(2026, 12, 31));

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->call('register')
        ->assertHasErrors(['participants.0.raceClassId']);
});

it('does not accept a class from another event', function () {
    [, , , $class] = openEvent();
    $foreign = RaceClass::factory()->create();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('participants.0.raceClassId', $foreign->id)
        ->call('register')
        ->assertHasErrors(['participants.0.raceClassId']);
});

it('requires turning 17 during the year for a half marathon or longer', function (string $birthDate, bool $ok) {
    [, $event, $race] = openEvent();
    $half = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Halvmaraton', 'distance_meters' => 21097]);

    $page = fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $half)
        ->set('participants.0.birthDate', $birthDate)
        ->call('register');

    $ok ? $page->assertHasNoErrors() : $page->assertHasErrors(['participants.0.birthDate']);
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
        ->set('participants.0.club', 'högby if ')
        ->call('register');

    expect(Registration::sole())->club->toBe('Högby IF')->club_id->toBe($club->id);
});

it('keeps a home town as free text', function () {
    [, , , $class] = openEvent();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('participants.0.club', 'Kalmar')
        ->call('register');

    expect(Registration::sole())->club->toBe('Kalmar')->club_id->toBeNull();
});

it('suggests the federation clubs in the form', function () {
    [, , , $class] = openEvent();
    Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF']);

    // The runner's details are asked for once a class is chosen.
    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->assertDontSeeHtml('<datalist id="clubs">')
        ->set('participants.0.raceClassId', (string) $class->id)
        ->assertSeeHtml('<datalist id="clubs">')->assertSeeHtml('<option value="Högby IF">');
});

it('does not let a man register in a women\'s class', function () {
    [, $event, $race, $class] = openEvent();
    $class->update(['gender' => 'K']);
    RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Män 10 km', 'gender' => 'M']);

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('participants.0.gender', 'M')
        ->call('register')
        ->assertHasErrors(['participants.0.raceClassId']);

    expect(Registration::count())->toBe(0);
});

it('registers a team with its name and members, the one who registers as contact (#31)', function () {
    [, , , $class] = openEvent();
    $class->update(['name' => 'Öppen klass', 'team_size' => 3]);
    $page = fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->assertSee(__('registration.team_member_n', ['n' => 3]))
        ->set('participants.0.teamName', ' Kyrkans Löpare ')
        ->set('participants.0.members', ['Anna Karlsson', 'Bo Ek', '']);

    $page->call('register')->assertHasErrors(['participants.0.members.2']);
    expect(Registration::count())->toBe(0);

    $page->set('participants.0.members.2', 'Cilla Ask')->call('register')->assertHasNoErrors();
    expect(Registration::sole())->team_name->toBe('Kyrkans Löpare')->team_members->toBe(['Anna Karlsson', 'Bo Ek', 'Cilla Ask'])
        ->first_name->toBe('Anna');
});

it('asks no team questions for a class where everyone registers on their own', function () {
    [, , , $class] = openEvent();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->assertDontSee(__('registration.team_name'))
        ->call('register')->assertHasNoErrors();
    expect(Registration::sole()->team_name)->toBeNull();
});

it('shows the price now and the next step, and the sum to pay for the chosen class (#118)', function () {
    [, , , $class] = openEvent();

    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->assertSee(__('registration.price_then', ['date' => '30 nov 2026', 'price' => Money::format(30000, 'SEK')]))
        ->assertSee(__('registration.choose_first'))
        ->set('participants.0.raceClassId', (string) $class->id)
        ->assertSee(__('registration.pay', ['price' => Money::format(25000, 'SEK')]));
});

it('takes a birth date written without dashes', function () {
    [, , , $class] = openEvent();

    fillForm(Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026']), $class)
        ->set('participants.0.birthDate', '19910403')
        ->call('register')
        ->assertHasNoErrors();

    expect(Registration::sole()->birth_date->toDateString())->toBe('1991-04-03');
});

it('keeps what was filled in when the page is loaded again, until the registration is paid', function () {
    [, , , $class] = openEvent();

    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->set('participants.0.raceClassId', (string) $class->id)->set('participants.0.firstName', 'Anna');

    $again = Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->assertSet('participants.0.raceClassId', (string) $class->id)->assertSet('participants.0.firstName', 'Anna');

    fillForm($again, $class)->call('register')->assertHasNoErrors();

    // Still there for a runner who left Stripe; gone once the confirmation shows it paid.
    expect(session('registration.'.$class->event_id.'.participants.0.firstName'))->toBe('Anna');
    Registration::sole()->update(['paid_at' => now()]);
    $this->get(Registration::sole()->confirmationUrl())->assertOk();
    expect(session()->has('registration.'.$class->event_id))->toBeFalse();
});

it('gives a paid runner the race for the calendar, behind a signed link', function () {
    [, , , $class] = openEvent();
    $registration = Registration::factory()->for($class)->create(['paid_at' => now()]);
    $url = URL::signedRoute('public.registration.calendar', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026', 'registration' => $registration]);

    $this->get($url)->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertSee('DTSTART:'.$class->start_at->utc()->format('Ymd\THis\Z'), false)
        ->assertSee('SUMMARY:Sylvesterloppet 2026 – Kvinnor 10 km', false);
    $this->get(str_replace('calendar?', 'calendar?x=1&', $url))->assertForbidden();
});
