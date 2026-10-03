<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prize> */
class PrizeFactory extends Factory
{
    public function definition(): array
    {
        return ['event_id' => Event::factory(), 'name' => 'Presentkort'];
    }
}
