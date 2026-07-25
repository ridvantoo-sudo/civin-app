<?php

namespace App\Features\Security\DTO;

use App\Features\Security\Support\SecurityAction;

/**
 * The engine's final answer for one analyzed event.
 */
final readonly class SecurityVerdict
{
    /**
     * @param  list<DetectedSignal>  $signals
     * @param  list<RuleMatch>  $matches
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $action,
        public array $signals = [],
        public array $matches = [],
        public float $riskDelta = 0.0,
        public ?string $reason = null,
        public array $context = [],
    ) {}

    public static function allow(): self
    {
        return new self(action: SecurityAction::ALLOW);
    }

    public function isAllow(): bool
    {
        return $this->action === SecurityAction::ALLOW;
    }

    public function requiresEnforcement(): bool
    {
        return SecurityAction::isEnforcement($this->action);
    }

    public function hasSignals(): bool
    {
        return $this->signals !== [];
    }

    /** @return list<string> */
    public function signalNames(): array
    {
        return array_values(array_unique(array_map(
            static fn (DetectedSignal $signal): string => $signal->signal,
            $this->signals,
        )));
    }

    public function hasSignal(string $signal): bool
    {
        return in_array($signal, $this->signalNames(), true);
    }

    public function highestSeverity(): ?string
    {
        $highest = null;
        foreach ($this->signals as $signal) {
            if ($highest === null || $signal->isAtLeast($highest)) {
                $highest = $signal->severity;
            }
        }

        return $highest;
    }
}
