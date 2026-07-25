<?php

namespace App\Filament\Pages\Security\Concerns;

use App\Filament\Concerns\AuthorizesSecurityAccess;

trait SecurityPage
{
    use AuthorizesSecurityAccess;

    public static function canAccess(): bool
    {
        return static::canViewSecurity();
    }
}
