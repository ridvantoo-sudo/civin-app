<?php

namespace App\Features\Profiles\Services;

use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Profiles\Models\Profile;
use App\Features\Profiles\Repositories\Contracts\ProfileRepository;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ProfileService
{
    public function __construct(
        private ProfileRepository $profiles,
        private BlockRepository $blocks,
    ) {}

    public function show(User $user): Profile
    {
        return $this->profiles->forUser($user);
    }

    public function update(User $user, array $attributes): Profile
    {
        if (array_key_exists('nickname', $attributes)) {
            $attributes['display_name'] = $attributes['nickname'];
            unset($attributes['nickname']);
        }

        if (array_key_exists('birthday', $attributes)) {
            $attributes['birth_date'] = $attributes['birthday'];
            unset($attributes['birthday']);
        }

        return $this->profiles->update($this->profiles->forUser($user), $attributes);
    }

    public function publicProfile(User $viewer, User $target): Profile
    {
        if ($this->blocks->existsBetween($viewer, $target)) {
            throw new AuthorizationException('This profile is unavailable.');
        }

        return $this->profiles->publicForUser($target);
    }
}
