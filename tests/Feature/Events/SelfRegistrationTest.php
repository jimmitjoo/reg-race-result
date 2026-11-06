<?php

use App\Models\Club;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

// #35 1b: a new user registers, names the club and creates an event without help.
it('lets a new user register, name the club and create an event', function () {
    Mail::fake();
    Club::create(['federation' => 'SFIF', 'external_id' => '506', 'name' => 'Högby IF']);

    Volt::test('auth.register')
        ->set('name', 'Anna Karlsson')->set('email', 'anna@example.com')
        ->set('password', 'password')->set('password_confirmation', 'password')
        ->call('register');
    $user = User::sole();

    $this->get(route('events.index'))->assertRedirect(route('club.create'));
    $this->get(route('club.create'))->assertOk()->assertSee('<option value="Högby IF">', false);

    Volt::test('club.create')->set('name', ' Kalmar Löparklubb ')->call('create')->assertRedirect(route('events.index'));

    $organizer = Organizer::sole();
    expect($organizer)->name->toBe('Kalmar Löparklubb')->slug->toBe('kalmar-loparklubb')->currency->toBe('SEK')
        ->and($user->fresh()->organizer_id)->toBe($organizer->id);

    Volt::test('events.index')->set('name', 'Kalmarmilen')->set('date', '2027-05-01')->call('create')->assertHasNoErrors();
    expect($organizer->events()->sole()->name)->toBe('Kalmarmilen 2027');
});

it('tells the platform when a club signs up', function () {
    $sent = [];
    Mail::shouldReceive('raw')->once()->andReturnUsing(function (string $text, Closure $callback) use (&$sent) {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('to')->andReturnUsing(function ($to) use ($message, &$sent) { $sent['to'] = $to; return $message; });
        $message->shouldReceive('subject')->andReturnUsing(function ($subject) use ($message, &$sent) { $sent['subject'] = $subject; return $message; });
        $callback($message);
        $sent['text'] = $text;
    });
    $this->actingAs(User::factory()->create(['organizer_id' => null, 'email' => 'anna@example.com']));

    Volt::test('club.create')->set('name', 'Kalmar LK')->call('create');

    expect($sent['to'])->toBe('hej@lopning.se')
        ->and($sent['subject'])->toBe('Ny arrangör: Kalmar LK')
        ->and($sent['text'])->toContain('anna@example.com')->toContain('/kalmar-lk');
});

it('gives a club named like a path of the app a slug that does not collide', function () {
    Mail::fake();

    foreach (['Events', 'Settings', 'Club'] as $name) {
        $this->actingAs(User::factory()->create(['organizer_id' => null]));
        Volt::test('club.create')->set('name', $name)->call('create');
    }

    expect(Organizer::orderBy('id')->pluck('slug')->all())->toBe(['events-2', 'settings-2', 'club-2']);
});

it('requires a club name and does not create a second organizer', function () {
    Mail::fake();
    $user = User::factory()->create(['organizer_id' => null]);
    $this->actingAs($user);

    Volt::test('club.create')->set('name', '  ')->call('create')->assertHasErrors(['name']);
    expect(Organizer::count())->toBe(0);

    Organizer::open('Kalmar LK', $user);
    Volt::test('club.create')->assertRedirect(route('events.index'));
});
