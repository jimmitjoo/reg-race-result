<?php

use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TimingController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Volt::route('events', 'events.index')->name('events.index');
    Volt::route('events/{event}', 'events.show')->name('events.show');
    Volt::route('events/{event}/start', 'events.start')->name('start.show');
    Volt::route('events/{event}/results', 'events.results')->name('results.show');
    Route::get('events/{event}/timing', [TimingController::class, 'show'])->name('timing.show');
    Route::post('events/{event}/reads', [TimingController::class, 'storeReads'])->name('timing.reads.store');
});

require __DIR__.'/auth.php';

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// Public pages by slug. Registered last so that fixed paths (settings, events, login …) win.
// Later the organizer can also come from a custom domain (#43).
Volt::route('{organizer}/{event}', 'public.event')->name('public.event');
Route::get('{organizer}/{event}/registrations/{registration}', [PublicRegistrationController::class, 'show'])
    ->middleware('signed')
    ->name('public.registration');
