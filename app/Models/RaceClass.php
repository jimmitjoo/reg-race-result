<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Results\AgeGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RaceClass extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_at' => UtcDateTime::class, 'timed' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    /** M45, F15 … when the race has age groups: from the birth date and the upplaga's year, as in the results. */
    public function ageGroupOf(Registration $registration): ?string
    {
        if (! $this->race?->age_groups || ! $registration->birth_date) {
            return null;
        }

        return AgeGroup::for($registration->birth_date->year, $registration->gender ?? $this->gender ?? '', $this->event->date->year);
    }

    // Paid only, like Event::registrations().
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class)->whereNotNull('registrations.paid_at')->whereNull('registrations.refunded_at');
    }
}
