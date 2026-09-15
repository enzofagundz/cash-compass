<?php

use App\Filament\Pages\InitialBalanceSettings;
use App\Models\User;
use Filament\Notifications\Notification;
use Livewire\Livewire;

it('lets users view initial balance settings', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/initial-balance')->assertOk();
});

it('forbids admins from initial balance settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/initial-balance')->assertForbidden();
});

it('lets users update their own initial balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(InitialBalanceSettings::class)
        ->set('data.amount', '5000.00')
        ->set('data.base_date', '2026-01-01')
        ->call('save')
        ->assertHasNoErrors();

    $balance = $user->initialBalance()->firstOrFail();

    expect($balance->amount)->toBe('5000.00')
        ->and($balance->base_date->format('Y-m-d'))->toBe('2026-01-01');

    Notification::assertNotified();
});

it('makes the base date optional when saving the initial balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(InitialBalanceSettings::class)
        ->set('data.amount', '1000.00')
        ->call('save')
        ->assertHasNoErrors();

    $balance = $user->initialBalance()->firstOrFail();

    expect($balance->amount)->toBe('1000.00')
        ->and($balance->base_date)->toBeNull();
});
