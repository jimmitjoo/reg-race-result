<?php

namespace Database\Factories;

use App\Models\Organizer;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Series> */
class SeriesFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'name' => fake()->city().'loppet',
        ];
    }
}
