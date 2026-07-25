<?php

namespace App\Features\GameAnalytics\Policies;

use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;

final class AnalyticsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === User::STATUS_ACTIVE && ! $user->is_guest;
    }

    public function viewDashboard(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function export(User $user): bool
    {
        return $user->is_admin || $user->hasAnyRole(AdminRole::all());
    }

    public function manageReports(User $user): bool
    {
        return $user->is_admin || $user->hasAnyRole([
            AdminRole::SUPER_ADMIN,
            AdminRole::FINANCE_ADMIN,
        ]);
    }
}
