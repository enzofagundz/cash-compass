<?php

use Symfony\Component\Process\Process;

/**
 * Ambiente isolado para servidores MCP reais em processos separados, usando um
 * banco SQLite temporário (nunca o banco de finanças existente).
 *
 * @return array<string, string>
 */
function hermesMcpEnvironment(string $database, string $accountEmail): array
{
    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $database,
        'DB_BUSY_TIMEOUT' => '15000',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'CASH_COMPASS_MCP_ACCOUNT_EMAIL' => $accountEmail,
    ];
}

function hermesMcpRunArtisan(array $command, array $environment): void
{
    $process = new Process([PHP_BINARY, 'artisan', ...$command], base_path(), $environment);
    $process->setTimeout(120);
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    }
}

function hermesMcpSeedAccount(string $accountEmail, array $environment): void
{
    hermesMcpRunArtisan(['migrate', '--force', '--no-interaction'], $environment);
    hermesMcpRunArtisan([
        'tinker',
        '--execute',
        "\\App\\Models\\User::factory()->create(['email' => '{$accountEmail}', 'is_active' => true]);",
    ], $environment);
}

function hermesMcpCreateTransactionRequest(string $operationKey, string $date): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'create_transaction',
            'arguments' => [
                'operation_key' => $operationKey,
                'date' => $date,
                'type' => 'expense',
                'amount' => 10,
                'description' => 'Concorrência',
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

function hermesMcpStartServer(string $request, array $environment): Process
{
    $process = new Process([PHP_BINARY, 'artisan', 'mcp:start', 'cash-compass'], base_path(), $environment);
    $process->setInput($request.PHP_EOL);
    $process->setTimeout(120);
    $process->start();

    return $process;
}

function hermesMcpResponseTransactionId(Process $process): ?int
{
    foreach (explode(PHP_EOL, $process->getOutput()) as $line) {
        $decoded = json_decode(trim($line), true);

        if (is_array($decoded) && (int) ($decoded['id'] ?? 0) === 1) {
            $id = data_get($decoded, 'result.structuredContent.transaction.id');

            return $id === null ? null : (int) $id;
        }
    }

    return null;
}

function hermesMcpSqliteCount(string $database, string $table): int
{
    $pdo = new PDO('sqlite:'.$database);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return (int) $pdo->query("select count(*) from {$table}")->fetchColumn();
}

function hermesMcpCleanup(string $database): void
{
    foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

it('does not duplicate a concurrent create across processes with the same key', function () {
    $database = tempnam(sys_get_temp_dir(), 'cash-compass-conc-');
    $accountEmail = 'mcp-concurrency@example.com';
    $environment = hermesMcpEnvironment($database, $accountEmail);

    hermesMcpSeedAccount($accountEmail, $environment);

    $request = hermesMcpCreateTransactionRequest('concurrent-key', '2026-01-10');

    $processes = [];

    for ($i = 0; $i < 6; $i++) {
        $processes[] = hermesMcpStartServer($request, $environment);
    }

    foreach ($processes as $process) {
        $process->wait();
    }

    $ids = array_filter(array_map(
        fn (Process $process): ?int => hermesMcpResponseTransactionId($process),
        $processes,
    ));

    $transactions = hermesMcpSqliteCount($database, 'daily_transactions');
    $operations = hermesMcpSqliteCount($database, 'mcp_operations');

    hermesMcpCleanup($database);

    expect($ids)->toHaveCount(6)
        ->and(array_unique($ids))->toHaveCount(1)
        ->and($transactions)->toBe(1)
        ->and($operations)->toBe(1);
});

it('replays a committed create after a lost response without duplicating', function () {
    $database = tempnam(sys_get_temp_dir(), 'cash-compass-retry-');
    $accountEmail = 'mcp-retry@example.com';
    $environment = hermesMcpEnvironment($database, $accountEmail);

    hermesMcpSeedAccount($accountEmail, $environment);

    $request = hermesMcpCreateTransactionRequest('retry-key', '2026-01-10');

    $first = hermesMcpStartServer($request, $environment);
    $first->wait();

    // Segunda chamada em um processo novo, como um cliente que perdeu a resposta.
    $second = hermesMcpStartServer($request, $environment);
    $second->wait();

    $firstId = hermesMcpResponseTransactionId($first);
    $secondId = hermesMcpResponseTransactionId($second);

    $transactions = hermesMcpSqliteCount($database, 'daily_transactions');
    $operations = hermesMcpSqliteCount($database, 'mcp_operations');

    hermesMcpCleanup($database);

    expect($firstId)->not->toBeNull()
        ->and($secondId)->toBe($firstId)
        ->and($transactions)->toBe(1)
        ->and($operations)->toBe(1);
});
