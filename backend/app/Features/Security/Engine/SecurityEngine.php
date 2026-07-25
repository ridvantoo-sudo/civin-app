<?php

namespace App\Features\Security\Engine;

use App\Features\Security\AntiCheat\Contracts\Scanner;
use App\Features\Security\AntiCheat\ScannerRegistry;
use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\DTO\SecurityVerdict;
use App\Features\Security\Engine\Contracts\SecurityAnalyzer;
use App\Features\Security\Rules\SecurityRuleEngine;
use App\Features\Security\Services\SecurityAuditLogger;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecurityContext;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * The orchestrator.
 *
 * It owns the *sequence* — scan, analyze, score, decide, apply — but knows
 * nothing about any individual detection. New detectors and engines are added
 * through config, so this class is closed for modification by design.
 *
 * A failure in any one detector is contained: it is logged and the remaining
 * detectors still run, because partial detection beats none.
 */
final class SecurityEngine
{
    public function __construct(
        private readonly ScannerRegistry $scanners,
        private readonly RiskEngine $riskEngine,
        private readonly SecurityRuleEngine $ruleEngine,
        private readonly ModerationEngine $moderation,
        private readonly SecurityAuditLogger $logger,
        private readonly Container $container,
    ) {}

    /**
     * Full pipeline for one event. Always called from a queue worker.
     */
    public function analyze(SecurityEventData $event, ?string $eventId = null): SecurityVerdict
    {
        $context = new SecurityContext($event);

        $signals = array_merge(
            $this->runScanners($event, $context),
            $this->runAnalyzers($event, $context),
        );

        if ($signals === []) {
            return SecurityVerdict::allow();
        }

        $matches = $this->ruleEngine->evaluate($signals, $event);
        $action = $this->ruleEngine->resolveAction($matches);
        $riskDelta = $this->totalRisk($signals);

        // Standing scores can escalate beyond what this single event justifies.
        if ($event->isIdentified()) {
            $score = $this->riskEngine->apply((string) $event->userId, $signals);
            $escalation = $this->riskEngine->escalationFor($score);
            if ($escalation !== null) {
                $action = SecurityAction::strongest($action, $escalation);
            }
        }

        $verdict = new SecurityVerdict(
            action: $action,
            signals: $signals,
            matches: $matches,
            riskDelta: $riskDelta,
            reason: $this->describe($signals),
            context: [
                'event' => $event->eventName,
                'surface' => $event->surface,
            ],
        );

        $this->moderation->apply($verdict, $event, $signals, $eventId);

        return $verdict;
    }

    /**
     * @return list<DetectedSignal>
     */
    private function runScanners(SecurityEventData $event, SecurityContext $context): array
    {
        $signals = [];

        foreach ($this->scanners->all() as $scanner) {
            try {
                if (! $scanner->supports($event)) {
                    continue;
                }
                $signals = array_merge($signals, $scanner->scan($event, $context));
            } catch (Throwable $exception) {
                $this->reportFailure('scanner', $scanner->key(), $exception, $event);
            }
        }

        return $signals;
    }

    /**
     * @return list<DetectedSignal>
     */
    private function runAnalyzers(SecurityEventData $event, SecurityContext $context): array
    {
        $signals = [];

        foreach ($this->analyzers() as $analyzer) {
            try {
                if (! $analyzer->supports($event)) {
                    continue;
                }
                $signals = array_merge($signals, $analyzer->analyze($event, $context));
            } catch (Throwable $exception) {
                $this->reportFailure('engine', $analyzer->name(), $exception, $event);
            }
        }

        return $signals;
    }

    /**
     * @return list<SecurityAnalyzer>
     */
    private function analyzers(): array
    {
        $analyzers = [];
        foreach ((array) config('security_platform.engines', []) as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            $instance = $this->container->make($class);
            if ($instance instanceof SecurityAnalyzer) {
                $analyzers[] = $instance;
            }
        }

        return $analyzers;
    }

    /**
     * @param  list<DetectedSignal>  $signals
     */
    private function totalRisk(array $signals): float
    {
        $total = 0.0;
        foreach ($signals as $signal) {
            $total += $signal->weightedScore();
        }

        return round($total, 4);
    }

    /**
     * @param  list<DetectedSignal>  $signals
     */
    private function describe(array $signals): string
    {
        $titles = array_slice(array_values(array_unique(array_map(
            static fn (DetectedSignal $signal): string => $signal->title,
            $signals,
        ))), 0, 3);

        return mb_substr(implode('; ', $titles), 0, 500);
    }

    private function reportFailure(string $kind, string $name, Throwable $exception, SecurityEventData $event): void
    {
        $this->logger->engine(
            "{$kind}.failed",
            "{$kind} {$name} threw during analysis",
            [
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 300),
                'event' => $event->eventName,
            ],
            $event->userId,
        );
    }

    /**
     * Escape hatch for tests and future plugin packages.
     */
    public function registerScanner(Scanner $scanner): void
    {
        $this->scanners->extend($scanner);
    }
}
