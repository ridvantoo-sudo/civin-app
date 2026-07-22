<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\DTOs\TokenPair;
use App\Features\Authentication\Services\AuthenticationService;

final readonly class RefreshToken
{
    public function __construct(private AuthenticationService $authentication) {}

    public function execute(string $plainToken): TokenPair
    {
        return $this->authentication->refresh($plainToken);
    }
}
