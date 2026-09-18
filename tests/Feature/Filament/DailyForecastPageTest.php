<?php

use App\Filament\Pages\DailyForecastPage;
use App\Models\DailyForecast;
use App\Models\User;
use Livewire\Livewire;

it('lets regular users open the daily forecast page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/daily-forecast')->assertOk();
});

it('forbids admins from the daily forecast page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/daily-forecast')->assertForbidden();
});

it('hides the daily forecast page from the sidebar navigation', function () {
    expect(DailyForecastPage::shouldRegisterNavigation())->toBeFalse();
});

it('shows the daily forecast link in the profile menu for regular users', function () {
    $this->actingAs(User::factory()->create());

    $this->followingRedirects()->get('/')->assertOk()->assertSee('Previsão de diário');
});

it('hides the daily forecast link from the admin profile menu', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->followingRedirects()->get('/')->assertOk()->assertDontSee('Previsão de diário');
});

it('shows an empty state when there are no items', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(DailyForecastPage::class)
        ->assertSee('Nenhuma previsão cadastrada')
        ->assertSee('Nova previsão')
        ->assertSee('Alterar divisor');
});

it('adds a forecast item from the page', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(DailyForecastPage::class)
        ->callAction('newItem', data: [
            'description' => 'Mercado',
            'amount' => 1200,
        ])
        ->assertHasNoActionErrors()
        ->assertSee('Mercado')
        ->assertSee('R$ 1.200,00');

    $item = DailyForecast::firstOrFail();

    expect($item->user_id)->toBe($user->id)
        ->and($item->description)->toBe('Mercado')
        ->and($item->amount)->toBe('1200.00');
});

it('requires a description and a positive amount', function (array $data, string $error) {
    $this->actingAs(User::factory()->create());

    Livewire::test(DailyForecastPage::class)
        ->callAction('newItem', data: $data)
        ->assertHasActionErrors([$error]);

    expect(DailyForecast::count())->toBe(0);
})->with([
    'missing description' => [['amount' => 100], 'description'],
    'zero amount' => [['description' => 'Mercado', 'amount' => 0], 'amount'],
    'negative amount' => [['description' => 'Mercado', 'amount' => -10], 'amount'],
]);

it('edits a forecast item from the page', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $item = DailyForecast::factory()->create([
        'user_id' => $user->id,
        'description' => 'Mercado',
        'amount' => 1200,
    ]);

    Livewire::test(DailyForecastPage::class)
        ->callAction('editItem', data: [
            'description' => 'Mercado e feira',
            'amount' => 1500,
        ], arguments: ['item' => $item->id])
        ->assertHasNoActionErrors()
        ->assertSee('Mercado e feira');

    expect($item->refresh()->amount)->toBe('1500.00')
        ->and($item->description)->toBe('Mercado e feira');
});

it('deletes a forecast item immediately without confirmation', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $item = DailyForecast::factory()->create(['user_id' => $user->id]);

    Livewire::test(DailyForecastPage::class)
        ->call('deleteItem', $item->id)
        ->assertHasNoErrors();

    expect(DailyForecast::find($item->id))->toBeNull();
});

it('never edits or deletes another users item', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $foreign = DailyForecast::create(['description' => 'Do outro', 'amount' => 500]);

    $this->actingAs($user);

    Livewire::test(DailyForecastPage::class)
        ->assertActionHidden('editItem', ['item' => $foreign->id])
        ->call('deleteItem', $foreign->id);

    expect(DailyForecast::withoutGlobalScopes()->find($foreign->id))->not->toBeNull()
        ->and($foreign->refresh()->amount)->toBe('500.00');
});

it('keeps duplicate descriptions in creation order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $first = DailyForecast::factory()->create(['user_id' => $user->id, 'description' => 'Mercado']);
    $second = DailyForecast::factory()->create(['user_id' => $user->id, 'description' => 'Mercado']);

    $items = Livewire::test(DailyForecastPage::class)->instance()->summary()['items'];

    expect(array_column($items, 'id'))->toBe([$first->id, $second->id]);
});

it('shows the monthly total, the daily amount and the divisor', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create(['forecast_divisor_days' => 30]);
    $this->actingAs($user);

    DailyForecast::create(['description' => 'Mercado', 'amount' => 1200]);

    Livewire::test(DailyForecastPage::class)
        ->assertSee('R$ 1.200,00')
        ->assertSee('R$ 40,00')
        ->assertSee('30 dias');
});

it('updates the divisor and recalculates the daily amount', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create(['forecast_divisor_days' => 30]);
    $this->actingAs($user);

    DailyForecast::create(['description' => 'Mercado', 'amount' => 600]);

    Livewire::test(DailyForecastPage::class)
        ->callAction('configureDivisor', data: ['divisor_days' => 20])
        ->assertHasNoActionErrors()
        ->assertSee('R$ 30,00');

    expect($user->refresh()->forecast_divisor_days)->toBe(20);
});

it('rejects an out of range divisor', function (int $divisor) {
    $this->actingAs(User::factory()->create());

    Livewire::test(DailyForecastPage::class)
        ->callAction('configureDivisor', data: ['divisor_days' => $divisor])
        ->assertHasActionErrors(['divisor_days']);

    expect(User::firstOrFail()->forecast_divisor_days)->toBe(30);
})->with([0, 32]);
