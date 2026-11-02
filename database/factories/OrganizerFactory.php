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
            'stripe_account_id' => 'acct_'.fake()->unique()->bothify('????????'),
            'stripe_details_submitted' => true,
            'stripe_charges_enabled' => true,
        ];
    }

    public function unconnected(): static
    {
        return $this->state(['stripe_account_id' => null, 'stripe_details_submitted' => false, 'stripe_charges_enabled' => false]);
    }
}
