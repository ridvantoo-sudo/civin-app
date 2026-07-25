<?php

namespace App\Features\Security\Rules;

use App\Features\Security\DTO\RuleDefinition;
use App\Features\Security\Models\SecurityRule;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Supplies the active rule set.
 *
 * Config defaults are the floor; rows in `security_rules` override them by
 * key. The merged set is cached, and `flush()` (called from the model's saved
 * hook) is what makes rule edits hot-reload without a deploy.
 */
final class RuleProvider
{
    private const CACHE_KEY = 'sec:rules:active';

    private const CACHE_TTL = 300;

    /** @var list<RuleDefinition>|null */
    private ?array $memo = null;

    public function __construct(private readonly ?CacheRepository $cache = null) {}

    /**
     * @return list<RuleDefinition>
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $rows = $this->cachedRows();

        $definitions = [];
        foreach ($rows as $row) {
            $definition = RuleDefinition::fromArray($row);
            if ($definition->key === '' || $definition->signal === '') {
                continue;
            }
            $definitions[] = $definition;
        }

        // Lowest priority number wins, matching the rest of the admin panel.
        usort(
            $definitions,
            static fn (RuleDefinition $a, RuleDefinition $b): int => $a->priority <=> $b->priority,
        );

        return $this->memo = $definitions;
    }

    /**
     * @return list<RuleDefinition>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (RuleDefinition $rule): bool => $rule->isEnabled,
        ));
    }

    public function find(string $key): ?RuleDefinition
    {
        foreach ($this->all() as $rule) {
            if ($rule->key === $key) {
                return $rule;
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->memo = null;
        $this->store()->forget(self::CACHE_KEY);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cachedRows(): array
    {
        try {
            /** @var list<array<string, mixed>> $rows */
            $rows = $this->store()->remember(
                self::CACHE_KEY,
                self::CACHE_TTL,
                fn (): array => $this->mergeRows(),
            );

            return $rows;
        } catch (Throwable) {
            // Never let a cache or database outage disable detection entirely.
            return $this->configRows();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mergeRows(): array
    {
        $merged = [];
        foreach ($this->configRows() as $row) {
            $merged[(string) $row['key']] = $row;
        }

        try {
            $overrides = SecurityRule::query()->get();
        } catch (Throwable) {
            return array_values($merged);
        }

        foreach ($overrides as $rule) {
            $merged[(string) $rule->key] = [
                'key' => $rule->key,
                'name' => $rule->name,
                'signal' => $rule->signal,
                'severity' => $rule->severity,
                'action' => $rule->action,
                'threshold' => $rule->threshold,
                'priority' => $rule->priority,
                'is_enabled' => $rule->is_enabled,
                'conditions' => $rule->conditions ?? [],
            ];
        }

        return array_values($merged);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function configRows(): array
    {
        $rows = [];
        foreach ((array) config('security_platform.default_rules', []) as $row) {
            if (is_array($row) && isset($row['key'])) {
                $row['is_enabled'] = $row['is_enabled'] ?? true;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function store(): CacheRepository
    {
        return $this->cache ?? Cache::store((string) config('security_platform.cache_store', config('cache.default')));
    }
}
