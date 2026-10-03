<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function raceClasses(): HasMany
    {
        return $this->hasMany(RaceClass::class);
    }

    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, RaceClass::class);
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
