<?php

namespace App\Features\Users\Services;

use App\Features\Users\Models\User;
use App\Features\Users\Repositories\Contracts\UserRepository;

final readonly class UserService
{
    public function __construct(private UserRepository $users) {}

    public function update(User $user, array $attributes): User
    {
        $emailChanged = array_key_exists('email', $attributes) && $attributes['email'] !== $user->email;
        $user = $this->users->update($user, $attributes);

        if ($emailChanged) {
            $user = $this->users->update($user, ['email_verified_at' => null]);
        }

        return $user;
    }
}
