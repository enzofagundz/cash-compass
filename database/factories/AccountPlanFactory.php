<?php

namespace Database\Factories;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Models\AccountPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountPlan>
 */
class AccountPlanFactory extends Factory
{
    protected $model = AccountPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Expense,
            'description' => fake()->word(),
            'expected_amount' => fake()->randomFloat(2, 10, 5000),
            'frequency' => RecurrenceFrequency::Monthly,
            'interval' => 1,
            'starts_at' => now()->startOfDay(),
            'is_active' => true,
        ];
    }

    public function monthly(int $dayOfMonth = 16): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::Income,
            'frequency' => RecurrenceFrequency::Monthly,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
