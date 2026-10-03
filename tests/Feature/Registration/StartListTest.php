<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\RaceClass;
use App\Models\Registration;
use Livewire\Volt\Volt;

function startListEvent(): RaceClass
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'sylvesterloppet-2026']);

    return RaceClass::factory()->create(['event_id' => $event->id, 'name' => 'Kvinnor 10 km']);
}

it('lists paid registrations per class with bib and club', function () {
    $class = startListEvent();
    Registration::factory()->for($class)->create(['bib' => 729, 'first_name' => 'Ida', 'last_name' => 'Nilsson', 'club' => 'Högby IF', 'paid_at' => now()]);
    Registration::factory()->for($class)->create(['first_name' => 'Obetald', 'paid_at' => null]);

    $this->get('/hogby-if/sylvesterloppet-2026/start-list')
        ->assertOk()
        ->assertSee('Kvinnor 10 km')
        ->assertSee('729')
        ->assertSee('Ida Nilsson')
        ->assertSee('Högby IF')
        ->assertDontSee('Obetald');
});

it('finds a runner by name or bib', function () {
    $class = startListEvent();
    Registration::factory()->for($class)->create(['bib' => 729, 'first_name' => 'Ida', 'last_name' => 'Nilsson', 'paid_at' => now()]);
    Registration::factory()->for($class)->create(['bib' => 493, 'first_name' => 'Erika', 'last_name' => 'Lindeblad', 'paid_at' => now()]);

    Volt::test('public.start-list', ['organizer' => 'hogby-if', 'event' => 'sylvesterloppet-2026'])
        ->set('search', 'linde')->assertSee('Erika Lindeblad')->assertDontSee('Ida Nilsson')
        ->set('search', '729')->assertSee('Ida Nilsson')->assertDontSee('Erika Lindeblad');
});

it('only finds the event under its own organizer', function () {
    startListEvent();
    Organizer::factory()->create(['slug' => 'studenterna']);

    $this->get('/studenterna/sylvesterloppet-2026/start-list')->assertNotFound();
});
