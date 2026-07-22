<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\DTOs\DeviceData;
use App\Features\Authentication\DTOs\TokenPair;
use App\Features\Authentication\Services\AuthenticationService;

final readonly class FirebaseLogin
{
    public function __construct(private AuthenticationService $authentication) {}

    public function execute(array $validated, ?string $ipAddress): TokenPair
    {
        $device = $validated['device'];
        $device['ip_address'] = $ipAddress;

        return $this->authentication->firebaseLogin(
            $validated['id_token'],
            DeviceData::fromArray($device),
        );
    }
}
