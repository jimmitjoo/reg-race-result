<?php

use App\Models\Event;
use App\Models\Race;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

// #66: the organizer's list of registrations, and the CSV for Excel or a timer.
function participantEvent(): array
{
    $event = Event::factory()->create(['name' => 'Sylvesterloppet 2026', 'timezone' => 'Europe/Stockholm']);
    $long = Race::factory()->for($event)->create(['name' => '10 km']);
    $short = Race::factory()->for($event)->create(['name' => '5 km']);
    $women = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $long->id, 'name' => 'Kvinnor 10 km']);
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'race_id' => $short->id, 'name' => 'Barn 5 km']);
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return [$event, $long, $women, $kids];
}

it('shows paid registrations with contact details by default, without a cap', function () {
    [$event, , $women] = participantEvent();
    Registration::factory()->for($women)->create(['bib' => 1, 'first_name' => 'Åsa', 'email' => 'asa@example.com', 'phone' => '070-123', 'price' => 25000, 'payment_method' => 'stripe']);
    Registration::factory()->for($women)->unpaid()->create(['first_name' => 'Ulla']);
    Registration::factory()->for($women)->count(240)->sequence(fn ($s) => ['bib' => 1000 + $s->index])->create();

    Volt::test('events.registrations', ['event' => $event])
        ->assertSee('asa@example.com')->assertSee('070-123')->assertSee(__('registrations.method.stripe'))
        ->assertDontSee('Ulla')
        ->assertViewHas('registrations', fn ($page) => $page->total() === 241);
});

it('filters on started but unpaid registrations and on race, and counts per race and class', function () {
    [$event, $long, $women, $kids] = participantEvent();
    Registration::factory()->for($women)->create(['first_name' => 'Anna']);
    Registration::factory()->for($women)->unpaid()->create(['first_name' => 'Ulla']);
    Registration::factory()->for($kids)->create(['first_name' => 'Kalle']);

    $page = Volt::test('events.registrations', ['event' => $event]);
    $page->set('status', 'unpaid')->assertSee('Ulla')->assertDontSee('Anna')->assertSee(__('registrations.unpaid'));
    $page->set('status', 'all')->set('race', (string) $long->id)->assertSee('Anna')->assertSee('Ulla')->assertDontSee('Kalle');

    $counts = $page->viewData('counts');
    expect((int) $counts[$women->id]->total)->toBe(2)->and((int) $counts[$women->id]->paid)->toBe(1)
        ->and((int) $counts[$kids->id]->total)->toBe(1);
});

it('downloads the filtered rows as CSV for Swedish Excel', function () {
    [$event, , $women, $kids] = participantEvent();
    Registration::factory()->for($women)->create([
        'bib' => 101, 'first_name' => 'Åsa', 'last_name' => 'Öberg', 'birth_date' => '1990-04-03', 'gender' => 'K', 'club' => 'Högby IF',
        'email' => 'asa@example.com', 'phone' => '070-123', 'price' => 25000, 'payment_method' => 'stripe',
        'paid_at' => '2026-11-15 11:00:00', 'created_at' => '2026-11-15 10:58:00',
    ]);
    Registration::factory()->for($women)->unpaid()->create(['first_name' => 'Ulla']);
    Registration::factory()->for($kids)->create(['first_name' => 'Kalle']);

    $response = $this->get(route('events.exports.registrations', [$event, 'race' => $women->race_id]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('Anmälningar Sylvesterloppet 2026.csv');

    $csv = $response->getContent();
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");
    $lines = explode("\r\n", rtrim(substr($csv, 3), "\r\n"));
    expect($lines)->toHaveCount(2)
        ->and($lines[1])->toBe('Kvinnor 10 km;101;Åsa;Öberg;1990-04-03;K;Högby IF;asa@example.com;070-123;250,00;'.__('registrations.method.stripe').';2026-11-15 12:00;2026-11-15 11:58');
});

it('does not show or download another organizer\'s registrations', function () {
    [$event] = participantEvent();
    $other = Event::factory()->create();

    $this->get(route('events.registrations', $other))->assertNotFound();
    $this->get(route('events.exports.registrations', $other))->assertNotFound();
    expect($event->organizer_id)->not->toBe($other->organizer_id);
});
