<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\DailyTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTransaction>
 */
class DailyTransactionFactory extends Factory
{
    protected $model = DailyTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->startOfDay(),
            'type' => TransactionType::Expense,
            'amount' => fake()->randomFloat(2, 1, 1000),
            'description' => fake()->optional()->word(),
            'is_recurring' => false,
            'status' => TransactionStatus::Realized,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TransactionStatus::Pending,
            'is_recurring' => true,
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::Income,
        ]);
    }
}
