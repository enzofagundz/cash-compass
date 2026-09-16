<?php

namespace Database\Factories;

use App\Models\DayCheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayCheckIn>
 */
class DayCheckInFactory extends Factory
{
    protected $model = DayCheckIn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->startOfDay(),
        ];
    }
}
