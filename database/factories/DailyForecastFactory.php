<?php

namespace Database\Factories;

use App\Models\DailyForecast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyForecast>
 */
class DailyForecastFactory extends Factory
{
    protected $model = DailyForecast::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 10, 2000),
        ];
    }
}
