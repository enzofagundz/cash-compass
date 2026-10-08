<?php

namespace App\Mcp\Concerns;

use App\Models\AccountPlan;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

trait ResolvesTransactionRelations
{
    /**
     * @param  array<int, int>  $alreadyLinkedTagIds
     * @return array<int, int>
     */
    protected function resolveTagIds(User $account, Request $request, array $alreadyLinkedTagIds = []): array
    {
        $rawTagIds = $request->get('tags');

        $requested = collect(is_array($rawTagIds) ? $rawTagIds : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($requested === []) {
            return [];
        }

        $allowed = Tag::query()
            ->attachableTo($account, $alreadyLinkedTagIds)
            ->whereIn('tags.id', $requested)
            ->pluck('tags.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (count($allowed) !== count($requested)) {
            throw ValidationException::withMessages([
                'tags' => 'Uma ou mais tags não pertencem à conta ou estão arquivadas.',
            ]);
        }

        return $allowed;
    }

    protected function resolveAccountPlanId(User $account, Request $request): ?int
    {
        $planId = $request->get('account_plan_id');

        if ($planId === null) {
            return null;
        }

        $selectable = AccountPlan::query()
            ->selectableFor($account)
            ->whereKey((int) $planId)
            ->exists();

        if (! $selectable) {
            throw ValidationException::withMessages([
                'account_plan_id' => 'Plano não encontrado ou inativo para a conta.',
            ]);
        }

        return (int) $planId;
    }
}
