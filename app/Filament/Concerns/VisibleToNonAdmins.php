<?php

namespace App\Filament\Concerns;

use App\Models\User;

trait VisibleToNonAdmins
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->isAdmin();
    }
}
