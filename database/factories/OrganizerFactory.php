<?php

namespace Database\Factories;

use App\Models\Organizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organizer> */
class OrganizerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'country' => 'SE',
            'timezone' => 'Europe/Stockholm',
            'currency' => 'SEK',
            'locale' => 'sv',
        ];
    }
}
