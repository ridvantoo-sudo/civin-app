<?php

namespace App\Features\Security\DTO;

use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecuritySeverity;

/**
 * A rule as the engine sees it, after config defaults have been merged with
 * database overrides. Hot reload works by rebuilding these from cache.
 */
final readonly class RuleDefinition
{
    /**
     * @param  array<string, mixed>  $conditions
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $signal,
        public string $severity,
        public string $action,
        public float $threshold,
        public int $priority,
        public bool $isEnabled,
        public array $conditions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            key: (string) ($row['key'] ?? ''),
            name: (string) ($row['name'] ?? $row['key'] ?? 'Unnamed rule'),
            signal: (string) ($row['signal'] ?? ''),
            severity: SecuritySeverity::normalize($row['severity'] ?? null),
            action: SecurityAction::normalize($row['action'] ?? null),
            threshold: (float) ($row['threshold'] ?? 70),
            priority: (int) ($row['priority'] ?? 500),
            isEnabled: (bool) ($row['is_enabled'] ?? true),
            conditions: is_array($row['conditions'] ?? null) ? $row['conditions'] : [],
        );
    }

    /**
     * Matches when the signal name is identical or the rule declares a prefix
     * wildcard (e.g. `game.*`), so operators can write coarse rules.
     */
    public function matchesSignal(string $signal): bool
    {
        if ($this->signal === $signal) {
            return true;
        }

        if (str_ends_with($this->signal, '*')) {
            return str_starts_with($signal, rtrim($this->signal, '*'));
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'signal' => $this->signal,
            'severity' => $this->severity,
            'action' => $this->action,
            'threshold' => $this->threshold,
            'priority' => $this->priority,
            'is_enabled' => $this->isEnabled,
            'conditions' => $this->conditions,
        ];
    }
}
