<?php

use Symfony\Component\Process\Process;

it('discovers tools and runs a safe query over stdio', function () {
    $database = tempnam(sys_get_temp_dir(), 'cash-compass-mcp-');
    $accountEmail = 'mcp-smoke@example.com';

    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $database,
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'CASH_COMPASS_MCP_ACCOUNT_EMAIL' => $accountEmail,
    ];

    $runArtisan = function (array $command, array $environment): void {
        $process = new Process([PHP_BINARY, 'artisan', ...$command], base_path(), $environment);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput().$process->getOutput());
        }
    };

    $runArtisan(['migrate', '--force', '--no-interaction'], $environment);
    $runArtisan([
        'tinker',
        '--execute',
        "\\App\\Models\\User::factory()->create(['email' => '{$accountEmail}', 'is_active' => true]);",
    ], $environment);

    $requests = [
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => new stdClass],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'get_initial_balance', 'arguments' => new stdClass]],
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'list_tags', 'arguments' => ['per_page' => 5]]],
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'create_transaction', 'arguments' => [
            'operation_key' => 'smoke-1',
            'date' => '2026-01-10',
            'type' => 'expense',
            'amount' => 12.5,
            'description' => 'Smoke',
        ]]],
        ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => ['name' => 'create_transaction', 'arguments' => [
            'operation_key' => 'smoke-1',
            'date' => '2026-01-10',
            'type' => 'expense',
            'amount' => 12.5,
            'description' => 'Smoke',
        ]]],
        ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => ['name' => 'set_day_check_in', 'arguments' => [
            'date' => '2020-01-01',
            'checked_in' => true,
        ]]],
        ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => ['name' => 'create_tag', 'arguments' => [
            'operation_key' => 'smoke-tag-1',
            'name' => 'Smoke',
            'color' => 'blue',
        ]]],
        ['jsonrpc' => '2.0', 'id' => 8, 'method' => 'tools/call', 'params' => ['name' => 'create_tag', 'arguments' => [
            'operation_key' => 'smoke-tag-1',
            'name' => 'Smoke',
            'color' => 'blue',
        ]]],
    ];

    $input = implode(PHP_EOL, array_map(
        fn (array $request): string => json_encode($request, JSON_THROW_ON_ERROR),
        $requests,
    )).PHP_EOL;

    $server = new Process([PHP_BINARY, 'artisan', 'mcp:start', 'cash-compass'], base_path(), $environment);
    $server->setInput($input);
    $server->setTimeout(120);
    $server->run();

    $responses = collect(explode(PHP_EOL, $server->getOutput()))
        ->map(fn (string $line): mixed => json_decode(trim($line), true))
        ->filter(fn (mixed $decoded): bool => is_array($decoded) && isset($decoded['id']))
        ->keyBy(fn (array $message): int => (int) $message['id']);

    @unlink($database);

    $toolNames = collect(data_get($responses->get(1), 'result.tools', []))->pluck('name');

    expect($responses->has(1))->toBeTrue()
        ->and($toolNames)->toContain('list_transactions')
        ->and($toolNames)->toContain('get_balance')
        ->and($toolNames)->toContain('create_transaction')
        ->and($toolNames)->toContain('delete_transaction')
        ->and($toolNames)->toContain('update_initial_balance')
        ->and($toolNames)->toContain('set_day_check_in')
        ->and($toolNames)->toContain('create_tag')
        ->and($toolNames)->toContain('delete_tag')
        ->and($toolNames)->toHaveCount(28)
        ->and(data_get($responses->get(2), 'result.structuredContent.has_balance'))->toBeFalse()
        ->and(data_get($responses->get(3), 'result.structuredContent.total'))->toBe(0)
        ->and(data_get($responses->get(4), 'result.structuredContent.transaction.amount'))->toBe('12.50')
        ->and(data_get($responses->get(5), 'result.structuredContent.transaction.id'))
        ->toBe(data_get($responses->get(4), 'result.structuredContent.transaction.id'))
        ->and(data_get($responses->get(6), 'result.structuredContent.checked_in'))->toBeTrue()
        ->and(data_get($responses->get(7), 'result.structuredContent.tag.name'))->toBe('Smoke')
        ->and(data_get($responses->get(8), 'result.structuredContent.tag.id'))
        ->toBe(data_get($responses->get(7), 'result.structuredContent.tag.id'));
});
