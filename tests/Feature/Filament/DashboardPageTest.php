<?php

use App\Enums\TransactionType;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports;
use App\Models\DailyTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
});

it('shows the current month summary to non-admin users', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DailyTransaction::factory()->for($user)->create([
        'date' => '2026-10-10',
        'type' => TransactionType::Expense,
        'amount' => '100.00',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('Resumo do mês atual')
        ->assertSee('Total gasto')
        ->assertSee('R$ 100,00')
        ->assertSee('Média por dia ativo')
        ->assertSee('Tag líder')
        ->assertSee('Abrir relatórios')
        ->assertSeeHtml('href="'.url('/reports').'"');
});

it('forbids administrators from the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/dashboard')->assertForbidden();
});

it('does not render the sample account and filament widgets', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get('/dashboard');

    $response->assertOk()
        ->assertDontSee('fi-account-widget')
        ->assertDontSee('fi-filament-info-widget');
});

it('keeps the dashboard on the current month regardless of the reports filter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DailyTransaction::factory()->for($user)->create([
        'date' => '2026-10-10',
        'amount' => '100.00',
    ]);
    DailyTransaction::factory()->for($user)->create([
        'date' => '2026-09-10',
        'amount' => '50.00',
    ]);

    Livewire::test(Reports::class)
        ->set('filters.preset', 'last_month')
        ->assertSee('Setembro de 2026');

    Livewire::test(Dashboard::class)
        ->assertSee('Resumo do mês atual')
        ->assertSee('R$ 100,00')
        ->assertSee('Outubro de 2026');
});

it('renders the dashboard without spending without errors', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Total gasto');
});
