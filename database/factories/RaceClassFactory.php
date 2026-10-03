<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Race;
use App\Models\RaceClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RaceClass> */
class RaceClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'race_id' => fn (array $attributes) => Race::factory()->create(['event_id' => $attributes['event_id']]),
            'name' => 'Kvinnor 10 km',
            'distance_meters' => 10000,
            'gender' => 'K',
            'timed' => true,
            'start_at' => '2026-10-03 08:00:00',
            'min_time_seconds' => 600,
        ];
    }
}
