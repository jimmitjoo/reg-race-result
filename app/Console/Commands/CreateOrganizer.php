<?php

namespace App\Console\Commands;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateOrganizer extends Command
{
    protected $signature = 'organizer:create {name : The club name} {email : The first user, who gets a link to choose a password}';

    protected $description = 'Create an organizer (SE, Europe/Stockholm, SEK, sv) and connect its first user';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));
        $user = User::where('email', $email)->first();

        if ($user?->organizer_id) {
            $this->error("{$email} already belongs to an organizer.");

            return self::FAILURE;
        }

        $user ??= User::create(['name' => Str::before($email, '@'), 'email' => $email, 'password' => Str::password()]);
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        $organizer = Organizer::open($this->argument('name'), $user);

        Password::sendResetLink(['email' => $email]);

        $this->info("{$organizer->name} created at /{$organizer->slug}, password link sent to {$email}.");

        return self::SUCCESS;
    }
}
