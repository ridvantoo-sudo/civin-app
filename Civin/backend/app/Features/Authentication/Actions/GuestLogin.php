<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\DTOs\DeviceData;
use App\Features\Authentication\DTOs\TokenPair;
use App\Features\Authentication\Services\AuthenticationService;

final readonly class GuestLogin
{
    public function __construct(private AuthenticationService $authentication) {}

    public function execute(array $device): TokenPair
    {
        return $this->authentication->guest(DeviceData::fromArray($device));
    }
}
