<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;

trait VisibleToNonAdminWidgets
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->isAdmin();
    }
}
