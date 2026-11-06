<?php

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

it('creates an organizer whose user can choose a password and create an event', function () {
    Notification::fake();

    $this->artisan('organizer:create', ['name' => 'Kalmar Löparklubb', 'email' => 'Anna@Example.com'])->assertSuccessful();

    $organizer = Organizer::sole();
    $user = User::sole();
    expect($organizer)->slug->toBe('kalmar-loparklubb')->country->toBe('SE')->timezone->toBe('Europe/Stockholm')->currency->toBe('SEK')->locale->toBe('sv')
        ->and($user)->email->toBe('anna@example.com')->organizer_id->toBe($organizer->id);
    Notification::assertSentTo($user, ResetPassword::class);

    $this->actingAs($user)->get(route('events.index'))->assertOk();
    Volt::test('events.index')->set('name', 'Kalmarmilen')->set('date', '2027-05-01')->call('create')->assertHasNoErrors();
    expect($organizer->events()->sole()->name)->toBe('Kalmarmilen 2027');
});

it('connects an existing user without an organizer, but not one that has one', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'anna@example.com', 'organizer_id' => null]);

    $this->artisan('organizer:create', ['name' => 'Kalmar LK', 'email' => 'anna@example.com'])->assertSuccessful();
    expect($user->fresh()->organizer_id)->toBe(Organizer::sole()->id);

    $this->artisan('organizer:create', ['name' => 'Ett till', 'email' => 'anna@example.com'])->assertFailed();
    expect(Organizer::count())->toBe(1);
});

it('gives a club named like a path of the app a slug that does not collide', function () {
    Notification::fake();

    $this->artisan('organizer:create', ['name' => 'Events', 'email' => 'a@example.com']);
    $this->artisan('organizer:create', ['name' => 'Settings', 'email' => 'b@example.com']);
    $this->artisan('organizer:create', ['name' => 'Settings', 'email' => 'c@example.com']);

    expect(Organizer::orderBy('id')->pluck('slug')->all())->toBe(['events-2', 'settings-2', 'settings-3']);
});
