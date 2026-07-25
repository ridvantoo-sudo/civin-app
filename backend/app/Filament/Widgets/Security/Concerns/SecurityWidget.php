<?php

namespace App\Filament\Widgets\Security\Concerns;

use App\Filament\Concerns\AuthorizesSecurityAccess;

trait SecurityWidget
{
    use AuthorizesSecurityAccess;

    public static function canView(): bool
    {
        return static::canViewSecurity();
    }
}
