<?php

namespace App\Features\Security\DTO;

use App\Features\Security\Support\SecuritySeverity;

/**
 * A single detection produced by a scanner or engine.
 *
 * A signal is an observation, not a decision. Only SecurityRuleEngine converts
 * signals into actions, which keeps detection and enforcement independent.
 */
final readonly class DetectedSignal
{
    /**
     * @param  float  $confidence  0-100
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $signal,
        public string $severity,
        public float $confidence,
        public string $title,
        public array $evidence = [],
        public ?string $surface = null,
        public ?string $userId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public static function make(
        string $signal,
        string $severity,
        float $confidence,
        string $title,
        array $evidence = [],
        ?string $surface = null,
        ?string $userId = null,
    ): self {
        return new self(
            signal: $signal,
            severity: SecuritySeverity::normalize($severity),
            confidence: max(0.0, min(100.0, $confidence)),
            title: mb_substr($title, 0, 191),
            evidence: $evidence,
            surface: $surface,
            userId: $userId,
        );
    }

    /**
     * Risk points this signal contributes before rule weighting.
     */
    public function weightedScore(): float
    {
        return round($this->confidence * SecuritySeverity::multiplier($this->severity), 4);
    }

    public function isAtLeast(string $severity): bool
    {
        return SecuritySeverity::weight($this->severity) >= SecuritySeverity::weight($severity);
    }
}
