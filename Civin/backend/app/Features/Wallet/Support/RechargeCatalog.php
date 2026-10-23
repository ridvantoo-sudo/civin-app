<?php

namespace App\Features\Wallet\Support;

final class RechargeCatalog
{
    /**
     * @return list<array{id: string, name: string, coins: int, bonus_coins: int, price: int, currency: string, badge: ?string}>
     */
    public static function all(): array
    {
        $packages = config('civin.recharge_packages', []);

        return array_values(is_array($packages) ? $packages : []);
    }

    /**
     * @return array{id: string, name: string, coins: int, bonus_coins: int, price: int, currency: string, badge: ?string}|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $package) {
            if (($package['id'] ?? null) === $id) {
                return $package;
            }
        }

        return null;
    }

    /**
     * @param  array{coins: int, bonus_coins: int}  $package
     */
    public static function creditedCoins(array $package): int
    {
        return (int) $package['coins'] + (int) $package['bonus_coins'];
    }
}
