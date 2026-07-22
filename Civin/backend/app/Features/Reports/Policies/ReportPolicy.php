<?php

namespace App\Features\Reports\Policies;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Reports\Models\Report;
use App\Features\Users\Models\User;

final class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return (bool) $user->is_admin
            || $user->can(AdminPermission::REVIEW_REPORTS)
            || $report->reporter_id === $user->getKey();
    }

    public function reviewAny(User $user): bool
    {
        return (bool) $user->is_admin || $user->can(AdminPermission::REVIEW_REPORTS);
    }

    public function review(User $user, Report $report): bool
    {
        return (bool) $user->is_admin || $user->can(AdminPermission::REVIEW_REPORTS);
    }
}
