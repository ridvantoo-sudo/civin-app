<?php

namespace App\Features\Security\AntiCheat\Contracts;

/**
 * Uniform result type for every predictor, so swapping a null adapter for a
 * real model never changes calling code.
 */
final readonly class Prediction
{
    /**
     * @param  float  $score  0.0 – 1.0
     * @param  array<string, mixed>  $explanation
     */
    public function __construct(
        public float $score,
        public float $confidence,
        public bool $available,
        public ?string $label = null,
        public array $explanation = [],
    ) {}

    /**
     * The adapter had no model to consult. Callers treat this as "no opinion"
     * rather than "clean", so a missing model never suppresses a rule.
     */
    public static function unavailable(): self
    {
        return new self(score: 0.0, confidence: 0.0, available: false);
    }

    /**
     * @param  array<string, mixed>  $explanation
     */
    public static function of(float $score, float $confidence = 1.0, ?string $label = null, array $explanation = []): self
    {
        return new self(
            score: max(0.0, min(1.0, $score)),
            confidence: max(0.0, min(1.0, $confidence)),
            available: true,
            label: $label,
            explanation: $explanation,
        );
    }

    /** Score expressed on the engine's 0-100 confidence scale. */
    public function asConfidence(): float
    {
        return round($this->score * 100, 4);
    }
}
