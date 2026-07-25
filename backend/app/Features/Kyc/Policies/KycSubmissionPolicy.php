<?php

namespace App\Features\Kyc\Policies;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Users\Models\User;

final class KycSubmissionPolicy
{
    public function submit(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function viewOwn(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_guest;
    }

    public function view(User $user, KycSubmission $submission): bool
    {
        return $submission->isOwnedBy($user)
            || $user->is_admin
            || $user->can(AdminPermission::REVIEW_KYC);
    }

    public function review(User $user): bool
    {
        return $user->is_admin || $user->can(AdminPermission::REVIEW_KYC);
    }

    public function viewAnyAdmin(User $user): bool
    {
        return $this->review($user);
    }
}
