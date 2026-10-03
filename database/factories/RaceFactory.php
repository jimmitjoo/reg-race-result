<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Race;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Race> */
class RaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->city().'loppet',
        ];
    }
}
