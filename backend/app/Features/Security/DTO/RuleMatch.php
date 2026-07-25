<?php

namespace App\Features\Security\DTO;

/**
 * Pairs a detected signal with the rule that fired on it.
 */
final readonly class RuleMatch
{
    public function __construct(
        public string $ruleKey,
        public string $ruleName,
        public string $signal,
        public string $severity,
        public string $action,
        public float $threshold,
        public float $confidence,
        public int $priority,
    ) {}

    public function exceededBy(): float
    {
        return round($this->confidence - $this->threshold, 4);
    }
}
