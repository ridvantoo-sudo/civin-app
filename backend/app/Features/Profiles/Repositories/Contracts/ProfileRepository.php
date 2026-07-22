<?php

namespace App\Features\Profiles\Repositories\Contracts;

use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;

interface ProfileRepository
{
    public function createForUser(User $user, string $displayName, ?string $avatarUrl = null): Profile;

    public function forUser(User $user): Profile;

    public function publicForUser(User $user): Profile;

    public function update(Profile $profile, array $attributes): Profile;
}
