<?php

use App\Models\Event;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

function signedInWith(Event $event): User
{
    $user = User::factory()->create(['organizer_id' => $event->organizer_id]);
    test()->actingAs($user);

    return $user;
}

it('shows the results per class', function () {
    $event = Event::factory()->create();
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'Kvinnor 10 km']);
    Registration::factory()->for($class)->create(['bib' => 32, 'first_name' => 'Beatrice', 'last_name' => 'Lejnegård', 'status' => 'dnf']);
    signedInWith($event);

    $this->get(route('results.show', $event))
        ->assertOk()
        ->assertSee('Kvinnor 10 km')
        ->assertSee('Beatrice Lejnegård')
        ->assertSee('DNF');
});

it('changes the start time of several classes at once, in the event timezone', function () {
    $event = Event::factory()->create(['date' => '2026-10-03', 'timezone' => 'Europe/Stockholm']);
    $women = RaceClass::factory()->create(['event_id' => $event->id, 'start_at' => '2026-10-03 08:00:00']);
    $men = RaceClass::factory()->create(['event_id' => $event->id, 'start_at' => '2026-10-03 08:00:00']);
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'start_at' => '2026-10-03 07:30:00']);
    signedInWith($event);

    Volt::test('events.start', ['event' => $event])
        ->set('selected', [$women->id, $men->id])
        ->set('startTime', '10:03:30')
        ->call('changeStartTime')
        ->assertHasNoErrors();

    expect($women->fresh()->start_at->format('Y-m-d H:i:s'))->toBe('2026-10-03 08:03:30')
        ->and($men->fresh()->start_at->format('H:i:s'))->toBe('08:03:30')
        ->and($kids->fresh()->start_at->format('H:i:s'))->toBe('07:30:00');
});

it('only changes classes of the event', function () {
    $event = Event::factory()->create();
    $foreign = RaceClass::factory()->create(['start_at' => '2026-10-03 08:00:00']);
    signedInWith($event);

    Volt::test('events.start', ['event' => $event])
        ->set('selected', [$foreign->id])
        ->set('startTime', '10:03:30')
        ->call('changeStartTime');

    expect($foreign->fresh()->start_at->format('H:i:s'))->toBe('08:00:00');
});

it('requires a selected class and a valid time', function () {
    $event = Event::factory()->create();
    signedInWith($event);

    Volt::test('events.start', ['event' => $event])
        ->set('startTime', '25:99')
        ->call('changeStartTime')
        ->assertHasErrors(['selected', 'startTime']);
});

it('has every results translation in Swedish and English', function () {
    expect(array_keys(require lang_path('sv/results.php')))->toEqualCanonicalizing(array_keys(require lang_path('en/results.php')));
});

it('has every prizes translation in Swedish and English', function () {
    expect(array_keys(require lang_path('sv/prizes.php')))->toEqualCanonicalizing(array_keys(require lang_path('en/prizes.php')));
});
