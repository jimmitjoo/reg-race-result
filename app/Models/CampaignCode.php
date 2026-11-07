<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A personal single-use code (#85): reserved when the registration is made, used when it is paid or invoiced. */
class CampaignCode extends Model
{
    public const APPLIES_TO = ['both', 'individual', 'team'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valid_until' => 'immutable_date', 'reserved_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /** Never used, and not held by a registration still on its way to paying. */
    public function scopeFree(Builder $query): void
    {
        $query->whereNull('used_at')->where(fn ($q) => $q->whereNull('registration_id')
            ->orWhere('reserved_at', '<', now()->subMinutes(Campaign::HOLD_MINUTES)));
    }

    /** Eight characters without the ones that are easy to mix up (0/O, 1/I/L). */
    public static function generate(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => '23456789ABCDEFGHJKMNPQRSTUVWXYZ'[random_int(0, 30)])->implode('');
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
