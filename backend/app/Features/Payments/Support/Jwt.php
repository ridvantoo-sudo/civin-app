<?php

namespace App\Features\Payments\Support;

use RuntimeException;

/**
 * Minimal dependency-free HS256 JWT encoder/decoder used by gateways that
 * authenticate transactions with signed tokens (e.g. ZainCash).
 */
final class Jwt
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload, string $secret): string
    {
        $header = self::base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::base64UrlEncode((string) json_encode($payload));
        $signature = self::sign($header.'.'.$body, $secret);

        return $header.'.'.$body.'.'.$signature;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT.');
        }

        [$header, $body, $signature] = $parts;

        $expected = self::sign($header.'.'.$body, $secret);
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid JWT signature.');
        }

        $payload = json_decode((string) self::base64UrlDecode($body), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid JWT payload.');
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new RuntimeException('Expired JWT.');
        }

        return $payload;
    }

    private static function sign(string $data, string $secret): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
