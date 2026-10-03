<?php

namespace App\Providers;

use App\Models\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every admin {event} belongs to the signed in user's organizer; others are 404.
        // Public pages look up organizer and event by slug themselves.
        Route::bind('event', fn (string $value, $route) => $route->named('public.*')
            ? $value
            : Event::where('organizer_id', auth()->user()?->organizer_id)->findOrFail($value));
    }
}
