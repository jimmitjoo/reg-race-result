<?php

use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\Series;
use App\Models\User;
use App\Payments\StripeCheckout;
use Livewire\Volt\Volt;

// #117: the way through an organizer's pages, and a cancelled or moved upplaga said first of all.
beforeEach(function () {
    $this->travelTo(now()->setTimezone('Europe/Stockholm')->setDate(2026, 11, 15)->setTime(12, 0));
});

function pagesEvent(): RaceClass
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'name' => 'Högby IF']);
    $series = Series::create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet']);
    $event = $series->addEdition('2026-12-31', ['city' => 'Kalmar']);
    $race = Race::factory()->for($event)->create(['name' => 'Sylvesterloppet']);
    $race->priceSteps()->create(['until' => '2026-12-30', 'amount' => 30000]);

    return RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km', 'start_at' => '2026-12-31 11:00:00']);
}

it('shows where a page sits, with the way back, on every public page of an upplaga', function () {
    $class = pagesEvent();
    $registration = Registration::factory()->for($class)->create();
    $crumbs = fn ($response) => $response->assertOk()->assertSeeInOrder(['Du är här', 'Högby IF', 'Sylvesterloppet', '2026'])
        ->assertSee('href="'.route('public.organizer', 'hogby-if').'"', false);

    $crumbs($this->get('/hogby-if/sylvesterloppet'));
    $crumbs($this->get('/hogby-if/sylvesterloppet-2026/start-list'))->assertSee('<span aria-current="page">Startlista</span>', false);
    $crumbs($this->get('/hogby-if/sylvesterloppet-2026/results'));
    $crumbs($this->get($registration->confirmationUrl()));
});

it('says first of all that an upplaga is cancelled, closes registration and tells schema.org', function () {
    $class = pagesEvent();
    $this->mock(StripeCheckout::class)->shouldNotReceive('create');
    $this->actingAs(User::factory()->create(['organizer_id' => $class->event->organizer_id]));
    Volt::test('events.show', ['event' => $class->event])->call('cancelEvent');

    $this->get('/hogby-if/sylvesterloppet')->assertSee(__('public.cancelled'))->assertSee('"eventStatus":"https://schema.org/EventCancelled"', false);
    $this->get('/hogby-if')->assertSee(__('public.cancelled'));

    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet'])
        ->set('participants.0.raceClassId', (string) $class->id)->set('participants.0.firstName', 'Anna')->set('participants.0.lastName', 'Karlsson')
        ->set('participants.0.birthDate', '1991-04-03')->set('participants.0.gender', 'K')->set('email', 'anna@example.com')->set('acceptTerms', true)
        ->call('register')->assertHasErrors(['participants.0.raceClassId']);
    expect(Registration::count())->toBe(0);

    Volt::test('events.show', ['event' => $class->event->fresh()])->call('reinstate');
    $this->get('/hogby-if/sylvesterloppet')->assertDontSee(__('public.cancelled'))->assertSee('EventScheduled');
});

it('moves an upplaga with its start times and says so on the page and to schema.org', function () {
    $class = pagesEvent();
    $this->actingAs(User::factory()->create(['organizer_id' => $class->event->organizer_id]));

    Volt::test('events.show', ['event' => $class->event])->set('newDate', '2027-01-15')->call('move')->assertHasNoErrors();

    $event = $class->event->fresh();
    expect($event->date->toDateString())->toBe('2027-01-15')->and($event->moved_from->toDateString())->toBe('2026-12-31')
        ->and($class->fresh()->start_at->setTimezone('Europe/Stockholm')->format('Y-m-d H:i'))->toBe('2027-01-15 12:00');
    $this->get('/hogby-if/sylvesterloppet')
        ->assertSee(__('public.moved', ['date' => 'fredag 15 januari 2027']))
        ->assertSee('"eventStatus":"https://schema.org/EventRescheduled"', false)
        ->assertSee('"previousStartDate":"2026-12-31"', false)
        ->assertSee('"startDate":"2027-01-15T12:00:00+01:00"', false)
        ->assertSee('"price":"300.00"', false);
});

it('closes registration once race day has passed, whatever the price steps say', function () {
    $class = pagesEvent();
    $race = $class->race;
    $race->priceSteps()->create(['until' => '2027-01-31', 'amount' => 40000]);

    // Before: open, and never said to be open after race day.
    expect($race->priceAt(now()))->toBe(30000)
        ->and($race->fresh()->closesOn()->toDateString())->toBe('2026-12-31')
        ->and($race->priceAt(now()->setDate(2026, 12, 31)))->toBe(40000);

    $this->travelTo(now()->setDate(2027, 1, 1));
    expect($race->fresh()->priceAt(now()))->toBeNull();
    $this->get('/hogby-if/sylvesterloppet-2026')->assertOk()->assertDontSee('400 kr');
    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->set('participants.0.raceClassId', (string) $class->id)->set('participants.0.firstName', 'Test')->set('participants.0.lastName', 'Testsson')
        ->set('participants.0.birthDate', '1990-01-01')->set('participants.0.gender', 'K')->set('email', 'test@example.com')->set('phone', '0701234567')->set('acceptTerms', true)
        ->call('register')->assertHasErrors('participants.0.raceClassId');
});
