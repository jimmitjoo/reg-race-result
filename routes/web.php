<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TimingController;
use App\Models\Organizer;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// The admin starts at the events; signing in is required there.
Route::redirect('/', '/events')->name('home');
Route::redirect('dashboard', '/events')->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Volt::route('club', 'club.create')->name('club.create');
    Volt::route('payments', 'payments.index')->name('payments.index');
    Route::get('payments/return', [StripeConnectController::class, 'back'])->name('payments.return');
    Route::get('payments/refresh', [StripeConnectController::class, 'refresh'])->name('payments.refresh');
    Volt::route('sites', 'sites.index')->name('sites.index');
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
        Volt::route('registrations', 'events.registrations')->name('registrations');
        Volt::route('campaigns', 'events.campaigns')->name('campaigns');
        Route::get('campaigns/{campaign}/codes', [ExportController::class, 'campaignCodes'])->name('campaigns.codes');
        Route::get('campaigns/{campaign}/invoice', [ExportController::class, 'invoiceBasis'])->name('campaigns.invoice');
        Route::get('exports/sfif', [ExportController::class, 'sfif'])->name('exports.sfif');
        Route::get('exports/registrations', [ExportController::class, 'registrations'])->name('exports.registrations');
        Route::get('exports/pdf', [ExportController::class, 'pdf'])->name('exports.pdf');
        Route::get('timing', [TimingController::class, 'show'])->name('timing');
        Route::post('reads', [TimingController::class, 'storeReads'])->name('timing.reads');
        Route::post('timing/upload', [TimingController::class, 'upload'])->name('timing.upload');
    });
});

require __DIR__.'/auth.php';

Route::post('stripe/webhook', [StripeWebhookController::class, 'platform'])->name('stripe.webhook');
Route::post('stripe/connect/webhook', [StripeWebhookController::class, 'connect'])->name('stripe.connect-webhook');

// Public pages by slug. Registered last so that fixed paths (settings, events, login …) win.
// Later the organizer can also come from a custom domain (#43).
Route::get('{organizer}/terms', function (string $organizer) {
    $organizer = Organizer::where('slug', $organizer)->firstOrFail();
    $email = $organizer->events()->whereNotNull('contact_email')->latest('date')->value('contact_email');

    return view('public.terms', ['organizer' => $organizer, 'email' => $email]);
})->name('public.terms');
Volt::route('{organizer}', 'public.organizer')->name('public.organizer');
Volt::route('{organizer}/{event}', 'public.event')->name('public.event');
Volt::route('{organizer}/{event}/start-list', 'public.start-list')->name('public.start-list');
Volt::route('{organizer}/{event}/results', 'public.results')->name('public.results');
Route::get('{organizer}/{event}/registrations/{registration}', [PublicRegistrationController::class, 'show'])
    ->middleware('signed')
    ->name('public.registration');
Route::get('{organizer}/{event}/registrations/{registration}/receipt', [PublicRegistrationController::class, 'receipt'])
    ->middleware('signed')
    ->name('public.receipt');
Route::get('{organizer}/{event}/registrations/{registration}/calendar', [PublicRegistrationController::class, 'calendar'])
    ->middleware('signed')
    ->name('public.registration.calendar');
