<?php

namespace App\Features\Security\Scanners;

use App\Features\Security\AntiCheat\Contracts\FraudPredictor;
use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityContext;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;
use App\Features\Security\Support\Statistics;

/**
 * Recharge/withdrawal fraud, transaction replay, rapid transfers and balances
 * that move in ways the ledger cannot explain.
 */
final class WalletFraudScanner implements Scanner
{
    public function __construct(private readonly FraudPredictor $predictor) {}

    public function key(): string
    {
        return 'wallet_fraud';
    }

    public function supports(SecurityEventData $event): bool
    {
        return $event->surface === SecuritySurface::WALLET || $event->surface === SecuritySurface::GIFTS;
    }

    public function scan(SecurityEventData $event, SecurityContext $context): array
    {
        $thresholds = (array) config('security_platform.thresholds.wallet', []);
        $windowSeconds = (int) ($thresholds['rapid_transfer_window_seconds'] ?? 60);
        $transferLimit = (int) ($thresholds['rapid_transfer_count'] ?? 10);
        $balanceJumpLimit = (float) ($thresholds['abnormal_balance_jump'] ?? 1000000);

        $actor = $context->actorKey();
        $signals = [];

        $window = (int) floor($event->occurredAt->getTimestamp() / max(1, $windowSeconds));
        $transfers = $context->bump("wal:tx:{$actor}:{$window}", $windowSeconds * 3);
        if ($transfers > $transferLimit) {
            $signals[] = DetectedSignal::make(
                signal: SecuritySignal::RAPID_TRANSFERS,
                severity: SecuritySeverity::HIGH,
                confidence: Statistics::confidenceFromRatio((float) $transfers, (float) $transferLimit),
                title: 'Rapid succession of wallet movements',
                evidence: [
                    'transfers_in_window' => $transfers,
                    'limit' => $transferLimit,
                    'window_seconds' => $windowSeconds,
                ],
                surface: SecuritySurface::WALLET,
                userId: $event->userId,
            );
        }

        $signals = array_merge($signals, $this->inspectBalanceJump($event, $context, $actor, $balanceJumpLimit));
        $signals = array_merge($signals, $this->inspectRefundAbuse($event, $context, $actor));
        $signals = array_merge($signals, $this->consultModel($event, $transfers));

        return $signals;
    }

    /**
     * @return list<DetectedSignal>
     */
    private function inspectBalanceJump(
        SecurityEventData $event,
        SecurityContext $context,
        string $actor,
        float $limit,
    ): array {
        $balance = $event->get('coins_balance') ?? $event->get('balance');
        if (! is_numeric($balance)) {
            return [];
        }

        $balance = (float) $balance;
        $previous = $context->samples("wal:bal:{$actor}");
        $context->pushSample("wal:bal:{$actor}", $balance, 1, 86400);

        if ($previous === []) {
            return [];
        }

        $delta = $balance - $previous[0];
        $movement = abs($event->amountAsFloat());

        // A credit far larger than the transaction that caused it means the
        // balance was written outside the ledger path.
        if ($delta > $limit && ($movement <= 0 || $delta > $movement * 10)) {
            return [DetectedSignal::make(
                signal: SecuritySignal::ABNORMAL_BALANCE,
                severity: SecuritySeverity::CRITICAL,
                confidence: Statistics::confidenceFromRatio($delta, $limit),
                title: 'Balance increase unexplained by transaction',
                evidence: [
                    'balance_delta' => round($delta, 4),
                    'transaction_amount' => round($movement, 4),
                    'threshold' => $limit,
                ],
                surface: SecuritySurface::WALLET,
                userId: $event->userId,
            )];
        }

        return [];
    }

    /**
     * Recharge → withdraw churn with no gameplay in between is the standard
     * card-testing / laundering pattern.
     *
     * @return list<DetectedSignal>
     */
    private function inspectRefundAbuse(SecurityEventData $event, SecurityContext $context, string $actor): array
    {
        $name = strtolower($event->eventName);
        $day = $event->occurredAt->format('Ymd');

        if (str_contains($name, 'recharge') || str_contains($name, 'topup')) {
            $context->bump("wal:rc:{$actor}:{$day}", 172800);

            return [];
        }

        if (! str_contains($name, 'withdraw')) {
            return [];
        }

        $withdrawals = $context->bump("wal:wd:{$actor}:{$day}", 172800);
        $recharges = $context->counter("wal:rc:{$actor}:{$day}");

        if ($withdrawals < 3 || $recharges < 3) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::WALLET_FRAUD,
            severity: SecuritySeverity::CRITICAL,
            confidence: min(92.0, 60.0 + (min($withdrawals, $recharges) * 4.0)),
            title: 'Recharge-withdraw churn pattern',
            evidence: [
                'recharges_today' => $recharges,
                'withdrawals_today' => $withdrawals,
            ],
            surface: SecuritySurface::WALLET,
            userId: $event->userId,
        )];
    }

    /**
     * @return list<DetectedSignal>
     */
    private function consultModel(SecurityEventData $event, int $velocity): array
    {
        $prediction = $this->predictor->predictFraud($event, [
            'amount' => $event->amountAsFloat(),
            'velocity' => (float) $velocity,
        ]);

        if (! $prediction->available || $prediction->score < 0.85) {
            return [];
        }

        return [DetectedSignal::make(
            signal: SecuritySignal::WALLET_FRAUD,
            severity: SecuritySeverity::CRITICAL,
            confidence: $prediction->asConfidence(),
            title: 'Model flagged transaction as fraudulent',
            evidence: [
                'model_score' => round($prediction->score, 4),
                'label' => $prediction->label,
                'explanation' => $prediction->explanation,
            ],
            surface: SecuritySurface::WALLET,
            userId: $event->userId,
        )];
    }
}
