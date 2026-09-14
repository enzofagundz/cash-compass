<?php

namespace Tests\Feature\Models;

use App\Models\User;
use App\Models\UserInitialBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInitialBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_their_own_initial_balance(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA);
        UserInitialBalance::create(['amount' => 5000, 'base_date' => '2026-01-01']);

        $this->actingAs($userB);
        UserInitialBalance::create(['amount' => 9000, 'base_date' => '2026-01-01']);

        $this->actingAs($userA);
        $balances = UserInitialBalance::all();

        $this->assertCount(1, $balances);
        $this->assertSame('5000.00', $balances->first()->amount);
    }
}
