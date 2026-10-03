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
        // Every {event} in a route belongs to the signed in user's organizer; others are 404.
        Route::bind('event', fn (string $id) => Event::where('organizer_id', auth()->user()?->organizer_id)->findOrFail($id));
    }
}
