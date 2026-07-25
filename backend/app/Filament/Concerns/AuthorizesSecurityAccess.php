<?php

namespace App\Filament\Concerns;

use App\Features\Security\Support\SecurityPermission;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Shared gate for every security page, resource and widget.
 *
 * Uses SecurityPermission rather than AdminPermission so Phase 12 adds its own
 * permissions instead of editing the Admin module's list.
 */
trait AuthorizesSecurityAccess
{
    protected static function allowsSecurity(string $permission): bool
    {
        $user = Auth::user();

        return $user instanceof User && SecurityPermission::allows($user, $permission);
    }

    protected static function canViewSecurity(): bool
    {
        return static::allowsSecurity(SecurityPermission::VIEW_SECURITY);
    }
}
