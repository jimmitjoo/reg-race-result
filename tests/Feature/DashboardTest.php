<?php

use App\Models\Organizer;
use App\Models\User;

it('sends the start page and dashboard to the events', function () {
    $this->get('/')->assertRedirect('/events');
    $this->get('/dashboard')->assertRedirect('/events');
});

it('asks guests to sign in for the events', function () {
    $this->get('/events')->assertRedirect(route('login'));
});

it('shows the events to signed in organizer users', function () {
    $user = User::factory()->create(['organizer_id' => Organizer::factory()->create()->id]);

    $this->actingAs($user)->get('/events')->assertOk();
});
