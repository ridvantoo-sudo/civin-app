<?php

namespace App\Features\Security\Rules;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\DTO\RuleDefinition;
use App\Features\Security\DTO\RuleMatch;
use App\Features\Security\DTO\SecurityEventData;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecuritySeverity;

/**
 * Turns detections into decisions.
 *
 * This is the only component allowed to choose an action, which is why
 * scanners stay pure observers and can be added freely.
 */
final class SecurityRuleEngine
{
    public function __construct(private readonly RuleProvider $rules) {}

    /**
     * Evaluates every signal against every enabled rule.
     *
     * @param  list<DetectedSignal>  $signals
     * @return list<RuleMatch>
     */
    public function evaluate(array $signals, ?SecurityEventData $event = null): array
    {
        if ($signals === []) {
            return [];
        }

        $matches = [];
        foreach ($this->rules->enabled() as $rule) {
            foreach ($signals as $signal) {
                if (! $rule->matchesSignal($signal->signal)) {
                    continue;
                }
                if ($signal->confidence < $rule->threshold) {
                    continue;
                }
                if (! $this->conditionsHold($rule, $signal, $event)) {
                    continue;
                }

                $matches[] = new RuleMatch(
                    ruleKey: $rule->key,
                    ruleName: $rule->name,
                    signal: $signal->signal,
                    severity: $this->effectiveSeverity($rule, $signal),
                    action: $rule->action,
                    threshold: $rule->threshold,
                    confidence: $signal->confidence,
                    priority: $rule->priority,
                );
            }
        }

        usort($matches, static fn (RuleMatch $a, RuleMatch $b): int => $a->priority <=> $b->priority);

        return $matches;
    }

    /**
     * The strongest action among all matches wins, so a single critical rule
     * is never diluted by a pile of low-severity ones.
     *
     * @param  list<RuleMatch>  $matches
     */
    public function resolveAction(array $matches): string
    {
        $action = SecurityAction::ALLOW;
        foreach ($matches as $match) {
            $action = SecurityAction::strongest($action, $match->action);
        }

        return $action;
    }

    /**
     * Optional per-rule guards, e.g. `{"surface": "wallet", "min_amount": 500}`.
     * Unknown condition keys are ignored rather than failing closed.
     */
    private function conditionsHold(RuleDefinition $rule, DetectedSignal $signal, ?SecurityEventData $event): bool
    {
        foreach ($rule->conditions as $key => $expected) {
            $holds = match ($key) {
                'surface' => $this->matchesSurface($expected, $signal, $event),
                'min_confidence' => is_numeric($expected) && $signal->confidence >= (float) $expected,
                'min_severity' => is_string($expected) && $signal->isAtLeast(SecuritySeverity::normalize($expected)),
                'min_amount' => is_numeric($expected) && $event !== null && $event->amountAsFloat() >= (float) $expected,
                'platform' => $event !== null && is_string($expected) && $event->platform === strtolower($expected),
                'country_code' => $event !== null && is_string($expected) && $event->countryCode === strtoupper($expected),
                default => true,
            };

            if (! $holds) {
                return false;
            }
        }

        return true;
    }

    private function matchesSurface(mixed $expected, DetectedSignal $signal, ?SecurityEventData $event): bool
    {
        $surface = $signal->surface ?? $event?->surface;
        if ($surface === null) {
            return false;
        }

        if (is_array($expected)) {
            return in_array($surface, array_map('strval', $expected), true);
        }

        return is_string($expected) && $surface === $expected;
    }

    /**
     * A rule may only raise severity, never lower what a detector observed.
     */
    private function effectiveSeverity(RuleDefinition $rule, DetectedSignal $signal): string
    {
        return SecuritySeverity::weight($rule->severity) >= SecuritySeverity::weight($signal->severity)
            ? $rule->severity
            : $signal->severity;
    }
}
