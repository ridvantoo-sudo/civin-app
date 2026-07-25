<?php

namespace App\Features\Security\Scanners;

use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use Carbon\CarbonImmutable;

/**
 * Replayed packets, tampered payloads and duplicate settlements.
 *
 * Detection is observational only: we never block the original request, we
 * report that the same settlement identity was seen twice.
 */
final class ReplayAttackScanner implements Scanner
{
    /** Requests older than this are treated as replayed rather than late. */
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    public function key(): string
    {
        return 'replay_attack';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->idempotencyKey !== null
            || $event->subjectId !== null
            || $event->get('nonce') !== null
            || $event->get('signature') !== null;
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $signals = [];

        $nonce = $event->get('nonce') ?? $event->get('request_id') ?? $event->get('client_token');
        if (is_string($nonce) && $nonce !== '' && ! $context->isFirstOccurrence("nonce:{$nonce}", 900)) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::REPLAY_ATTACK,
                severity: SecuritySeverity::CRITICAL,
                confidence: 92.0,
                title: 'Replayed request nonce',
                evidence: [
                    'event' => $event->eventName,
                    'surface' => $event->surface,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        // A settlement identity must only ever be observed once.
        $settlementId = $event->subjectId ?? $event->idempotencyKey;
        if (is_string($settlementId) && $settlementId !== '' && $this->isSettlement($event)) {
            $bucket = "settle:{$event->surface}:{$settlementId}";
            if (! $context->isFirstOccurrence($bucket, 3600)) {
                $signals[] = DetectedSignal::make(
                    signal: SecuritySignal::DUPLICATE_SETTLEMENT,
                    severity: SecuritySeverity::CRITICAL,
                    confidence: 90.0,
                    title: 'Duplicate settlement for one subject',
                    evidence: [
                        'event' => $event->eventName,
                        'subject_type' => $event->subjectType,
                        'amount' => $event->amount,
                    ],
                    surface: $event->surface,
                    userId: $event->userId,
                );
            }
        }

        if ($this->hasStaleTimestamp($event)) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::TAMPERED_REQUEST,
                severity: SecuritySeverity::HIGH,
                confidence: 78.0,
                title: 'Client timestamp outside acceptable skew',
                evidence: [
                    'client_timestamp' => $event->get('client_timestamp'),
                    'occurred_at' => $event->occurredAt->toIso8601String(),
                    'max_skew_seconds' => self::MAX_CLOCK_SKEW_SECONDS,
                ],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        if ($event->boolean('signature_invalid') || $event->boolean('checksum_mismatch')) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::TAMPERED_REQUEST,
                severity: SecuritySeverity::CRITICAL,
                confidence: 94.0,
                title: 'Request signature verification failed',
                evidence: ['event' => $event->eventName],
                surface: $event->surface,
                userId: $event->userId,
            );
        }

        return $signals;
    }

    private function isSettlement(SecurityEventData $event): bool
    {
        $name = strtolower($event->eventName);

        foreach (['settle', 'reward', 'payout', 'completed', 'finished', 'granted', 'claimed'] as $token) {
            if (str_contains($name, $token)) {
                return true;
            }
        }

        return false;
    }

    private function hasStaleTimestamp(SecurityEventData $event): bool
    {
        $clientTimestamp = $event->get('client_timestamp') ?? $event->get('client_time');
        if (! is_string($clientTimestamp) && ! is_numeric($clientTimestamp)) {
            return false;
        }

        try {
            $parsed = is_numeric($clientTimestamp)
                ? CarbonImmutable::createFromTimestamp((int) $clientTimestamp)
                : CarbonImmutable::parse((string) $clientTimestamp);
        } catch (\Throwable) {
            return false;
        }

        return abs($parsed->diffInSeconds($event->occurredAt)) > self::MAX_CLOCK_SKEW_SECONDS;
    }
}
