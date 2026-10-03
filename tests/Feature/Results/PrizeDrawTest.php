<?php

use App\Exports\ResultsPdf;
use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Event;
use App\Models\Prize;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

function drawEvent(): array
{
    $event = Event::factory()->create(['date' => '2026-10-03', 'timezone' => 'Europe/Stockholm']);
    $class = RaceClass::factory()->create(['event_id' => $event->id, 'start_at' => '2026-10-03 08:00:00', 'min_time_seconds' => 600]);
    test()->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    return [$event, $class];
}

function drawRunner(RaceClass $class, int $bib, bool $finished, array $attributes = []): Registration
{
    Chip::create(['event_id' => $class->event_id, 'code' => "c{$bib}", 'bib' => $bib]);
    if ($finished) {
        ChipRead::create(['event_id' => $class->event_id, 'reader' => '240', 'chip' => "c{$bib}", 'read_at' => '2026-10-03 08:40:00', 'unit' => 1, 'antenna' => 1]);
    }

    return Registration::factory()->for($class)->create(['bib' => $bib] + $attributes);
}

it('adds and removes prizes', function () {
    [$event] = drawEvent();
    $page = Volt::test('events.prizes', ['event' => $event])->set('prizeName', 'Solglasögon från Optiker Tottie')->call('addPrize')->assertHasNoErrors();

    expect(Prize::sole())->name->toBe('Solglasögon från Optiker Tottie')->event_id->toBe($event->id)->registration_id->toBeNull();

    $page->call('removePrize', Prize::sole()->id);
    expect(Prize::count())->toBe(0);
});

it('draws a winner among those who finished, not DNS, missing or earlier winners', function () {
    [$event, $class] = drawEvent();
    $winnerAlready = drawRunner($class, 1, true);
    drawRunner($class, 2, false);
    drawRunner($class, 3, true, ['status' => 'dns']);
    $only = drawRunner($class, 4, true);
    $event->prizes()->create(['name' => 'Cykel', 'registration_id' => $winnerAlready->id]);
    $prize = $event->prizes()->create(['name' => 'Presentkort']);

    Volt::test('events.prizes', ['event' => $event])->call('draw', $prize->id)->assertHasNoErrors();

    expect($prize->fresh()->registration_id)->toBe($only->id);
});

it('includes children who took part in untimed classes', function () {
    [$event] = drawEvent();
    $kids = RaceClass::factory()->create(['event_id' => $event->id, 'timed' => false]);
    $child = Registration::factory()->for($kids)->create(['bib' => 100]);
    $prize = $event->prizes()->create(['name' => 'Medalj']);

    Volt::test('events.prizes', ['event' => $event])->call('draw', $prize->id);

    expect($prize->fresh()->registration_id)->toBe($child->id);
});

it('tells when nobody can win', function () {
    [$event] = drawEvent();
    $prize = $event->prizes()->create(['name' => 'Cykel']);

    Volt::test('events.prizes', ['event' => $event])->call('draw', $prize->id)->assertHasErrors(['draw']);
});

it('cannot draw prizes of another event', function () {
    [$event] = drawEvent();
    $foreign = Prize::factory()->create();

    Volt::test('events.prizes', ['event' => $event])->call('draw', $foreign->id)->assertNotFound();
});

it('shows the drawn prizes on the result list', function () {
    [$event, $class] = drawEvent();
    $winner = drawRunner($class, 425, true, ['first_name' => 'Lars', 'last_name' => 'Lundberg', 'club' => 'Alvesta']);
    $event->prizes()->create(['name' => 'Övernattning på Best Western Kalmarsund', 'registration_id' => $winner->id]);
    $event->prizes()->create(['name' => 'Ej lottat än']);

    expect(ResultsPdf::html($event))
        ->toContain(__('pdf.prizes'))
        ->toContain('Övernattning på Best Western Kalmarsund')
        ->toContain('425')
        ->toContain('Lars Lundberg')
        ->toContain('Alvesta')
        ->not->toContain('Ej lottat än');
});
