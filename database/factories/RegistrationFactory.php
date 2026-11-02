<?php

namespace Database\Factories;

use App\Models\RaceClass;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Registration> */
class RegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'race_class_id' => RaceClass::factory(),
            'bib' => fake()->unique()->numberBetween(1, 9999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => 'registered',
            'paid_at' => now(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(['paid_at' => null]);
    }
}
