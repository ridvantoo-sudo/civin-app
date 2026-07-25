<?php

namespace App\Features\Games\Core;

use App\Features\Games\Core\Contracts\GamePlugin;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class GameRegistry
{
    /** @var array<string, GamePlugin> */
    private array $plugins = [];

    public function register(GamePlugin $plugin): void
    {
        if (isset($this->plugins[$plugin->slug()])) {
            throw new InvalidArgumentException('Plugin already registered: '.$plugin->slug());
        }

        $this->plugins[$plugin->slug()] = $plugin;
    }

    /** @param  iterable<GamePlugin>  $plugins */
    public function registerAll(iterable $plugins): void
    {
        foreach ($plugins as $plugin) {
            $this->register($plugin);
        }
    }

    public function get(string $slug): ?GamePlugin
    {
        return $this->plugins[$slug] ?? null;
    }

    public function require(string $slug): GamePlugin
    {
        $plugin = $this->get($slug);
        if ($plugin === null) {
            throw new InvalidArgumentException('No game plugin registered for: '.$slug);
        }

        return $plugin;
    }

    /** @return Collection<int, GamePlugin> */
    public function all(): Collection
    {
        return collect(array_values($this->plugins));
    }

    /** @return Collection<int, string> */
    public function slugs(): Collection
    {
        return collect(array_keys($this->plugins));
    }

    public function has(string $slug): bool
    {
        return isset($this->plugins[$slug]);
    }
}
