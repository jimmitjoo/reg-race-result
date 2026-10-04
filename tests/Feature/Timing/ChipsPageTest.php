<?php

use App\Models\Chip;
use App\Models\Event;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

function chipsEvent(): Event
{
    $event = Event::factory()->create();
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return $event;
}

it('imports a pasted chip list with the chosen columns', function () {
    $event = chipsEvent();

    Volt::test('events.chips', ['event' => $event])
        ->set('contents', "Startnummer;Chip\n501;E1\n502;E2\nabc;\n")
        ->call('preview')
        ->assertSet('chipColumn', 1)
        ->assertSet('bibColumn', 0)
        ->call('import')
        ->assertHasNoErrors();

    expect($event->chips()->orderBy('bib')->pluck('code', 'bib')->all())->toBe([501 => 'E1', 502 => 'E2']);
});

it('replaces old mappings for the same chip or bib', function () {
    $event = chipsEvent();
    Chip::create(['event_id' => $event->id, 'code' => 'E1', 'bib' => 700]);
    Chip::create(['event_id' => $event->id, 'code' => 'OLD', 'bib' => 502]);

    Volt::test('events.chips', ['event' => $event])
        ->set('contents', "501;E1\n502;E2\n")
        ->call('preview')
        ->set('bibColumn', 0)
        ->set('chipColumn', 1)
        ->call('import');

    expect($event->chips()->orderBy('bib')->pluck('code', 'bib')->all())->toBe([501 => 'E1', 502 => 'E2']);
});

it('requires two different columns', function () {
    $event = chipsEvent();

    Volt::test('events.chips', ['event' => $event])
        ->set('contents', "1;2\n")
        ->call('preview')
        ->set('bibColumn', 0)
        ->set('chipColumn', 0)
        ->call('import')
        ->assertHasErrors(['chipColumn']);
});

it('says all bibs have chips, or lists the ones missing, for timed classes only', function () {
    $event = chipsEvent();
    $class = RaceClass::factory()->create(['event_id' => $event->id]);
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'timed' => false]);
    Registration::factory()->for($class)->create(['bib' => 501]);
    Registration::factory()->for($class)->create(['bib' => 502]);
    Registration::factory()->for($kids)->create(['bib' => 1001]);
    Chip::create(['event_id' => $event->id, 'code' => 'E1', 'bib' => 501]);

    Volt::test('events.chips', ['event' => $event])->assertSee('502')->assertDontSee(__('chips.all_have_chips'))->assertDontSee('1001');

    Chip::create(['event_id' => $event->id, 'code' => 'E2', 'bib' => 502]);
    Volt::test('events.chips', ['event' => $event])->assertSee(__('chips.all_have_chips'));
});

it('assigns bibs in a series per race, in registration order, skipping numbers in use', function () {
    $event = chipsEvent();
    $race = Race::factory()->for($event)->create();
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id]);
    $other = RaceClass::factory()->create(['event_id' => $event->id]);
    $first = Registration::factory()->for($class)->create(['bib' => null, 'created_at' => now()->subDays(2)]);
    $second = Registration::factory()->for($class)->create(['bib' => null, 'created_at' => now()->subDay()]);
    $kept = Registration::factory()->for($class)->create(['bib' => 777]);
    Registration::factory()->for($other)->create(['bib' => 502]);

    Volt::test('events.chips', ['event' => $event])
        ->set("firstBib.{$race->id}", 501)
        ->call('assignBibs', $race->id)
        ->assertHasNoErrors();

    expect($first->fresh()->bib)->toBe(501)
        ->and($second->fresh()->bib)->toBe(503)
        ->and($kept->fresh()->bib)->toBe(777);
});
