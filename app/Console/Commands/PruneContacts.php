<?php

namespace App\Console\Commands;

use App\Models\Registration;
use Illuminate\Console\Command;

class PruneContacts extends Command
{
    protected $signature = 'registrations:prune-contacts';

    protected $description = 'Delete email and phone of registrations some months after their event (names and results are kept)';

    public function handle(): int
    {
        $before = now()->subMonths(config('privacy.contact_retention_months'))->toDateString();

        $count = Registration::query()
            ->whereHas('raceClass.event', fn ($q) => $q->where('date', '<', $before))
            ->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone'))
            ->update(['email' => null, 'phone' => null]);

        $this->info("{$count} registrations pruned.");

        return self::SUCCESS;
    }
}
