<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (! $event->slug) {
                $base = Str::slug($event->name);
                $slug = $base;
                for ($n = 2; static::where('organizer_id', $event->organizer_id)->where('slug', $slug)->exists(); $n++) {
                    $slug = "{$base}-{$n}";
                }
                $event->slug = $slug;
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function races(): HasMany
    {
        return $this->hasMany(Race::class);
    }

    public function raceClasses(): HasMany
    {
        return $this->hasMany(RaceClass::class);
    }

    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, RaceClass::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class);
    }

    public function chips(): HasMany
    {
        return $this->hasMany(Chip::class);
    }

    public function chipReads(): HasMany
    {
        return $this->hasMany(ChipRead::class);
    }
}
