<?php

namespace App\Features\Global\Services;

use App\Features\Global\Models\CdnConfig;
use Illuminate\Validation\ValidationException;

final readonly class CdnService
{
    /**
     * @return array<string, mixed>
     */
    public function config(?string $countryCode = null): array
    {
        $row = CdnConfig::query()->where('key', 'default')->where('active', true)->first();

        $base = $row?->base_url ?: (string) config('global.cdn.base_url');
        $public = $row?->public_base_url ?: (string) config('global.cdn.public_base_url', $base);
        $ttl = $row?->cache_ttl_seconds ?? (int) config('global.cdn.cache_ttl_seconds', 86400);
        $image = $row?->image_params ?: (array) config('global.cdn.image', []);
        $prefixes = $row?->prefixes ?: (array) config('global.cdn.prefixes', []);
        $edges = $row?->edge_map ?: (array) config('global.cdn.edge_map', []);

        $country = $countryCode !== null ? strtoupper($countryCode) : 'DEFAULT';
        $edge = $edges[$country] ?? $edges['DEFAULT'] ?? null;

        return [
            'base_url' => rtrim($base, '/'),
            'public_base_url' => rtrim($public, '/'),
            'cache_ttl_seconds' => (int) $ttl,
            'image' => $image,
            'prefixes' => $prefixes,
            'edge' => $edge,
            'edge_map' => $edges,
        ];
    }

    /**
     * @return array{url: string, path: string, base_url: string}
     */
    public function resolve(string $path, ?string $countryCode = null, bool $imageOptimize = false): array
    {
        $path = trim($path);
        if ($path === '') {
            throw ValidationException::withMessages(['path' => 'Path is required.']);
        }

        // Already absolute CDN/http URL.
        if (preg_match('#^https?://#i', $path) === 1) {
            return [
                'url' => $path,
                'path' => $path,
                'base_url' => parse_url($path, PHP_URL_SCHEME).'://'.parse_url($path, PHP_URL_HOST),
            ];
        }

        $cfg = $this->config($countryCode);
        $normalized = '/'.ltrim($path, '/');
        $url = $cfg['public_base_url'].$normalized;

        if ($imageOptimize) {
            $params = [];
            foreach ((array) $cfg['image'] as $key => $value) {
                $params[] = $key.'='.$value;
            }
            if ($params !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?').implode('&', $params);
            }
        }

        return [
            'url' => $url,
            'path' => $normalized,
            'base_url' => $cfg['public_base_url'],
            'edge' => $cfg['edge'],
        ];
    }
}
