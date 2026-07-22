<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\Services\AuthenticationService;
use App\Features\Users\Models\User;

final readonly class DeleteAccount
{
    public function __construct(private AuthenticationService $authentication) {}

    public function execute(User $user, ?string $password): void
    {
        $this->authentication->delete($user, $password);
    }
}
