<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Race extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const TYPES = ['Väg', 'Terräng', 'Trail'];

    protected function casts(): array
    {
        return ['measured_on' => 'immutable_date', 'age_groups' => 'boolean', 'championship_districts' => 'array', 'championship_veterans' => 'boolean'];
    }

    /** Whether a runner of this club counts in the race's championship (DM: club in a chosen district; SM: any federation club). */
    public function eligibleForChampionship(?Club $club): bool
    {
        return match ($this->championship) {
            'SM' => $club !== null,
            'DM' => $club !== null && in_array($club->district, $this->championship_districts ?? [], true),
            default => false,
        };
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function raceClasses(): HasMany
    {
        return $this->hasMany(RaceClass::class);
    }

    public function priceSteps(): HasMany
    {
        return $this->hasMany(PriceStep::class)->orderBy('until');
    }

    /** Online price at a moment, or null when no step is valid any more (online registration closed). */
    public function priceAt(CarbonInterface $at): ?int
    {
        $date = $at->copy()->setTimezone($this->event->timezone)->toDateString();

        return $this->priceSteps()->where('until', '>=', $date)->value('amount');
    }
}
