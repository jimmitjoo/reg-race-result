<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(fn (Club $club) => $club->normalized_name = self::normalize($club->name));
    }

    /** The official club for what a runner typed ("högby if " → Högby IF), or null for a home town. */
    public static function match(string $typed): ?self
    {
        $normalized = self::normalize($typed);

        return $normalized === '' ? null : static::where('normalized_name', $normalized)->first();
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }
}
