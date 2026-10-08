<?php

use App\Enums\TagColor;
use App\Enums\TransactionType;
use App\Mcp\Servers\CashCompassServer;
use App\Mcp\Tools\ArchiveTagTool;
use App\Mcp\Tools\CreateTagTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\DeleteTagTool;
use App\Mcp\Tools\ReactivateTagTool;
use App\Mcp\Tools\UpdateTagTool;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\McpMutationAudit;
use App\Models\McpOperation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;

it('creates a tag with a normalized name and a palette color', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => '  Casa   da   Praia ',
        'color' => TagColor::Blue->value,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('tag.name', 'Casa da Praia')
            ->where('tag.color', TagColor::Blue->value)
            ->where('tag.is_active', true)
            ->etc());

    $tag = Tag::query()->forUser($account)->sole();

    expect($tag->name)->toBe('Casa da Praia')
        ->and($tag->normalized_name)->toBe('casa da praia')
        ->and($tag->color)->toBe(TagColor::Blue)
        ->and(McpMutationAudit::query()->where('tool', 'create_tag')->where('result', 'created')->count())->toBe(1);
});

it('defaults a created tag to the neutral color', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => 'Casa',
    ])->assertOk();

    expect(Tag::query()->forUser($account)->sole()->color)->toBe(TagColor::Neutral);
});

it('rejects a color outside the palette', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => 'Casa',
        'color' => 'rainbow',
    ])->assertHasErrors(['color']);

    expect(Tag::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
});

it('rejects a normalized name already used by an archived tag', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    Tag::factory()->archived()->for($account)->create(['name' => 'Mercado']);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => '  mercado ',
    ])->assertHasErrors(['Já existe uma tag']);

    expect(Tag::query()->count())->toBe(1);
});

it('replays the previous result for the same tag operation key and arguments', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $arguments = [
        'operation_key' => 'tag-1',
        'name' => 'Casa',
        'color' => TagColor::Green->value,
    ];

    CashCompassServer::tool(CreateTagTool::class, $arguments)->assertOk();
    $firstId = (int) Tag::query()->forUser($account)->sole()->getKey();

    CashCompassServer::tool(CreateTagTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('tag.id', $firstId)
            ->etc());

    expect(Tag::query()->count())->toBe(1)
        ->and(McpOperation::query()->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('result', 'replayed')->count())->toBe(1);
});

it('rejects the same tag operation key with different arguments', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => 'Casa',
    ])->assertOk();

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => 'Lazer',
    ])->assertHasErrors(['chave de operação']);

    expect(Tag::query()->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('result', 'conflict')->count())->toBe(1);
});

it('scopes the tag operation key to the account', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $arguments = ['operation_key' => 'shared', 'name' => 'Casa'];

    useMcpAccount($first);
    CashCompassServer::tool(CreateTagTool::class, $arguments)->assertOk();

    useMcpAccount($second);
    CashCompassServer::tool(CreateTagTool::class, $arguments)->assertOk();

    expect(Tag::query()->count())->toBe(2)
        ->and(McpOperation::query()->count())->toBe(2);
});

it('enforces tag uniqueness at the database level including archived tags', function () {
    $account = User::factory()->create();

    Tag::factory()->archived()->for($account)->create(['name' => 'Mercado']);

    Tag::factory()->for($account)->create(['name' => '  mercado ']);
})->throws(UniqueConstraintViolationException::class);

it('updates the name and color of a tag', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create(['name' => 'casa']);

    CashCompassServer::tool(UpdateTagTool::class, [
        'id' => $tag->getKey(),
        'name' => ' Casa   Nova ',
        'color' => TagColor::Purple->value,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('tag.name', 'Casa Nova')
            ->where('tag.color', TagColor::Purple->value)
            ->etc());

    $tag->refresh();

    expect($tag->name)->toBe('Casa Nova')
        ->and($tag->normalized_name)->toBe('casa nova')
        ->and($tag->color)->toBe(TagColor::Purple)
        ->and(McpMutationAudit::query()->where('tool', 'update_tag')->where('result', 'updated')->count())->toBe(1);
});

it('rejects an update name colliding with another tag', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    Tag::factory()->for($account)->create(['name' => 'Casa']);
    $target = Tag::factory()->for($account)->create(['name' => 'Lazer']);

    CashCompassServer::tool(UpdateTagTool::class, [
        'id' => $target->getKey(),
        'name' => ' casa ',
    ])->assertHasErrors(['Já existe uma tag']);

    expect($target->refresh()->name)->toBe('Lazer');
});

it('requires at least one field to update a tag', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create();

    CashCompassServer::tool(UpdateTagTool::class, ['id' => $tag->getKey()])
        ->assertHasErrors(['ao menos um campo']);
});

it('does not update another account tag', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = Tag::factory()->for($other)->create(['name' => 'Casa']);

    CashCompassServer::tool(UpdateTagTool::class, [
        'id' => $foreign->getKey(),
        'name' => 'Invadida',
    ])->assertHasErrors(['não encontrada']);

    expect($foreign->refresh()->name)->toBe('Casa');
});

it('archives a tag and preserves its links', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create(['name' => 'Casa']);
    $transaction = DailyTransaction::factory()->for($account)->create();
    $transaction->tags()->attach($tag);

    CashCompassServer::tool(ArchiveTagTool::class, ['id' => $tag->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('tag.is_active', false)
            ->etc());

    $tag->refresh();

    expect($tag->is_active)->toBeFalse()
        ->and($tag->dailyTransactions()->withoutGlobalScope('user')->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('tool', 'archive_tag')->where('result', 'archived')->count())->toBe(1);
});

it('reactivates an archived tag', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->archived()->for($account)->create();

    CashCompassServer::tool(ReactivateTagTool::class, ['id' => $tag->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('tag.is_active', true)
            ->etc());

    expect($tag->refresh()->is_active)->toBeTrue()
        ->and(McpMutationAudit::query()->where('tool', 'reactivate_tag')->where('result', 'reactivated')->count())->toBe(1);
});

it('deletes a tag without links', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create();

    CashCompassServer::tool(DeleteTagTool::class, ['id' => $tag->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('deleted', true)
            ->where('id', (int) $tag->getKey())
            ->etc());

    expect(Tag::query()->find($tag->getKey()))->toBeNull()
        ->and(McpMutationAudit::query()->where('tool', 'delete_tag')->where('result', 'deleted')->count())->toBe(1);
});

it('blocks deleting a tag linked to a transaction', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create();
    $transaction = DailyTransaction::factory()->for($account)->create();
    $transaction->tags()->attach($tag);

    CashCompassServer::tool(DeleteTagTool::class, ['id' => $tag->getKey()])
        ->assertHasErrors(['vinculada']);

    expect($tag->refresh()->exists)->toBeTrue();
});

it('blocks deleting a tag linked to an account plan', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create();
    $plan = AccountPlan::factory()->for($account)->create();
    $plan->tags()->attach($tag);

    CashCompassServer::tool(DeleteTagTool::class, ['id' => $tag->getKey()])
        ->assertHasErrors(['vinculada']);

    expect($tag->refresh()->exists)->toBeTrue();
});

it('does not delete another account tag', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = Tag::factory()->for($other)->create();

    CashCompassServer::tool(DeleteTagTool::class, ['id' => $foreign->getKey()])
        ->assertHasErrors(['não encontrada']);

    expect($foreign->refresh()->exists)->toBeTrue();
});

it('marks the delete tag tool as destructive', function () {
    expect((new DeleteTagTool)->annotations())
        ->toHaveKey('destructiveHint', true);
});

it('rejects identity arguments on tag tools', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => 'Casa',
        'user_id' => 999,
    ])->assertHasErrors(['identidade']);
});

it('keeps only minimal information in the tag mutation history', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => 'Segredo',
        'color' => TagColor::Red->value,
    ])->assertOk();

    $columns = Schema::getColumnListing('mcp_mutation_audits');
    $audit = McpMutationAudit::query()->sole();

    expect($columns)->not->toContain('name')
        ->and($columns)->not->toContain('color')
        ->and($audit->result)->toBe('created')
        ->and($audit->auditable_type)->toBe(Tag::class)
        ->and($audit->auditable_id)->not->toBeNull();
});

it('links a tag created through the MCP to a transaction', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTagTool::class, [
        'operation_key' => 'tag-1',
        'name' => 'Casa',
        'color' => TagColor::Blue->value,
    ])->assertOk();

    $tagId = (int) Tag::query()->forUser($account)->sole()->getKey();

    CashCompassServer::tool(CreateTransactionTool::class, [
        'operation_key' => 'tx-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 30,
        'tags' => [$tagId],
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('transaction.tags.0.id', $tagId)
            ->where('transaction.tags.0.name', 'Casa')
            ->etc());

    expect(DailyTransaction::query()->forUser($account)->sole()->tags->pluck('id')->all())->toBe([$tagId]);
});
