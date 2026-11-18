<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Resources\DailyTransactions\Pages\ManageDailyTransactions;
use App\Models\AccountPlan;
use App\Models\CardItem;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

it('lets users create a manual transaction from the panel', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Income->value,
            'amount' => 1000,
            'description' => 'Freelance',
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasNoActionErrors();

    $transaction = DailyTransaction::firstOrFail();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->amount)->toBe('1000.00')
        ->and($transaction->is_recurring)->toBeFalse()
        ->and($transaction->status)->toBe(TransactionStatus::Realized);
});

it('creates a daily transaction from the panel', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Daily->value,
            'amount' => 45,
            'description' => 'Café da rua',
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasNoActionErrors();

    $transaction = DailyTransaction::firstOrFail();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->type)->toBe(TransactionType::Daily)
        ->and($transaction->amount)->toBe('45.00');
});

it('creates a transaction with tags', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Saúde']);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-05',
            'type' => TransactionType::Expense->value,
            'amount' => 150,
            'description' => 'Psicólogo',
            'status' => TransactionStatus::Realized->value,
            'tags' => [$tag->id],
        ])
        ->assertHasNoActionErrors();

    expect(DailyTransaction::firstOrFail()->tags->pluck('name')->all())->toBe(['Saúde']);
});

it('creates a tag with a color from the transaction selector', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'Academia',
            'color' => 'purple',
        ]);

    expect(Tag::firstOrFail()->name)->toBe('Academia')
        ->and(Tag::firstOrFail()->color->value)->toBe('purple');
});

it('reuses an active tag when quick creation receives an equivalent name', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Academia', 'color' => 'blue']);

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => '  academia ',
            'color' => 'purple',
        ]);

    expect(Tag::count())->toBe(1)
        ->and($tag->refresh()->color->value)->toBe('blue');
});

it('uses the neutral color by default when quickly creating a tag', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'Academia',
        ]);

    expect(Tag::firstOrFail()->color->value)->toBe('neutral');
});

it('rejects quick creation when the equivalent tag is archived', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'academia',
            'color' => 'purple',
        ])
        ->assertHasActionErrors();
});

it('does not attach another users tag to a transaction', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $foreign = Tag::create(['name' => 'Lazer']);

    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-05',
            'type' => TransactionType::Expense->value,
            'amount' => 150,
            'status' => TransactionStatus::Realized->value,
            'tags' => [$foreign->id],
        ])
        ->assertHasActionErrors(['tags.0']);

    expect(DailyTransaction::count())->toBe(0);
});

it('shows up to two colored tags and an overflow count in the transaction table', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transaction = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'description' => 'Compra',
    ]);
    $first = Tag::create(['name' => 'Casa', 'color' => 'blue']);
    $second = Tag::create(['name' => 'Lazer', 'color' => 'purple']);
    $third = Tag::create(['name' => 'Saúde', 'color' => 'teal']);
    $transaction->tags()->attach([$first->id, $second->id, $third->id]);

    Livewire::test(ManageDailyTransactions::class)
        ->assertSee('Casa')
        ->assertSee('Lazer')
        ->assertSee('+1')
        ->assertSeeHtml('fi-color-blue')
        ->assertSeeHtml('fi-color-purple')
        ->assertDontSee('fi-color-teal');
});

it('rejects a non-positive amount', function (float $amount) {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Income->value,
            'amount' => $amount,
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasActionErrors(['amount']);

    expect(DailyTransaction::count())->toBe(0);
})->with([0, -10]);

it('shows an archived linked tag while editing a transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();
    $transaction = DailyTransaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach($tag);

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction(TestAction::make('edit')->table($transaction))
        ->assertMountedActionModalSee('Academia')
        ->assertMountedActionModalSee('arquivada');
});

it('does not attach an archived tag to a new transaction', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-05',
            'type' => TransactionType::Expense->value,
            'amount' => 150,
            'status' => TransactionStatus::Realized->value,
            'tags' => [$tag->id],
        ])
        ->assertHasActionErrors(['tags.0']);

    expect(DailyTransaction::count())->toBe(0);
});

it('preserves an archived linked tag when editing another transaction field', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();
    $transaction = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'description' => 'Original',
    ]);
    $transaction->tags()->attach($tag);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('edit')->table($transaction), data: [
            'date' => $transaction->date->toDateString(),
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'description' => 'Atualizado',
            'status' => $transaction->status->value,
            'tags' => [$tag->id],
        ])
        ->assertHasNoActionErrors();

    expect($transaction->refresh()->description)->toBe('Atualizado')
        ->and($transaction->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('allows several manual transactions on the same day alongside a generated one', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    AccountPlan::create([
        'type' => TransactionType::Income,
        'description' => 'Salário',
        'expected_amount' => 5000,
        'frequency' => RecurrenceFrequency::Monthly,
        'day_of_month' => 5,
        'starts_at' => '2026-01-01',
    ]);

    foreach ([TransactionType::Income, TransactionType::Expense] as $type) {
        Livewire::test(ManageDailyTransactions::class)
            ->callAction('create', data: [
                'date' => '2026-01-05',
                'type' => $type->value,
                'amount' => 100,
                'status' => TransactionStatus::Realized->value,
            ])
            ->assertHasNoActionErrors();
    }

    $day = DailyTransaction::where('date', '2026-01-05')->get();

    expect($day)->toHaveCount(3)
        ->and($day->where('is_recurring', true))->toHaveCount(1)
        ->and($day->where('is_recurring', false))->toHaveCount(2);
});

it('only lists the transactions of the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $otherTransaction = DailyTransaction::factory()->create(['date' => '2026-01-02']);

    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->assertCanNotSeeTableRecords([$otherTransaction]);
});

it('confirms a pending transaction early', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    $pending = DailyTransaction::factory()->pending()->create([
        'user_id' => $user->id,
        'date' => '2026-01-10',
    ]);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('confirm')->table($pending));

    expect($pending->refresh()->status)->toBe(TransactionStatus::Realized);
});

it('skips a pending transaction without deleting it', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    $pending = DailyTransaction::factory()->pending()->create([
        'user_id' => $user->id,
        'date' => '2026-01-10',
    ]);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('skip')->table($pending));

    expect($pending->refresh()->status)->toBe(TransactionStatus::Skipped)
        ->and($pending->exists)->toBeTrue();
});

it('deletes a transaction', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    $realized = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-01-10',
    ]);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('delete')->table($realized));

    expect(DailyTransaction::find($realized->id))->toBeNull();
});

it('forbids admins from the transactions panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/daily-transactions')->assertForbidden();
});

it('creates a card with items that close exactly with the amount', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 100,
            'description' => 'Compra no cartão',
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['description' => 'Mercado', 'amount' => 60],
                ['description' => 'Farmácia', 'amount' => 40],
            ],
        ])
        ->assertHasNoActionErrors();

    $transaction = DailyTransaction::firstOrFail();

    expect($transaction->cardItems()->pluck('description')->all())->toBe(['Mercado', 'Farmácia'])
        ->and((float) $transaction->cardItems()->sum('amount'))->toBe(100.0);
});

it('creates a card without any item', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 100,
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasNoActionErrors();

    expect(DailyTransaction::firstOrFail()->cardItems()->count())->toBe(0);
});

it('blocks a card whose items do not close and shows the difference', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 100,
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['description' => 'Mercado', 'amount' => 60],
            ],
        ]);

    $component->assertHasActionErrors(['card_items']);

    expect($component->errors()->get('mountedActions.0.data.card_items')[0])
        ->toContain('Diferença: R$ 40,00')
        ->and(DailyTransaction::count())->toBe(0);
});

it('blocks changing the amount of a detailed card without adjusting the items', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-02',
        'amount' => '100.00',
    ]);
    CardItem::factory()->forTransaction($card)->create(['description' => 'Mercado', 'amount' => '100.00']);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('edit')->table($card), data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 150,
            'description' => 'Compra no cartão',
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['description' => 'Mercado', 'amount' => 100],
            ],
        ])
        ->assertHasActionErrors(['card_items']);

    expect($card->refresh()->amount)->toBe('100.00');
});

it('does not clear newly added items when saving an older edit modal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $card = DailyTransaction::factory()->for($user)->card()->create(['amount' => '100.00']);
    $modal = Livewire::test(ManageDailyTransactions::class)
        ->mountAction(TestAction::make('edit')->table($card));

    $item = CardItem::factory()->forTransaction($card)->create(['amount' => '100.00']);

    $modal->fillForm(['description' => 'Edição antiga'])
        ->callMountedAction()
        ->assertHasActionErrors(['card_items']);

    expect($item->fresh())->not->toBeNull()
        ->and($card->refresh()->description)->not->toBe('Edição antiga');
});

it('updates a detailed card when the items are adjusted in the same save', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-02',
        'amount' => '100.00',
    ]);
    $item = CardItem::factory()->forTransaction($card)->create(['description' => 'Mercado', 'amount' => '100.00']);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('edit')->table($card), data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 150,
            'description' => 'Compra no cartão',
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['id' => $item->id, 'description' => 'Mercado', 'amount' => 90],
                ['description' => 'Farmácia', 'amount' => 60],
            ],
        ])
        ->assertHasNoActionErrors();

    expect($card->refresh()->amount)->toBe('150.00')
        ->and($card->cardItems()->pluck('description')->all())->toBe(['Mercado', 'Farmácia']);
});

it('blocks leaving the card type while items remain', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-02',
        'amount' => '100.00',
    ]);
    CardItem::factory()->forTransaction($card)->create(['description' => 'Mercado', 'amount' => '100.00']);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('edit')->table($card), data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Expense->value,
            'amount' => 100,
            'description' => 'Compra no cartão',
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['description' => 'Mercado', 'amount' => 100],
            ],
        ])
        ->assertHasActionErrors(['card_items']);

    expect($card->refresh()->type)->toBe(TransactionType::Card)
        ->and($card->cardItems()->count())->toBe(1);
});

it('allows removing every item and leaving the card type in the same save', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-02',
        'amount' => '100.00',
    ]);
    CardItem::factory()->forTransaction($card)->create(['description' => 'Mercado', 'amount' => '100.00']);

    $component = Livewire::test(ManageDailyTransactions::class)
        ->mountAction(TestAction::make('edit')->table($card))
        ->set('mountedActions.0.data.type', TransactionType::Expense->value)
        ->assertSchemaComponentVisible('card_items', 'mountedActionSchema0');

    $itemKey = array_key_first($component->get('mountedActions.0.data.card_items'));

    $component
        ->callAction(TestAction::make('delete')->schemaComponent('card_items')->arguments(['item' => $itemKey]))
        ->assertSchemaComponentHidden('card_items', 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($card->refresh()->type)->toBe(TransactionType::Expense)
        ->and($card->cardItems()->count())->toBe(0);
});

it('shows the item count of a card in the transactions table', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-02',
        'amount' => '100.00',
        'description' => 'Compra no cartão',
    ]);
    CardItem::factory()->forTransaction($card)->create(['description' => 'Mercado', 'amount' => '40.00']);
    CardItem::factory()->forTransaction($card)->create(['description' => 'Farmácia', 'amount' => '60.00']);

    Livewire::test(ManageDailyTransactions::class)
        ->assertCanSeeTableRecords([$card])
        ->assertTableColumnStateSet('card_items_count', 2, $card);
});

it('attaches tags to each card item', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $market = Tag::create(['name' => 'Mercado', 'color' => 'green']);
    $health = Tag::create(['name' => 'Saúde', 'color' => 'teal']);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 100,
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['description' => 'Feira', 'amount' => 60, 'tag_ids' => [$market->id]],
                ['description' => 'Farmácia', 'amount' => 40, 'tag_ids' => [$health->id]],
            ],
        ])
        ->assertHasNoActionErrors();

    $items = DailyTransaction::firstOrFail()->cardItems()->with('tags')->get();

    expect($items[0]->tags->pluck('name')->all())->toBe(['Mercado'])
        ->and($items[1]->tags->pluck('name')->all())->toBe(['Saúde']);
});

it('keeps an archived tag already linked to a card item when editing', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $archived = Tag::factory()->archived()->for($user)->create(['name' => 'Antiga']);
    $card = DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-02',
        'amount' => '100.00',
    ]);
    $item = CardItem::factory()->forTransaction($card)->create(['description' => 'Feira', 'amount' => '100.00']);
    $item->tags()->attach($archived);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('edit')->table($card), data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 100,
            'description' => 'Atualizado',
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['id' => $item->id, 'description' => 'Feira', 'amount' => 100, 'tag_ids' => [$archived->id]],
            ],
        ])
        ->assertHasNoActionErrors();

    expect($item->refresh()->tags->pluck('id')->all())->toBe([$archived->id]);
});

it('rejects a tag of another user on a card item', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $foreign = Tag::create(['name' => 'Lazer']);
    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Card->value,
            'amount' => 100,
            'status' => TransactionStatus::Realized->value,
            'card_items' => [
                ['description' => 'Feira', 'amount' => 100, 'tag_ids' => [$foreign->id]],
            ],
        ])
        ->assertHasActionErrors();

    expect(DailyTransaction::count())->toBe(0);
});

it('creates a tag quickly from the card item selector and escapes its labels', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->setActionData([
            'type' => TransactionType::Card->value,
            'card_items' => [['description' => 'Feira', 'amount' => 10]],
        ])
        ->call('$refresh')
        ->callAction(TestAction::make('createOption')->schemaComponent('card_items.0.tag_ids'), data: [
            'name' => '<b>Feira & mercado</b>',
            'color' => 'green',
        ]);

    $tag = Tag::firstOrFail();

    expect($tag->name)->toBe('<b>Feira & mercado</b>')
        ->and($tag->color->value)->toBe('green');

    $component->assertSchemaComponentExists(
        'card_items.0.tag_ids',
        'mountedActionSchema0',
        function (Select $select) use ($tag): bool {
            expect($select->getOptions()[$tag->id])->toBe('&lt;b&gt;Feira &amp; mercado&lt;/b&gt;')
                ->and((string) $select->getOptionLabels()[$tag->id])->toContain('&lt;b&gt;Feira &amp; mercado&lt;/b&gt;')
                ->not->toContain($tag->name);

            return true;
        },
    );
});

it('rejects quick creation from an item when the equivalent tag is archived', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Feira']);
    $tag->archive();

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->setActionData([
            'type' => TransactionType::Card->value,
            'card_items' => [['description' => 'Feira', 'amount' => 10]],
        ])
        ->call('$refresh')
        ->callAction(TestAction::make('createOption')->schemaComponent('card_items.0.tag_ids'), data: [
            'name' => 'feira',
            'color' => 'green',
        ])
        ->assertHasActionErrors();

    expect(Tag::count())->toBe(1);
});

it('details a pending recurring card occurrence from the panel', function () {
    $this->travelTo('2026-01-10');
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = AccountPlan::factory()->for($user)->card()->create([
        'description' => 'Assinaturas',
        'expected_amount' => '100.00',
        'starts_at' => '2026-01-10',
        'day_of_month' => 10,
    ]);
    $pending = $plan->dailyTransactions()->where('date', '2026-01-10')->firstOrFail();

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('edit')->table($pending), data: [
            'date' => '2026-01-10',
            'type' => TransactionType::Card->value,
            'amount' => 100,
            'description' => 'Assinaturas',
            'status' => TransactionStatus::Pending->value,
            'card_items' => [
                ['description' => 'Mercado', 'amount' => 60],
                ['description' => 'Farmácia', 'amount' => 40],
            ],
        ])
        ->assertHasNoActionErrors();

    expect($pending->refresh()->cardItems()->pluck('description')->all())->toBe(['Mercado', 'Farmácia']);
});

it('shows the item sum and the closing difference in the card form', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $card = DailyTransaction::factory()->for($user)->card()->create([
        'date' => '2026-01-02',
        'amount' => '100.00',
    ]);
    CardItem::factory()->forTransaction($card)->create(['description' => 'Mercado', 'amount' => '60.00']);
    CardItem::factory()->forTransaction($card)->create(['description' => 'Farmácia', 'amount' => '40.00']);

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction(TestAction::make('edit')->table($card))
        ->assertMountedActionModalSee('Soma dos itens: R$ 100,00')
        ->assertMountedActionModalSee('Diferença para o valor do lançamento: R$ 0,00');
});
