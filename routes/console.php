<?php

use App\Models\FormResponse;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('clubs:sync')->weekly();
Schedule::command('registrations:prune-contacts')->daily();
Schedule::command('payments:reconcile')->everyTenMinutes();
Schedule::call(fn () => FormResponse::where('created_at', '<', now()->subMonths(config('privacy.form_retention_months')))->delete())
    ->daily()->name('forms:prune-responses');
