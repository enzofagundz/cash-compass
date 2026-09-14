<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\InitialBalanceSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InitialBalanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_initial_balance_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/initial-balance')->assertOk();
    }

    public function test_user_can_update_own_initial_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(InitialBalanceSettings::class)
            ->set('data.amount', '5000.00')
            ->set('data.base_date', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $balance = $user->initialBalance()->firstOrFail();

        $this->assertSame('5000.00', $balance->amount);
        $this->assertSame('2026-01-01', $balance->base_date->format('Y-m-d'));
    }
}
