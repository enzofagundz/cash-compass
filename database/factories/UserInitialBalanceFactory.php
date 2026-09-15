<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserInitialBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserInitialBalance>
 */
class UserInitialBalanceFactory extends Factory
{
    protected $model = UserInitialBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 0,
            'base_date' => now()->startOfDay(),
        ];
    }
}
