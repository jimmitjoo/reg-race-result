<?php

use App\Exports\ResultsPdf;
use App\Exports\SfifExport;
use App\Models\Event;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use App\Results\ResultList;
use Livewire\Volt\Volt;

// #64: an abandoned Stripe Checkout leaves an unpaid row that must not count as a participant.
function unpaidEvent(): array
{
    $event = Event::factory()->create(['date' => '2026-12-31', 'timezone' => 'Europe/Stockholm']);
    $race = Race::factory()->for($event)->create();
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $race->id, 'start_at' => '2026-12-31 10:00:00']);
    $paid = Registration::factory()->for($class)->create(['bib' => null, 'first_name' => 'Anna', 'status' => 'dnf']);
    $unpaid = Registration::factory()->for($class)->unpaid()->create(['bib' => null, 'first_name' => 'Ulla', 'status' => 'dnf']);
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return [$event, $race, $paid, $unpaid];
}

it('gives bibs only to paid registrations', function () {
    [$event, $race, $paid, $unpaid] = unpaidEvent();

    Volt::test('events.chips', ['event' => $event])->set("firstBib.{$race->id}", 101)->call('assignBibs', $race->id);

    expect($paid->fresh()->bib)->toBe(101)->and($unpaid->fresh()->bib)->toBeNull();
});

it('skips bibs already held by an unpaid registration', function () {
    [$event, $race, $paid, $unpaid] = unpaidEvent();
    $unpaid->update(['bib' => 101]);

    Volt::test('events.chips', ['event' => $event])->set("firstBib.{$race->id}", 101)->call('assignBibs', $race->id);

    expect($paid->fresh()->bib)->toBe(102);
});

it('leaves unpaid registrations out of every participant list', function () {
    [$event, , $paid, $unpaid] = unpaidEvent();
    $paid->update(['bib' => 101, 'status' => 'registered']);
    $unpaid->update(['bib' => 102, 'status' => 'registered']);

    Volt::test('events.missing', ['event' => $event])->assertSee('Anna')->assertDontSee('Ulla');
    expect(collect(Volt::test('events.uncollected', ['event' => $event])->viewData('columns'))->flatten()->filter()->values()->all())->toBe([101]);

    $paid->update(['status' => 'dnf']);
    $unpaid->update(['status' => 'dnf']);
    expect(collect(ResultList::for($event))->flatMap(fn ($results) => $results->rows)->count())->toBe(1)
        ->and(substr_count(SfifExport::csv($event), 'DNF'))->toBe(1)
        ->and(ResultsPdf::html($event))->not->toContain('Ulla');
});

it('still shows unpaid registrations in the admin list', function () {
    [$event] = unpaidEvent();

    Volt::test('events.registrations', ['event' => $event])
        ->assertSee('Anna')
        ->assertSee('Ulla')
        ->assertSee(__('registrations.unpaid'));
});
