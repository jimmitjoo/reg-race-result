<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Series;

it('shows the organizer\'s coming races and earlier years', function () {
    $this->travelTo('2026-11-07 12:00');
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'name' => 'Högby IF']);
    $series = Series::create(['organizer_id' => $organizer->id, 'name' => 'Sylvesterloppet']);
    $series->addEdition('2026-12-31', ['city' => 'Kalmar']);
    $series->addEdition('2025-12-31', ['results_public_at' => now()]);
    $series->addEdition('2024-12-31');
    Event::factory()->create(['organizer_id' => Organizer::factory()->create()->id, 'name' => 'Någon annans lopp 2026', 'date' => '2026-12-01']);

    $this->get('/hogby-if')
        ->assertOk()
        ->assertSee('Högby IF')
        ->assertSee('Sylvesterloppet 2026')->assertSee('torsdag 31 december 2026')->assertSee('/hogby-if/sylvesterloppet"', false)
        ->assertSee('/hogby-if/sylvesterloppet-2025/results', false)
        ->assertSee('/hogby-if/sylvesterloppet-2024/start-list', false)
        ->assertDontSee('Någon annans lopp');
});

it('says so when nothing is coming', function () {
    Organizer::factory()->create(['slug' => 'hogby-if']);

    $this->get('/hogby-if')->assertOk()->assertSee(__('public.no_upcoming'));
});

it('is not found for an unknown organizer', function () {
    $this->get('/finns-inte')->assertNotFound();
});
