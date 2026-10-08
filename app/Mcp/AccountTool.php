<?php

namespace App\Mcp;

use App\Mcp\Support\ConfiguredAccount;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

abstract class AccountTool extends Tool
{
    /**
     * @var array<int, string>
     */
    private const IDENTITY_ARGUMENTS = [
        'user_id',
        'user',
        'account_id',
        'account_email',
        'email',
    ];

    protected function account(Request $request, ConfiguredAccount $accounts): User
    {
        foreach (self::IDENTITY_ARGUMENTS as $argument) {
            if ($request->get($argument) !== null) {
                throw ValidationException::withMessages([
                    $argument => 'A identidade financeira vem da configuração local e não pode ser enviada nas ferramentas.',
                ]);
            }
        }

        return $accounts->resolve();
    }
}
