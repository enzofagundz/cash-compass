<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Pages\DailyForecastPage;
use App\Filament\Pages\ThermometerPage;
use App\Models\AccountPlan;
use App\Models\DailyForecast;
use App\Models\DailyTransaction;
use App\Models\DayCheckIn;
use App\Models\Tag;
use App\Models\User;
use App\Services\RecurrenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('lets users open the thermometer and forbids admins', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/thermometer')->assertOk();

    $this->actingAs(User::factory()->admin()->create());

    $this->get('/thermometer')->assertForbidden();
});

it('defaults to a twelve month horizon starting on the current month', function () {
    $this->travelTo('2026-09-15');
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(ThermometerPage::class);

    expect($component->year)->toBe(2026)
        ->and($component->month)->toBe(9)
        ->and($component->months)->toBe(12)
        ->and($component->horizonMonths)->toBe(12)
        ->and($component->periodLabel)->toBe('set/2026 – ago/2027')
        ->and($component->horizon)->toHaveCount(12)
        ->and($component->horizon[0]['label'])->toBe('setembro de 2026')
        ->and($component->horizon[11]['label'])->toBe('agosto de 2027');

    $component
        ->assertSee('setembro de 2026')
        ->assertSee('agosto de 2027');
});

it('honours the period and horizon from the url', function () {
    $this->travelTo('2026-09-15');
    $this->actingAs(User::factory()->create());

    $this->get('/thermometer?year=2027&month=3&months=3')
        ->assertOk()
        ->assertSee('mar/2027 – mai/2027');
});

it('falls back to the default period when the url is invalid', function () {
    $this->travelTo('2026-09-15');
    $this->actingAs(User::factory()->create());

    $this->get('/thermometer?year=9999&month=44&months=99')
        ->assertOk()
        ->assertSee('set/2026 – ago/2027');
});

it('shifts the whole horizon by month and year', function () {
    $this->travelTo('2026-09-15');
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(ThermometerPage::class)
        ->call('previousMonth')
        ->assertSet('year', 2026)
        ->assertSet('month', 8);

    $component
        ->call('previousMonth')
        ->assertSet('month', 7)
        ->call('nextMonth')
        ->assertSet('month', 8)
        ->call('previousYear')
        ->assertSet('year', 2025)
        ->assertSet('month', 8)
        ->call('nextYear')
        ->assertSet('year', 2026)
        ->assertSet('month', 8);
});

it('returns to the current month with go to today', function () {
    $this->travelTo('2026-09-15');
    $this->actingAs(User::factory()->create());

    Livewire::test(ThermometerPage::class)
        ->call('nextYear')
        ->assertSet('year', 2027)
        ->call('goToToday')
        ->assertSet('year', 2026)
        ->assertSet('month', 9)
        ->assertSet('months', 12);
});

it('changes the horizon through the period action', function () {
    $this->travelTo('2026-09-15');
    $this->actingAs(User::factory()->create());

    Livewire::test(ThermometerPage::class)
        ->callAction('selectPeriod', data: [
            'year' => 2027,
            'month' => 2,
            'months' => 6,
        ])
        ->assertHasNoActionErrors()
        ->assertSet('year', 2027)
        ->assertSet('month', 2)
        ->assertSet('months', 6);

    Livewire::test(ThermometerPage::class)
        ->callAction('configureHorizon', data: ['months' => 3])
        ->assertHasNoActionErrors()
        ->assertSet('months', 3);
});

it('rejects an out of range year in the period action', function () {
    $this->travelTo('2026-09-15');
    $this->actingAs(User::factory()->create());

    Livewire::test(ThermometerPage::class)
        ->callAction('selectPeriod', data: [
            'year' => 1200,
            'month' => 2,
            'months' => 6,
        ])
        ->assertHasActionErrors(['year']);
});

it('shows zeros on empty days and the accumulated balance', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Expense->value, 'amount' => 200]);

    $days = collect(Livewire::test(ThermometerPage::class)->instance()->horizon[0]['days'])->keyBy('day');

    expect($days)->toHaveCount(31)
        ->and($days[1]['income'])->toBe('0.00')
        ->and($days[1]['expense'])->toBe('0.00')
        ->and($days[1]['balance'])->toBe('0.00')
        ->and($days[3]['balance'])->toBe('800.00');
});

it('marks pending movements as projections and keeps them in the balance', function () {
    $this->travelTo('2026-02-10');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-02-10', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create([
        'date' => '2026-02-16',
        'type' => TransactionType::Income->value,
        'amount' => 5000,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $component = Livewire::test(ThermometerPage::class);

    $days = collect($component->instance()->horizon[0]['days'])->keyBy('day');

    expect($days[15]['balance'])->toBe('1000.00')
        ->and($days[15]['pending_types'])->toBe([])
        ->and($days[16]['balance'])->toBe('6000.00')
        ->and($days[16]['pending_types'])->toBe(['income']);

    $component
        ->assertSeeHtml('<span class="thermometer-value">R$ 1.000,00</span>')
        ->assertSeeHtml('<span class="thermometer-value thermometer-value-projection">R$ 5.000,00</span>')
        ->assertSeeHtml('total R$ 5.000,00, com projeções')
        ->assertDontSee('proj.');
});

it('does not show another users movement', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $other->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 4321]);

    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    $component = Livewire::test(ThermometerPage::class);

    $days = collect($component->instance()->horizon[0]['days'])->keyBy('day');

    expect($days[2]['income'])->toBe('0.00')
        ->and($days[2]['balance'])->toBe('0.00');

    $component->assertDontSee('4.321,00');
});

it('toggles the check in of past and current days only', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ThermometerPage::class)
        ->call('toggleCheckIn', '2026-09-14');

    expect(DayCheckIn::where('date', '2026-09-14')->exists())->toBeTrue();

    $component->assertSeeHtml('aria-pressed="true"')
        ->assertSeeHtml('<use href="#tmb-icon-check" />');

    $component->call('toggleCheckIn', '2026-09-14');

    expect(DayCheckIn::where('date', '2026-09-14')->exists())->toBeFalse();

    $component->assertSeeHtml('aria-pressed="false"');

    $component->call('toggleCheckIn', '2026-09-15');

    expect(DayCheckIn::where('date', '2026-09-15')->exists())->toBeTrue();

    $component->call('toggleCheckIn', '2026-09-20')->assertNotified();

    expect(DayCheckIn::where('date', '2026-09-20')->exists())->toBeFalse();
});

it('only reads the check ins of the authenticated user', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $other->saveInitialBalance(['amount' => 0, 'base_date' => '2026-09-01']);
    DayCheckIn::create(['date' => '2026-09-14']);

    $this->actingAs($user);

    $days = collect(Livewire::test(ThermometerPage::class)->instance()->horizon[0]['days'])->keyBy('day');

    expect($days[14]['is_checked_in'])->toBeFalse();
});

it('lists the movements of a cell in the detail panel', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    $tag = Tag::create(['name' => 'Saúde', 'color' => 'purple']);
    $secondTag = Tag::create(['name' => 'Casa', 'color' => 'teal']);
    $movement = DailyTransaction::create([
        'date' => '2026-09-05',
        'type' => TransactionType::Expense->value,
        'amount' => 150,
        'description' => 'Psicólogo',
    ]);
    $movement->tags()->attach([$tag->id, $secondTag->id]);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-05', 'type' => TransactionType::Expense->value])
        ->assertMountedActionModalSee('Psicólogo')
        ->assertMountedActionModalSee('Saúde')
        ->assertMountedActionModalSee('Casa')
        ->assertMountedActionModalSeeHtml('fi-color-purple')
        ->assertMountedActionModalSeeHtml('fi-color-teal')
        ->assertMountedActionModalSee('R$ 150,00');
});

it('shows the empty state when a cell has no movements', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-05', 'type' => TransactionType::Card->value])
        ->assertMountedActionModalSee('Sem movimentações por aqui');
});

it('does not show tags in the thermometer grid', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Saúde']);
    $movement = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-09-05',
    ]);
    $movement->tags()->attach($tag);

    Livewire::test(ThermometerPage::class)
        ->assertDontSee('Saúde');
});

it('filters the detail panel by type', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    DailyTransaction::create(['date' => '2026-09-05', 'type' => TransactionType::Expense->value, 'amount' => 150, 'description' => 'Psicólogo']);
    DailyTransaction::create(['date' => '2026-09-05', 'type' => TransactionType::Card->value, 'amount' => 20, 'description' => 'Assinatura']);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-05'])
        ->assertMountedActionModalSee('Psicólogo')
        ->set('detailType', TransactionType::Card->value)
        ->assertMountedActionModalSee('Assinatura')
        ->assertMountedActionModalDontSee('Psicólogo');
});

it('creates a manual transaction from the quick create modal', function () {
    $this->travelTo('2026-01-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ThermometerPage::class)
        ->callAction('create', data: [
            'date' => '2026-01-15',
            'type' => TransactionType::Income->value,
            'amount' => 500,
            'description' => 'Extra',
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasNoActionErrors();

    $movement = DailyTransaction::firstOrFail();

    expect($movement->user_id)->toBe($user->id)
        ->and($movement->amount)->toBe('500.00')
        ->and($movement->is_recurring)->toBeFalse();
});

it('prefills the creation form with the clicked date and type', function () {
    $this->travelTo('2026-01-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ThermometerPage::class)
        ->callAction('addMovement', data: [
            'date' => '2026-01-08',
            'type' => TransactionType::Savings->value,
            'amount' => 30,
            'status' => TransactionStatus::Realized->value,
        ], arguments: [
            'date' => '2026-01-08',
            'type' => TransactionType::Savings->value,
        ])
        ->assertHasNoActionErrors();

    $movement = DailyTransaction::firstOrFail();

    expect($movement->date->toDateString())->toBe('2026-01-08')
        ->and($movement->type)->toBe(TransactionType::Savings);
});

it('rejects a non positive amount from the grid', function () {
    $this->travelTo('2026-01-15');
    $this->actingAs(User::factory()->create());

    Livewire::test(ThermometerPage::class)
        ->callAction('addMovement', data: [
            'date' => '2026-01-08',
            'type' => TransactionType::Savings->value,
            'amount' => 0,
            'status' => TransactionStatus::Realized->value,
        ], arguments: ['date' => '2026-01-08', 'type' => TransactionType::Savings->value])
        ->assertHasActionErrors(['amount']);

    expect(DailyTransaction::count())->toBe(0);
});

it('edits a movement from the detail panel', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $movement = DailyTransaction::create([
        'date' => '2026-09-05',
        'type' => TransactionType::Expense->value,
        'amount' => 150,
        'description' => 'Psicólogo',
    ]);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-05', 'type' => TransactionType::Expense->value])
        ->callAction('editMovement', data: [
            'date' => '2026-09-05',
            'type' => TransactionType::Expense->value,
            'amount' => 175,
            'description' => 'Psicólogo',
            'status' => TransactionStatus::Realized->value,
        ], arguments: ['movement' => $movement->id]);

    expect($movement->refresh()->amount)->toBe('175.00');
});

it('confirms and skips a pending movement from the detail panel', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $pending = DailyTransaction::factory()->pending()->create([
        'user_id' => $user->id,
        'date' => '2026-09-20',
    ]);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-20'])
        ->callAction('confirmMovement', arguments: ['movement' => $pending->id]);

    expect($pending->refresh()->status)->toBe(TransactionStatus::Realized);

    $other = DailyTransaction::factory()->pending()->create([
        'user_id' => $user->id,
        'date' => '2026-09-21',
    ]);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-21'])
        ->callAction('skipMovement', arguments: ['movement' => $other->id]);

    expect($other->refresh()->status)->toBe(TransactionStatus::Skipped);
});

it('keeps a skipped recurring occurrence out of the next generator run', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = AccountPlan::create([
        'type' => TransactionType::Expense,
        'description' => 'Assinatura diária',
        'expected_amount' => 49.67,
        'frequency' => 'daily',
        'starts_at' => '2026-09-10',
    ]);

    $occurrence = DailyTransaction::query()
        ->where('date', '2026-09-20')
        ->where('is_recurring', true)
        ->firstOrFail();

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-20'])
        ->callAction('skipMovement', arguments: ['movement' => $occurrence->id]);

    app(RecurrenceGenerator::class)->generate($plan);

    expect($occurrence->refresh()->status)->toBe(TransactionStatus::Skipped)
        ->and(DailyTransaction::where('date', '2026-09-20')->count())->toBe(1);
});

it('deletes a manual movement from the detail panel', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    $movement = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-09-05',
        'is_recurring' => false,
    ]);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-05'])
        ->callAction('deleteMovement', arguments: ['movement' => $movement->id]);

    expect(DailyTransaction::find($movement->id))->toBeNull();
});

it('never resolves another users movement', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $foreign = DailyTransaction::factory()->create([
        'user_id' => $other->id,
        'date' => '2026-09-05',
        'amount' => 999,
        'description' => 'Do outro usuário',
        'is_recurring' => false,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-05']);

    expect($component->instance()->detailRows)->toBe([]);

    $component
        ->assertMountedActionModalDontSee('Do outro usuário')
        ->assertActionHidden('editMovement', ['movement' => $foreign->id])
        ->assertActionHidden('confirmMovement', ['movement' => $foreign->id])
        ->assertActionHidden('skipMovement', ['movement' => $foreign->id])
        ->assertActionHidden('deleteMovement', ['movement' => $foreign->id]);

    expect($foreign->refresh()->amount)->toBe('999.00');
});

it('does not attach another users tag to a movement', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $foreignTag = Tag::create(['name' => 'Lazer']);

    $this->actingAs($user);

    Livewire::test(ThermometerPage::class)
        ->callAction('addMovement', data: [
            'date' => '2026-09-05',
            'type' => TransactionType::Expense->value,
            'amount' => 150,
            'status' => TransactionStatus::Realized->value,
            'tags' => [$foreignTag->id],
        ], arguments: ['date' => '2026-09-05', 'type' => TransactionType::Expense->value])
        ->assertHasActionErrors(['tags.0']);

    expect(DailyTransaction::count())->toBe(0);
});

it('colors the balance column by range', function (string $balance, string $color) {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => $balance, 'base_date' => '2026-09-01']);

    Livewire::test(ThermometerPage::class)
        ->assertSeeHtml('data-balance-color="'.$color.'"');
})->with([
    'well below -500' => ['-600.00', 'dark-red'],
    'one cent below -500' => ['-500.01', 'dark-red'],
    'exactly -500' => ['-500.00', 'dark-red'],
    'one cent above -500' => ['-499.99', 'light-red'],
    'exactly zero' => ['0.00', 'light-red'],
    'one cent above zero' => ['0.01', 'light-yellow'],
    'exactly 1000' => ['1000.00', 'light-yellow'],
    'one cent above 1000' => ['1000.01', 'light-green'],
    'exactly 2000' => ['2000.00', 'light-green'],
    'one cent above 2000' => ['2000.01', 'dark-green'],
    'well above 2000' => ['2500.00', 'dark-green'],
]);

it('colors balance cells outside the current month', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 2500, 'base_date' => '2026-09-01']);

    Livewire::test(ThermometerPage::class)
        ->set('month', 10)
        ->set('months', 1)
        ->assertSeeHtml('data-balance-color="dark-green"');
});

it('shows the daily column with real movements and the quick add button', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-10', 'type' => TransactionType::Daily->value, 'amount' => 30]);

    $component = Livewire::test(ThermometerPage::class);

    expect($component->instance()->columns)->toBe(TransactionType::cases());

    $component
        ->assertSee('Diários')
        ->assertSeeHtml('data-type="daily"')
        ->assertDontSeeHtml('data-type="forecast"')
        ->assertSeeHtml('<span class="thermometer-value">R$ 30,00</span>')
        ->assertSeeHtml("wire:click=\"mountAction('addMovement', { date: '2026-09-10', type: 'daily' })\"")
        ->assertSeeHtml("wire:click=\"mountAction('openCell', { date: '2026-09-10', type: 'daily' })\"");
});

it('projects the daily forecast on future days without a daily movement', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    Livewire::test(ThermometerPage::class)
        ->assertSeeHtml('thermometer-value thermometer-value-projection">R$ 10,00')
        ->assertSeeHtml("wire:click=\"mountAction('addMovement', { date: '2026-09-16', type: 'daily' })\"")
        ->assertSeeHtml("wire:click=\"mountAction('openCell', { date: '2026-09-16', type: 'daily' })\"");
});

it('shows the daily forecast summary with a link to its management page', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create(['forecast_divisor_days' => 30]);
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 1200]);

    Livewire::test(ThermometerPage::class)
        ->assertSee('Previsão de diário')
        ->assertSee('R$ 1.200,00')
        ->assertSee('R$ 40,00')
        ->assertSee('30 dias')
        ->assertSeeHtml('href="'.DailyForecastPage::getUrl().'"');
});

it('shows the daily forecast in the detail panel of a day without movements', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-16', 'type' => TransactionType::Daily->value])
        ->assertMountedActionModalSee('Previsão diária: R$ 10,00');
});

it('marks movements before the creation date as out of the calculation', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000]);

    $user->initialBalance()->firstOrFail()->forceFill(['created_at' => '2026-09-10 12:00:00'])->save();

    DailyTransaction::create(['date' => '2026-09-09', 'type' => TransactionType::Expense->value, 'amount' => 150, 'description' => 'Antes']);

    $component = Livewire::test(ThermometerPage::class)
        ->mountAction('openCell', ['date' => '2026-09-09', 'type' => TransactionType::Expense->value]);

    expect($component->instance()->detailRows[0]['counts'])->toBeFalse();

    $component->assertMountedActionModalSee('Fora do cálculo desta célula.');
});

it('shows the initial balance start in the thermometer legend', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-10']);

    Livewire::test(ThermometerPage::class)
        ->assertSee('Saldo inicial de R$ 1.000,00 conta a partir de 10/09/2026');
});

it('stays inside the render budget of the twelve month horizon', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create(['forecast_divisor_days' => 30]);
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 5000, 'base_date' => '2026-09-01']);

    DailyForecast::factory()->count(3)->create(['user_id' => $user->id]);

    $start = CarbonImmutable::parse('2026-09-01');

    foreach (range(0, 11) as $offset) {
        $month = $start->addMonthsNoOverflow($offset);

        DailyTransaction::factory()->create([
            'user_id' => $user->id,
            'date' => $month->setDay(5)->toDateString(),
            'type' => TransactionType::Income->value,
        ]);

        DailyTransaction::factory()->create([
            'user_id' => $user->id,
            'date' => $month->setDay(10)->toDateString(),
            'type' => TransactionType::Expense->value,
        ]);

        DayCheckIn::factory()->create([
            'user_id' => $user->id,
            'date' => $month->setDay(1)->toDateString(),
        ]);
    }

    DB::enableQueryLog();

    $response = $this->get('/thermometer');

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    $response->assertOk();

    expect($queries)->toBeLessThanOrEqual(25)
        ->and(strlen($response->getContent()))->toBeLessThanOrEqual(1_800_000);
});
