<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** An arrangemang that comes back every year (Sylvesterloppet); each event is one upplaga of it. */
class Series extends Model
{
    use HasFactory;

    protected $table = 'series';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Series $series) {
            if (! $series->slug) {
                $base = Str::slug($series->name);
                $slug = $base;
                for ($n = 2; static::where('organizer_id', $series->organizer_id)->where('slug', $slug)->exists(); $n++) {
                    $slug = "{$base}-{$n}";
                }
                $series->slug = $slug;
            }
        });
    }

    /** "Sylvesterloppet 2026" is typed as often as "Sylvesterloppet"; the year belongs to the upplaga. */
    public static function nameFrom(string $name): string
    {
        return preg_replace('/\s+\d{4}$/', '', trim($name));
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function addEdition(string $date, array $attributes = []): Event
    {
        return $this->events()->create($attributes + [
            'organizer_id' => $this->organizer_id,
            'name' => $this->name.' '.CarbonImmutable::parse($date)->year,
            'date' => $date,
            'timezone' => $this->organizer->timezone,
        ]);
    }

    /**
     * A new upplaga set up like the latest one: details, races, classes, prices and prize names.
     * Price dates move as many days as the race day; start times keep their local clock time.
     * Registrations, chips, results and the weather belong to the old upplaga.
     */
    public function newEdition(string $date): Event
    {
        $previous = $this->events()->with(['races.priceSteps', 'raceClasses', 'prizes'])->latest('date')->firstOrFail();

        return DB::transaction(function () use ($previous, $date) {
            $event = $this->addEdition($date, $previous->only(['city', 'race_director', 'contact_email', 'timezone']));
            $days = (int) $previous->date->diffInDays($event->date, false);

            foreach ($previous->races as $race) {
                $copy = $event->races()->create($race->only(['name', 'onsite_price', 'type', 'course_measurer', 'measured_on', 'age_groups', 'championship', 'championship_districts', 'championship_veterans']));

                foreach ($race->priceSteps as $step) {
                    $copy->priceSteps()->create(['until' => $step->until->addDays($days), 'amount' => $step->amount]);
                }

                foreach ($previous->raceClasses->where('race_id', $race->id) as $class) {
                    $time = $class->start_at->setTimezone($event->timezone)->format('H:i:s');
                    $event->raceClasses()->create($class->only(['name', 'distance_meters', 'gender', 'timed', 'min_time_seconds']) + [
                        'race_id' => $copy->id,
                        'start_at' => CarbonImmutable::parse("{$event->date->toDateString()} {$time}", $event->timezone),
                    ]);
                }
            }

            foreach ($previous->prizes as $prize) {
                $event->prizes()->create(['name' => $prize->name]);
            }

            return $event;
        });
    }
}
