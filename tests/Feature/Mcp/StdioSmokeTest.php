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
        ->and($toolNames)->toHaveCount(12)
        ->and(data_get($responses->get(2), 'result.structuredContent.has_balance'))->toBeFalse()
        ->and(data_get($responses->get(3), 'result.structuredContent.total'))->toBe(0);
});
