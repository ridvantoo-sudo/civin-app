<?php

namespace App\Features\Security\Support;

/**
 * Privacy-preserving hashing for every identifier the platform stores.
 *
 * We never persist a raw IP, device UUID or session token: only a salted
 * one-way digest that is stable enough to correlate abuse across requests.
 */
final class Fingerprint
{
    public const LENGTH = 64;

    public static function hash(?string $value, string $namespace = 'generic'): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return substr(hash_hmac('sha256', $namespace.'|'.$value, self::salt()), 0, self::LENGTH);
    }

    public static function device(?string $value): ?string
    {
        return self::hash($value, 'device');
    }

    public static function session(?string $value): ?string
    {
        return self::hash($value, 'session');
    }

    /**
     * IP hashing can be disabled for deployments whose compliance regime
     * requires raw addresses; the column is sized for either representation.
     */
    public static function ip(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (! (bool) config('security_platform.privacy.hash_ip_addresses', true)) {
            return substr($value, 0, 128);
        }

        return self::hash($value, 'ip');
    }

    /**
     * Builds a composite hash from ordered fingerprint components. Missing
     * components are skipped so a partial fingerprint still correlates.
     *
     * @param  array<string, mixed>  $components
     */
    public static function composite(array $components, string $namespace = 'composite'): ?string
    {
        $parts = [];
        ksort($components);
        foreach ($components as $key => $value) {
            if ($value === null || $value === '' || is_array($value) || is_object($value)) {
                continue;
            }
            $parts[] = $key.'='.(is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        if ($parts === []) {
            return null;
        }

        return self::hash(implode('&', $parts), $namespace);
    }

    private static function salt(): string
    {
        return (string) config('security_platform.privacy.hash_salt', 'civin-security');
    }
}
