<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\DTOs\RegisterData;
use App\Features\Authentication\DTOs\TokenPair;
use App\Features\Authentication\Services\AuthenticationService;

final readonly class Register
{
    public function __construct(private AuthenticationService $authentication) {}

    public function execute(array $validated): TokenPair
    {
        return $this->authentication->register(RegisterData::fromArray($validated));
    }
}
