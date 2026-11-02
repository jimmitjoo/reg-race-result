<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Organizer extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** A Swedish club (SE, Europe/Stockholm, SEK, sv) with the user as its first member. */
    public static function open(string $name, User $user): self
    {
        $organizer = static::create([
            'name' => $name,
            'slug' => static::uniqueSlug($name),
            'country' => 'SE',
            'timezone' => 'Europe/Stockholm',
            'currency' => 'SEK',
            'locale' => 'sv',
        ]);
        $user->update(['organizer_id' => $organizer->id]);

        return $organizer;
    }

    /** The public pages live at /{organizer}, so the slug may not be a path of the app itself (/events, /login …). */
    public static function uniqueSlug(string $name): string
    {
        $taken = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => Str::before($route->uri(), '/'))->all();
        $base = Str::slug($name) ?: 'arrangor';
        $slug = $base;

        for ($n = 2; in_array($slug, $taken, true) || static::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
