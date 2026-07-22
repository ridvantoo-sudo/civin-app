<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesAdminAccess
{
    public static function canAccessWithPermission(string $permission): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->can($permission);
    }
}
