<?php

namespace Database\Factories;

use App\Enums\TagColor;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'color' => TagColor::Neutral,
            'is_active' => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
