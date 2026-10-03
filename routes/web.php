<?php

use App\Http\Controllers\ExportController;
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
    Route::prefix('events/{event}')->name('events.')->group(function () {
        Volt::route('start', 'events.start')->name('start');
        Volt::route('results', 'events.results')->name('results');
        Volt::route('missing', 'events.missing')->name('missing');
        Volt::route('uncollected', 'events.uncollected')->name('uncollected');
        Volt::route('chips', 'events.chips')->name('chips');
        Volt::route('prizes', 'events.prizes')->name('prizes');
        Volt::route('onsite', 'events.onsite')->name('onsite');
        Route::get('exports/sfif', [ExportController::class, 'sfif'])->name('exports.sfif');
        Route::get('exports/pdf', [ExportController::class, 'pdf'])->name('exports.pdf');
        Route::get('timing', [TimingController::class, 'show'])->name('timing');
        Route::post('reads', [TimingController::class, 'storeReads'])->name('timing.reads');
        Route::post('timing/upload', [TimingController::class, 'upload'])->name('timing.upload');
    });
});

require __DIR__.'/auth.php';

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// Public pages by slug. Registered last so that fixed paths (settings, events, login …) win.
// Later the organizer can also come from a custom domain (#43).
Volt::route('{organizer}/{event}', 'public.event')->name('public.event');
Volt::route('{organizer}/{event}/start-list', 'public.start-list')->name('public.start-list');
Route::get('{organizer}/{event}/registrations/{registration}', [PublicRegistrationController::class, 'show'])
    ->middleware('signed')
    ->name('public.registration');
