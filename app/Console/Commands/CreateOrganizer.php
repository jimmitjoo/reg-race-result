<?php

namespace App\Console\Commands;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
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

        $organizer = Organizer::create([
            'name' => $this->argument('name'),
            'slug' => self::slug($this->argument('name')),
            'country' => 'SE',
            'timezone' => 'Europe/Stockholm',
            'currency' => 'SEK',
            'locale' => 'sv',
        ]);

        $user ??= new User(['name' => Str::before($email, '@'), 'email' => $email, 'password' => Str::password()]);
        $user->organizer_id = $organizer->id;
        $user->email_verified_at ??= now();
        $user->save();

        Password::sendResetLink(['email' => $email]);

        $this->info("{$organizer->name} created at /{$organizer->slug}, password link sent to {$email}.");

        return self::SUCCESS;
    }

    /** The public pages live at /{organizer}, so the slug may not be a path of the app itself (/events, /login …). */
    public static function slug(string $name): string
    {
        $taken = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => Str::before($route->uri(), '/'))->all();
        $base = Str::slug($name) ?: 'arrangor';
        $slug = $base;

        for ($n = 2; in_array($slug, $taken, true) || Organizer::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
