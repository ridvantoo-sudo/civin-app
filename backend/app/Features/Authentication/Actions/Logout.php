<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\Services\AuthenticationService;
use App\Features\Users\Models\User;

final readonly class Logout
{
    public function __construct(private AuthenticationService $authentication) {}

    public function execute(User $user): void
    {
        $this->authentication->logout($user);
    }
}
