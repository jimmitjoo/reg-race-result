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
        return ['measured_on' => 'immutable_date'];
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
