<?php

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
    Route::get('events/{event}/timing', [TimingController::class, 'show'])->name('timing.show');
    Route::post('events/{event}/reads', [TimingController::class, 'storeReads'])->name('timing.reads.store');
});

require __DIR__.'/auth.php';
