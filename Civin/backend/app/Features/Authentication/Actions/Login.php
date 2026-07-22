<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\DTOs\LoginData;
use App\Features\Authentication\DTOs\TokenPair;
use App\Features\Authentication\Services\AuthenticationService;

final readonly class Login
{
    public function __construct(private AuthenticationService $authentication) {}

    public function execute(array $validated): TokenPair
    {
        return $this->authentication->login(LoginData::fromArray($validated));
    }
}
