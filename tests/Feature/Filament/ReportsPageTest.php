<?php

use App\Enums\TagColor;
use App\Enums\TransactionType;
use App\Filament\Pages\Reports;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
});

it('lets non-admin users open the reports page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/reports')->assertOk();
});

it('forbids administrators from the reports page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/reports')->assertForbidden();
});

it('registers reports in the navigation after transactions and tags', function () {
    $this->actingAs(User::factory()->create());

    $urls = collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getUrl())
        ->values();

    expect($urls)->toContain(url('/reports'))
        ->and($urls->search(url('/reports')))->toBeGreaterThan($urls->search(url('/tags')));
});

it('opens on the current month with the behavior tab active', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Reports::class)
        ->assertSet('activeTab', 'behavior')
        ->assertSee('Horário do lançamento')
        ->assertSee('Outubro de 2026');
});

it('keeps the selected period when switching tabs', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Reports::class)
        ->set('filters.preset', 'last_month')
        ->set('activeTab', 'tags')
        ->assertSet('filters.preset', 'last_month')
        ->assertSee('Setembro de 2026')
        ->assertSee('Todas as tags');
});

it('reflects the selected period in the url', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/reports?filters[preset]=last_month')
        ->assertOk()
        ->assertSee('Setembro de 2026');
});

it('accepts a custom period and shows its range', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Reports::class)
        ->set('filters.preset', 'custom')
        ->set('filters.start_date', '2026-10-05')
        ->set('filters.end_date', '2026-10-20')
        ->assertHasNoErrors()
        ->assertSee('05/10/2026 a 20/10/2026');
});

it('rejects an inverted custom period loaded from the url', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/reports?filters[preset]=custom&filters[start_date]=2026-10-20&filters[end_date]=2026-10-05')
        ->assertOk()
        ->assertSee('A data inicial deve ser anterior ou igual à data final.');
});

it('rejects a custom period whose start date is after the end date', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Reports::class)
        ->set('filters.preset', 'custom')
        ->set('filters.start_date', '2026-10-20')
        ->set('filters.end_date', '2026-10-05')
        ->assertHasErrors('filters.start_date');
});

it('shows the period indicators and the largest transactions', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'Mercado', 'color' => TagColor::Green]);
    $this->actingAs($user);

    $transaction = DailyTransaction::factory()->for($user)->create([
        'date' => '2026-10-10',
        'type' => TransactionType::Expense,
        'amount' => '150.00',
    ]);
    $transaction->tags()->attach($tag);

    Livewire::test(Reports::class)
        ->assertSee('Total gasto')
        ->assertSee('R$ 150,00')
        ->assertSee('Média por dia ativo')
        ->assertSee('Tag líder')
        ->assertSee('Mercado')
        ->assertSee('Cinco maiores lançamentos')
        ->assertSee('R$ 150,00');
});

it('renders an empty report without errors', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Reports::class)
        ->assertOk()
        ->assertSee('Horário do lançamento')
        ->assertSee('Total gasto');
});
