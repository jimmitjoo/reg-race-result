<?php

use App\Exports\RegistrationsCsv;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

// #92: the age group of a race that counts them is shown before the race, not only in the results.
function ageGroupEvent(bool $ageGroups): RaceClass
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'olands-marathon-2026', 'date' => '2026-08-08']);
    $race = Race::factory()->for($event)->create(['name' => 'Ölands Marathon', 'age_groups' => $ageGroups]);
    $race->priceSteps()->create(['until' => '2026-08-01', 'amount' => 50000]);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'name' => 'Män', 'gender' => 'M']);
    Registration::factory()->for($class)->create(['first_name' => 'Per', 'last_name' => 'Ek', 'birth_date' => '1980-05-01', 'gender' => 'M']);
    Registration::factory()->for($class)->create(['first_name' => 'Ove', 'last_name' => 'Ask', 'birth_date' => '1981-12-31', 'gender' => 'M']);
    Registration::factory()->for($class)->create(['first_name' => 'Dold', 'last_name' => 'Löpare', 'birth_date' => '1960-01-01', 'gender' => 'M', 'hidden_at' => now()]);

    return $class;
}

it('shows the age group in the start list, but not for a hidden runner', function () {
    ageGroupEvent(true);

    $this->get('/hogby-if/olands-marathon-2026/start-list')->assertOk()->assertSee('M45')->assertDontSee('M65');
});

it('shows no age group where the race does not count them', function () {
    $class = ageGroupEvent(false);
    $this->actingAs(User::factory()->create(['organizer_id' => $class->event->organizer_id]));

    $this->get('/hogby-if/olands-marathon-2026/start-list')->assertOk()->assertDontSee('M45');
    Volt::test('events.registrations', ['event' => $class->event])->assertDontSee(__('results.age_group'))->assertDontSee(__('registrations.per_age_group'));
    expect(RegistrationsCsv::csv($class->event))->not->toContain(__('results.age_group'));
});

it('shows the age group and the count per group in the participant list and the export', function () {
    $class = ageGroupEvent(true);
    $this->actingAs(User::factory()->create(['organizer_id' => $class->event->organizer_id]));

    Volt::test('events.registrations', ['event' => $class->event])
        ->assertSee(__('results.age_group'))
        ->assertSee('M45: 2 · M65: 1');

    $csv = RegistrationsCsv::csv($class->event);
    expect($csv)->toContain(__('registration.gender').';'.__('results.age_group'))->toContain(';M;M45;');
});

it('tells the runner the age group in the registration form', function () {
    ageGroupEvent(true);
    $class = RaceClass::sole();

    Volt::test('public.event', ['organizer' => 'hogby-if', 'event' => 'olands-marathon-2026'])
        ->assertDontSee(__('registration.your_age_group', ['group' => 'M45']))
        ->set('raceClassId', (string) $class->id)->set('gender', 'M')->set('birthDate', '1980-05-01')
        ->assertSee(__('registration.your_age_group', ['group' => 'M45']));
});
