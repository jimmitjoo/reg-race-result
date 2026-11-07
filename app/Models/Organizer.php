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

    protected function casts(): array
    {
        return ['stripe_details_submitted' => 'boolean', 'stripe_charges_enabled' => 'boolean', 'theme' => 'array', 'theme_draft' => 'array', 'page' => 'array', 'page_draft' => 'array'];
    }

    /** Online payments go to the organizer's own connected Stripe account, or not at all (#47). */
    public function canTakePayments(): bool
    {
        return $this->stripe_account_id && $this->stripe_charges_enabled;
    }

    /** The platform's part of a registration price, in minor units. */
    public function platformFee(int $price): int
    {
        return min($price, (int) round($price * $this->fee_basis_points / 10000) + $this->fee_fixed);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

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
