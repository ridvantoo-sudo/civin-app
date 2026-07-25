<?php

namespace App\Filament\Pages\Analytics\Concerns;

use App\Features\Admin\Support\AdminPermission;
use App\Filament\Concerns\AuthorizesAdminAccess;

trait AuthorizesAnalyticsAccess
{
    use AuthorizesAdminAccess;

    public static function canAccess(): bool
    {
        return static::canAccessWithPermission(AdminPermission::VIEW_ANALYTICS);
    }
}
