<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'series_id' => fn (array $attributes) => Series::factory()->create(['organizer_id' => $attributes['organizer_id']]),
            'name' => fake()->city().'loppet',
            'date' => '2026-10-03',
            'timezone' => 'Europe/Stockholm',
        ];
    }
}
