<?php

namespace App\Features\Security\Actions;

use App\Features\Security\Services\SecurityCollector;
use App\Features\Security\Services\SecuritySessionService;

final readonly class VerifySession
{
    public function __construct(
        private SecuritySessionService $sessions,
        private SecurityCollector $collector,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(string $userId, array $input, ?string $ipAddress = null): array
    {
        $session = $this->sessions->verify(
            userId: $userId,
            sessionId: (string) $input['session_id'],
            deviceId: isset($input['device_id']) ? (string) $input['device_id'] : null,
            ipAddress: $ipAddress,
            countryCode: isset($input['country_code']) ? (string) $input['country_code'] : null,
            platform: isset($input['platform']) ? (string) $input['platform'] : null,
            metadata: is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
        );

        $concurrent = $this->sessions->concurrentCount($userId);

        $this->collector->collectFromDomainEvent('security.client.session_verified', [[
            'user_id' => $userId,
            'session_id' => (string) $input['session_id'],
            'device_id' => $input['device_id'] ?? null,
            'concurrent_sessions' => $concurrent,
            'platform' => $input['platform'] ?? null,
        ]]);

        return [
            'session_id' => $session->id,
            'verified' => $session->is_verified,
            'status' => $session->status,
            'concurrent_sessions' => $concurrent,
            'risk_score' => (float) $session->risk_score,
        ];
    }
}
