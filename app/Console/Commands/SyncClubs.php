<?php

namespace App\Console\Commands;

use App\Models\Club;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncClubs extends Command
{
    protected $signature = 'clubs:sync';

    protected $description = 'Sync the club list of Svenska Friidrottsförbundet (the list SFIF itself points to)';

    private const URL = 'https://www.tilastopaja.com/json/swe/sweAPI.php?type=clubs';

    public function handle(): int
    {
        $response = Http::timeout(30)->get(self::URL);
        $clubs = $response->successful() ? json_decode(ltrim($response->body(), "\u{FEFF}"), true)['clubs'] ?? null : null;

        if (! is_array($clubs) || $clubs === []) {
            $this->error('Could not read the club list; the existing list is kept.');

            return self::FAILURE;
        }

        foreach ($clubs as $club) {
            Club::updateOrCreate(
                ['federation' => 'SFIF', 'external_id' => (string) $club['id']],
                ['name' => trim($club['club']), 'district' => $club['district'] ?: null, 'municipality' => ($club['kommun'] ?? '') ?: null],
            );
        }

        $this->info(count($clubs).' clubs synced.');

        return self::SUCCESS;
    }
}
