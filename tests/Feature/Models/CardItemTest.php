<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AccountPlan;
use App\Models\CardItem;
use App\Models\DailyTransaction;
use App\Models\User;
use App\Services\CardItemSet;
use Illuminate\Validation\ValidationException;

function cardFor(User $user, string $amount = '100.00'): DailyTransaction
{
    return DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-10',
        'amount' => $amount,
        'status' => TransactionStatus::Realized,
    ]);
}

it('stores an item set that closes exactly with the parent amount', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');

    app(CardItemSet::class)->sync($card, [
        ['description' => 'Mercado', 'amount' => 60],
        ['description' => 'Farmácia', 'amount' => '40.00'],
    ]);

    $items = $card->cardItems()->get();

    expect($items)->toHaveCount(2)
        ->and($items->pluck('description')->all())->toBe(['Mercado', 'Farmácia'])
        ->and($items->pluck('amount')->all())->toBe(['60.00', '40.00'])
        ->and($items->every(fn (CardItem $item): bool => $item->user_id === $user->id))->toBeTrue();
});

it('compares the closing in cents instead of floats', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '0.30');

    app(CardItemSet::class)->sync($card, [
        ['description' => 'A', 'amount' => '0.10'],
        ['description' => 'B', 'amount' => '0.20'],
    ]);

    expect($card->cardItems()->count())->toBe(2);
});

it('blocks a set that does not close and reports the difference', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');

    expect(fn () => app(CardItemSet::class)->sync($card, [
        ['description' => 'Mercado', 'amount' => '60.00'],
    ]))->toThrow(ValidationException::class, 'Diferença: R$ 40,00');
});

it('reports a negative difference when the items overshoot the parent', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');

    expect(fn () => app(CardItemSet::class)->sync($card, [
        ['description' => 'Mercado', 'amount' => '130.00'],
    ]))->toThrow(ValidationException::class, 'Diferença: -R$ 30,00');
});

it('allows an empty set and removes the stored items', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');
    CardItem::factory()->forTransaction($card)->create(['amount' => '100.00']);

    app(CardItemSet::class)->sync($card, []);

    expect($card->cardItems()->count())->toBe(0);
});

it('blocks leaving the card type while items remain', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');
    CardItem::factory()->forTransaction($card)->create(['amount' => '100.00']);

    expect(fn () => $card->update(['type' => TransactionType::Expense]))
        ->toThrow(ValidationException::class, 'Remova os itens antes de trocar o tipo');
});

it('blocks changing the parent amount away from the item sum', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');
    CardItem::factory()->forTransaction($card)->create(['amount' => '100.00']);

    expect(fn () => $card->update(['amount' => '150.00']))
        ->toThrow(ValidationException::class, 'Ajuste os itens antes de mudar o valor');

    $card->refresh();

    expect($card->amount)->toBe('100.00');
});

it('allows removing every item and leaving the card type in the same save', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');
    CardItem::factory()->forTransaction($card)->create(['amount' => '100.00']);

    app(CardItemSet::class)->deleteAll($card);
    $card->update(['type' => TransactionType::Expense]);

    expect($card->refresh()->type)->toBe(TransactionType::Expense)
        ->and($card->cardItems()->count())->toBe(0);
});

it('deletes the items in cascade with the parent transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');
    CardItem::factory()->forTransaction($card)->create(['amount' => '100.00']);

    $card->delete();

    expect(CardItem::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses to bind an item to a transaction of another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $card = cardFor($owner, '100.00');
    $item = CardItem::factory()->forTransaction($card)->make(['amount' => '100.00']);

    $this->actingAs($intruder);

    expect(fn () => $item->save())->toThrow(InvalidArgumentException::class);
});

it('refuses to bind an item to a transaction outside the card type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $expense = DailyTransaction::factory()->for($user)->create([
        'date' => '2026-01-10',
        'type' => TransactionType::Expense,
        'amount' => '100.00',
    ]);

    expect(fn () => CardItem::factory()->forTransaction($expense)->create(['amount' => '100.00']))
        ->toThrow(InvalidArgumentException::class, 'Itens só podem detalhar lançamentos do tipo Cartão.');
});

it('refuses an item without a description or with a non positive amount', function (array $attributes) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = cardFor($user, '100.00');

    expect(fn () => CardItem::factory()->forTransaction($card)->create($attributes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty description' => [['description' => '  ', 'amount' => '10.00']],
    'zero amount' => [['description' => 'Mercado', 'amount' => '0']],
    'negative amount' => [['description' => 'Mercado', 'amount' => '-5.00']],
]);

it('details a pending recurring occurrence generated by a plan', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = AccountPlan::factory()->for($user)->card()->create();
    $pending = DailyTransaction::factory()->for($user)->card()->pending()->create([
        'date' => '2026-01-10',
        'amount' => '100.00',
        'account_plan_id' => $plan->id,
    ]);

    app(CardItemSet::class)->sync($pending, [
        ['description' => 'Mercado', 'amount' => '100.00'],
    ]);

    expect($pending->cardItems()->pluck('description')->all())->toBe(['Mercado']);
});
