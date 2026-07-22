<?php

namespace App\Features\Profiles\Repositories\Eloquent;

use App\Features\Profiles\Models\Profile;
use App\Features\Profiles\Repositories\Contracts\ProfileRepository;
use App\Features\Users\Models\User;

final class EloquentProfileRepository implements ProfileRepository
{
    public function createForUser(User $user, string $displayName, ?string $avatarUrl = null): Profile
    {
        return $user->profile()->create([
            'display_name' => $displayName,
            'avatar_url' => $avatarUrl,
        ]);
    }

    public function forUser(User $user): Profile
    {
        return $user->profile()->with('country', 'user.socialStatus')->firstOrFail();
    }

    public function publicForUser(User $user): Profile
    {
        return $user->profile()->with('country', 'user.socialStatus')->firstOrFail();
    }

    public function update(Profile $profile, array $attributes): Profile
    {
        $profile->update($attributes);

        return $profile->fresh()->load('country', 'user.socialStatus');
    }
}
