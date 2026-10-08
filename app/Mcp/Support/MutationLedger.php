<?php

namespace App\Mcp\Support;

use App\Models\McpMutationAudit;
use App\Models\McpOperation;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MutationLedger
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): array<string, mixed>  $mutation
     * @return array<string, mixed>
     */
    public function run(User $account, string $tool, string $operationKey, array $arguments, Closure $mutation): array
    {
        $operationKey = trim($operationKey);
        $hash = hash('sha256', json_encode($arguments, JSON_THROW_ON_ERROR));

        $existing = $this->findOperation($account, $operationKey);

        if ($existing instanceof McpOperation) {
            return $this->replay($existing, $hash, $account, $tool);
        }

        try {
            return DB::transaction(function () use ($account, $tool, $operationKey, $hash, $mutation): array {
                $result = $mutation();

                McpOperation::query()->create([
                    'user_id' => $account->getKey(),
                    'operation_key' => $operationKey,
                    'tool' => $tool,
                    'arguments_hash' => $hash,
                    'result' => $result,
                ]);

                return $result;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findOperation($account, $operationKey);

            if (! $existing instanceof McpOperation) {
                throw $exception;
            }

            return $this->replay($existing, $hash, $account, $tool);
        }
    }

    public function record(User $account, string $tool, string $result, ?Model $record = null): McpMutationAudit
    {
        return McpMutationAudit::query()->create([
            'user_id' => $account->getKey(),
            'tool' => $tool,
            'auditable_type' => $record?->getMorphClass(),
            'auditable_id' => $record?->getKey(),
            'result' => $result,
        ]);
    }

    private function findOperation(User $account, string $operationKey): ?McpOperation
    {
        return McpOperation::query()
            ->forUser($account)
            ->where('operation_key', $operationKey)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function replay(McpOperation $operation, string $hash, User $account, string $tool): array
    {
        if ($operation->tool !== $tool || ! hash_equals($operation->arguments_hash, $hash)) {
            $this->record($account, $tool, 'conflict');

            throw ValidationException::withMessages([
                'operation_key' => 'Esta chave de operação já foi usada com argumentos diferentes.',
            ]);
        }

        $this->record($account, $tool, 'replayed');

        return $operation->result;
    }
}
