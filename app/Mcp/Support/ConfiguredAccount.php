<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ConfiguredAccount
{
    public function resolve(): User
    {
        $email = config('cash_compass.mcp.account_email');

        if (! is_string($email) || trim($email) === '') {
            throw ValidationException::withMessages([
                'account' => 'A conta financeira do MCP não está configurada.',
            ]);
        }

        $account = User::query()->where('email', $email)->first();

        if (! $account instanceof User) {
            throw ValidationException::withMessages([
                'account' => 'A conta financeira configurada para o MCP não existe.',
            ]);
        }

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'account' => 'A conta financeira configurada para o MCP está inativa.',
            ]);
        }

        if ($account->isAdmin()) {
            throw ValidationException::withMessages([
                'account' => 'Contas administrativas não podem ser usadas pelo MCP.',
            ]);
        }

        return $account;
    }
}
