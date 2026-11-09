<?php

namespace Database\Factories;

use App\Models\CardItem;
use App\Models\DailyTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardItem>
 */
class CardItemFactory extends Factory
{
    protected $model = CardItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_transaction_id' => DailyTransaction::factory()->card(),
            'description' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 1, 200),
        ];
    }

    public function forTransaction(DailyTransaction $transaction): static
    {
        return $this->state(fn (): array => [
            'daily_transaction_id' => $transaction->getKey(),
        ]);
    }
}
