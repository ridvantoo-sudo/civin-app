<?php

namespace App\Features\Security\AntiCheat;

use App\Features\Security\AntiCheat\Contracts\Scanner;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the configured detector set.
 *
 * Scanners are resolved lazily and memoised for the lifetime of the worker, so
 * a queue process pays construction cost once regardless of event volume.
 */
final class ScannerRegistry
{
    /** @var list<Scanner>|null */
    private ?array $resolved = null;

    /** @var list<Scanner> */
    private array $extra = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Registers a detector at runtime. Tests and future plugin packages use
     * this instead of editing config.
     */
    public function extend(Scanner $scanner): void
    {
        $this->extra[] = $scanner;
        $this->resolved = null;
    }

    public function flush(): void
    {
        $this->resolved = null;
    }

    /**
     * @return list<Scanner>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $scanners = [];
        foreach ((array) config('security_platform.scanners', []) as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            $instance = $this->container->make($class);
            if ($instance instanceof Scanner) {
                $scanners[] = $instance;
            }
        }

        return $this->resolved = array_values(array_merge($scanners, $this->extra));
    }
}
