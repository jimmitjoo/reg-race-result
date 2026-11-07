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
                // 'terms' is a public page of the organizer, like for events.
                for ($n = 2; $slug === 'terms' || static::where('organizer_id', $series->organizer_id)->where('slug', $slug)->exists(); $n++) {
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

    /** The upplaga the arrangemang's link shows: the next one coming, or the latest when none is. */
    public function current(): ?Event
    {
        $today = now($this->organizer->timezone)->toDateString();

        return $this->events()->whereDate('date', '>=', $today)->oldest('date')->first()
            ?? $this->events()->latest('date')->first();
    }

    /**
     * The slug stays, so that links on club sites and posters keep working. Upplagor with the old
     * name and their year ("Kalmar Malkars 21KM 2026") follow; one given a name of its own keeps it.
     */
    public function rename(string $name): void
    {
        DB::transaction(function () use ($name) {
            foreach ($this->events as $event) {
                if ($event->name === "{$this->name} {$event->date->year}") {
                    $event->update(['name' => "{$name} {$event->date->year}"]);
                }
            }
            $this->update(['name' => $name]);
        });
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
     * Campaigns come along without their codes (#85). Registrations, chips, results and the weather belong to the old upplaga.
     */
    public function newEdition(string $date): Event
    {
        $previous = $this->events()->with(['races.priceSteps', 'raceClasses', 'prizes', 'campaigns'])->latest('date')->firstOrFail();

        return DB::transaction(function () use ($previous, $date) {
            $event = $this->addEdition($date, $previous->only(['city', 'race_director', 'contact_email', 'timezone']));
            $days = (int) $previous->date->diffInDays($event->date, false);

            $raceIds = [];
            foreach ($previous->races as $race) {
                $copy = $event->races()->create($race->only(['name', 'position', 'onsite_price', 'type', 'course_measurer', 'measured_on', 'age_groups', 'championship', 'championship_districts', 'championship_veterans']));
                $raceIds[$race->id] = $copy->id;

                foreach ($race->priceSteps as $step) {
                    $copy->priceSteps()->create(['until' => $step->until->addDays($days), 'amount' => $step->amount]);
                }

                foreach ($previous->raceClasses->where('race_id', $race->id) as $class) {
                    $time = $class->start_at->setTimezone($event->timezone)->format('H:i:s');
                    $event->raceClasses()->create($class->only(['name', 'position', 'distance_meters', 'team_size', 'gender', 'timed', 'min_time_seconds']) + [
                        'race_id' => $copy->id,
                        'start_at' => CarbonImmutable::parse("{$event->date->toDateString()} {$time}", $event->timezone),
                    ]);
                }
            }

            foreach ($previous->campaigns as $campaign) {
                $campaign->copyTo($event, $raceIds);
            }

            foreach ($previous->prizes as $prize) {
                $event->prizes()->create(['name' => $prize->name]);
            }

            return $event;
        });
    }
}
