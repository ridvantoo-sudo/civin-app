<?php

namespace App\Features\Security\Engine;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;

/**
 * Wallet-scoped protection.
 *
 * Note this engine only *reports*: it never rolls back or blocks a
 * transaction. Wallet remains the sole owner of its business logic; the
 * platform exposes the lock state through `GET /api/v1/security/me`.
 */
final class WalletSecurityEngine implements SecurityAnalyzer
{
    public function name(): string
    {
        return 'wallet';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->surface === SecuritySurface::WALLET && $event->isIdentified();
    }

    public function analyze(SecurityEventData $event, SecurityContext $context): array
    {
        $lockedScopes = SecurityBan::query()
            ->effective()
            ->where('user_id', $event->userId)
            ->whereIn('scope', [SecurityBan::SCOPE_WALLET, SecurityBan::SCOPE_PLATFORM])
            ->pluck('scope')
            ->all();

        if ($lockedScopes === []) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::WALLET_ABUSE,
            severity: SecuritySeverity::CRITICAL,
            confidence: 94.0,
            title: 'Wallet activity while a restriction is active',
            evidence: [
                'active_scopes' => array_values(array_unique($lockedScopes)),
                'event' => $event->eventName,
                'amount' => $event->amount,
            ],
            surface: SecuritySurface::WALLET,
            userId: $event->userId,
        )];
    }
}
