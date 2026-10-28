<?php

use App\Enums\TagColor;
use App\Enums\TransactionType;
use App\Filament\Pages\Reports;
use App\Filament\Widgets\TagBreakdownTable;
use App\Filament\Widgets\TopTagsChart;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * @param  array<int, Tag>  $tags
 */
function taggedSpending(User $user, string $date, string $amount, array $tags = []): DailyTransaction
{
    $transaction = DailyTransaction::factory()->for($user)->create([
        'date' => $date,
        'type' => TransactionType::Expense,
        'amount' => $amount,
    ]);

    if ($tags !== []) {
        $transaction->tags()->attach(array_map(fn (Tag $tag): int => (int) $tag->id, $tags));
    }

    return $transaction;
}

it('shows the full tag table with the required columns and no-tag row at the end', function () {
    $market = Tag::factory()->for($this->user)->create(['name' => 'Mercado', 'color' => TagColor::Green]);
    taggedSpending($this->user, '2026-10-10', '150.00', [$market]);
    taggedSpending($this->user, '2026-10-11', '50.00');

    Livewire::test(Reports::class)
        ->set('activeTab', 'tags')
        ->assertSee('Todas as tags')
        ->assertSee('Tag')
        ->assertSee('Total')
        ->assertSee('Participação')
        ->assertSee('Lançamentos')
        ->assertSee('Média')
        ->assertSee('Última data')
        ->assertSee('Mercado')
        ->assertSee('Sem tag')
        ->assertSee('R$ 50,00')
        ->assertSee('75,0%')
        ->assertSee('25,0%');
});

it('marks archived tags with a textual seal', function () {
    $archived = Tag::factory()->for($this->user)->archived()->create(['name' => 'Antiga']);
    taggedSpending($this->user, '2026-10-10', '80.00', [$archived]);

    Livewire::test(Reports::class)
        ->set('activeTab', 'tags')
        ->assertSee('Antiga')
        ->assertSee('(arquivada)');
});

it('shows the top ten tag chart and hides tags without spending', function () {
    $tags = [];

    for ($index = 1; $index <= 12; $index++) {
        $tags[$index] = Tag::factory()->for($this->user)->create([
            'name' => sprintf('Tag %02d', $index),
            'color' => TagColor::Blue,
        ]);
        taggedSpending($this->user, '2026-10-10', number_format(3000 - $index, 2, '.', ''), [$tags[$index]]);
    }

    Tag::factory()->for($this->user)->create(['name' => 'Sem gasto']);

    Livewire::test(TopTagsChart::class, ['pageFilters' => ['preset' => 'current_month']])
        ->assertSee('Tag 01')
        ->assertSee('Tag 10')
        ->assertDontSee('Tag 11')
        ->assertDontSee('Tag 12')
        ->assertDontSee('Sem gasto')
        ->assertSee('A soma das tags pode passar do total gasto');
});

it('shows the untagged total in the tags chart note', function () {
    $tag = Tag::factory()->for($this->user)->create(['name' => 'Mercado']);
    taggedSpending($this->user, '2026-10-10', '100.00', [$tag]);
    taggedSpending($this->user, '2026-10-11', '25.00');

    Livewire::test(TopTagsChart::class, ['pageFilters' => ['preset' => 'current_month']])
        ->assertSee('Sem tag: R$ 25,00');
});

it('paginates the tag table by twenty-five rows', function () {
    for ($index = 1; $index <= 30; $index++) {
        $tag = Tag::factory()->for($this->user)->create(['name' => sprintf('Tag %02d', $index)]);
        taggedSpending($this->user, '2026-10-10', number_format(3000 - $index, 2, '.', ''), [$tag]);
    }

    Livewire::test(TagBreakdownTable::class, ['pageFilters' => ['preset' => 'current_month']])
        ->assertSee('Tag 01')
        ->assertDontSee('Tag 26')
        ->call('gotoPage', 2, 'tagsPage')
        ->assertSee('Tag 26')
        ->assertSee('Tag 30')
        ->assertDontSee('Tag 01');
});

it('switches the tag table with the period filter', function () {
    $september = Tag::factory()->for($this->user)->create(['name' => 'Tag Setembro']);
    $october = Tag::factory()->for($this->user)->create(['name' => 'Tag Outubro']);
    taggedSpending($this->user, '2026-09-10', '50.00', [$september]);
    taggedSpending($this->user, '2026-10-10', '100.00', [$october]);

    Livewire::test(TagBreakdownTable::class, ['pageFilters' => ['preset' => 'current_month']])
        ->assertSee('Tag Outubro')
        ->assertDontSee('Tag Setembro');

    Livewire::test(TagBreakdownTable::class, ['pageFilters' => ['preset' => 'last_month']])
        ->assertSee('Tag Setembro')
        ->assertDontSee('Tag Outubro');

    Livewire::test(Reports::class)
        ->set('activeTab', 'tags')
        ->set('filters.preset', 'last_month')
        ->assertSet('filters.preset', 'last_month')
        ->assertSee('Setembro de 2026');
});

it('isolates the tag report by user', function () {
    $other = User::factory()->create();
    $otherTag = Tag::factory()->for($other)->create(['name' => 'Tag de Outro']);
    taggedSpending($other, '2026-10-10', '999.00', [$otherTag]);

    Livewire::test(Reports::class)
        ->set('activeTab', 'tags')
        ->assertOk()
        ->assertDontSee('Tag de Outro');
});
