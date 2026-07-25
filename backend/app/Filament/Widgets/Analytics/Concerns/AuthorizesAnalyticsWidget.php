<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use App\Features\Admin\Support\AdminPermission;
use App\Filament\Concerns\AuthorizesAdminAccess;

trait AuthorizesAnalyticsWidget
{
    use AuthorizesAdminAccess;

    public static function canView(): bool
    {
        return static::canAccessWithPermission(AdminPermission::VIEW_ANALYTICS);
    }
}
