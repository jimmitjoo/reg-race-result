<?php

use App\Models\Chip;
use App\Models\Club;
use App\Models\Event;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

function onsiteEvent(): array
{
    $event = Event::factory()->create();
    $race = Race::factory()->for($event)->create(['onsite_price' => 35000]);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Kvinnor 10 km']);
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return [$event, $class];
}

function onsiteForm(Event $event, RaceClass $class, int $bib = 900)
{
    return Volt::test('events.onsite', ['event' => $event])
        ->set('raceClassId', (string) $class->id)
        ->set('bib', (string) $bib)
        ->set('firstName', 'Ida')
        ->set('lastName', 'Hasselbom')
        ->set('birthDate', '1990-02-20')
        ->set('gender', 'K')
        ->set('club', 'högby if')
        ->set('paidWithSwish', true);
}

it('registers on site with the on-site price, paid with Swish', function () {
    [$event, $class] = onsiteEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'E900', 'bib' => 900]);
    $club = Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF']);

    onsiteForm($event, $class)->call('register')->assertHasNoErrors()->assertSee('Ida Hasselbom');

    expect(Registration::sole())
        ->bib->toBe(900)
        ->price->toBe(35000)
        ->payment_method->toBe('swish_onsite')
        ->club->toBe('Högby IF')
        ->club_id->toBe($club->id)
        ->paid_at->not->toBeNull();
});

it('requires the Swish payment to be confirmed', function () {
    [$event, $class] = onsiteEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'E900', 'bib' => 900]);

    onsiteForm($event, $class)->set('paidWithSwish', false)->call('register')->assertHasErrors(['paidWithSwish']);
});

it('refuses a bib that is already used or has no chip in a timed class', function () {
    [$event, $class] = onsiteEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'E900', 'bib' => 900]);
    Registration::factory()->for($class)->create(['bib' => 900]);

    onsiteForm($event, $class, 900)->call('register')->assertHasErrors(['bib']);
    onsiteForm($event, $class, 901)->call('register')->assertHasErrors(['bib']);
});

it('accepts a bib without chip in an untimed class', function () {
    [$event] = onsiteEvent();
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'timed' => false]);

    onsiteForm($event, $kids, 1004)->call('register')->assertHasNoErrors();

    expect(Registration::sole()->bib)->toBe(1004);
});

it('changes the number of an on-site registration', function () {
    [$event, $class] = onsiteEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'E900', 'bib' => 900]);
    Chip::create(['event_id' => $event->id, 'code' => 'E901', 'bib' => 901]);
    onsiteForm($event, $class)->call('register');

    Volt::test('events.onsite', ['event' => $event])
        ->call('changeBib', Registration::sole()->id, '901')
        ->assertHasNoErrors();

    expect(Registration::sole()->bib)->toBe(901);
});
