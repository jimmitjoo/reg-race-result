<?php

use App\Models\Chip;
use App\Models\ChipRead;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\RaceClass;
use App\Models\Registration;
use App\Models\User;
use Livewire\Volt\Volt;

function publicResultsEvent(bool $public = true): array
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'nattloppet-2026', 'date' => '2026-08-21', 'results_public_at' => $public ? now() : null]);
    $women = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'Kvinnor 5 km', 'start_at' => '2026-08-21 20:00:00', 'min_time_seconds' => 600]);
    $men = RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'Män 5 km', 'start_at' => '2026-08-21 20:00:00', 'min_time_seconds' => 600]);
    foreach ([[$women, 367, 'Lova', 'Jönsson', '20:18:00'], [$men, 12, 'Erik', 'Svensson', '20:16:30']] as [$class, $bib, $first, $last, $finish]) {
        Chip::create(['event_id' => $event->id, 'code' => "c{$bib}", 'bib' => $bib]);
        ChipRead::create(['event_id' => $event->id, 'reader' => '240', 'chip' => "c{$bib}", 'read_at' => "2026-08-21 {$finish}", 'unit' => 1, 'antenna' => 1]);
        Registration::factory()->for($class)->create(['bib' => $bib, 'first_name' => $first, 'last_name' => $last]);
    }

    return [$event, $women, $men];
}

it('shows live results to anyone once the organizer made them public', function () {
    publicResultsEvent();

    $this->get('/hogby-if/nattloppet-2026/results')
        ->assertOk()
        ->assertSee('Lova Jönsson')->assertSee('18:00')
        ->assertSee('Erik Svensson')->assertSee('16:30')
        ->assertSee('wire:poll', false);
});

it('hides the results until they are made public', function () {
    publicResultsEvent(public: false);

    $this->get('/hogby-if/nattloppet-2026/results')->assertOk()->assertSee(__('live.not_public'))->assertDontSee('Lova Jönsson');
});

it('finds a runner by name or bib and filters by class', function () {
    [, $women] = publicResultsEvent();
    $page = Volt::test('public.results', ['organizer' => 'hogby-if', 'event' => 'nattloppet-2026']);

    $page->set('search', '367')->assertSee('Lova Jönsson')->assertDontSee('Erik Svensson');
    $page->set('search', 'svens')->assertSee('Erik Svensson')->assertDontSee('Lova Jönsson');
    $page->set('search', '')->set('classId', (string) $women->id)->assertSee('Lova Jönsson')->assertDontSee('Erik Svensson');
});

it('only finds the event under its own organizer', function () {
    publicResultsEvent();
    Organizer::factory()->create(['slug' => 'studenterna']);

    $this->get('/studenterna/nattloppet-2026/results')->assertNotFound();
});

it('lets the organizer make the results public and hide them again', function () {
    [$event] = publicResultsEvent(public: false);
    $this->actingAs(User::factory()->create(['organizer_id' => $event->organizer_id]));

    Volt::test('events.results', ['event' => $event])->call('togglePublic');
    expect($event->fresh()->results_public_at)->not->toBeNull();

    Volt::test('events.results', ['event' => $event->fresh()])->assertSee('/hogby-if/nattloppet-2026/results')->call('togglePublic');
    expect($event->fresh()->results_public_at)->toBeNull();
});
