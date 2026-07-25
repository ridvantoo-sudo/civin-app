<?php

namespace App\Features\Security\Services;

use App\Features\Security\Models\SecurityNetwork;
use App\Features\Security\Support\Fingerprint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SecurityNetworkService
{
    /**
     * Records network provenance for an account.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(?string $userId, ?string $ipAddress, array $attributes = []): ?SecurityNetwork
    {
        $ipHash = Fingerprint::ip($ipAddress);
        if ($ipHash === null) {
            return null;
        }

        return DB::transaction(function () use ($userId, $ipHash, $attributes): SecurityNetwork {
            /** @var SecurityNetwork $network */
            $network = SecurityNetwork::query()->firstOrCreate(
                ['ip_hash' => $ipHash, 'user_id' => $userId],
                ['first_seen_at' => CarbonImmutable::now()],
            );

            $network->asn = $this->text($attributes['asn'] ?? $network->asn, 32);
            $network->organization = $this->text($attributes['organization'] ?? $network->organization, 128);
            $network->country_code = $this->upper($attributes['country_code'] ?? $network->country_code, 8);
            $network->region = $this->text($attributes['region'] ?? $network->region, 96);
            $network->is_vpn = $this->flag($attributes['is_vpn'] ?? null, $network->is_vpn);
            $network->is_proxy = $this->flag($attributes['is_proxy'] ?? null, $network->is_proxy);
            $network->is_tor = $this->flag($attributes['is_tor'] ?? null, $network->is_tor);
            $network->is_hosting = $this->flag($attributes['is_hosting'] ?? null, $network->is_hosting);

            if (isset($attributes['latency_ms']) && is_numeric($attributes['latency_ms'])) {
                $network->latency_ms = (int) $attributes['latency_ms'];
            }
            if (isset($attributes['packet_frequency']) && is_numeric($attributes['packet_frequency'])) {
                $network->packet_frequency = (float) $attributes['packet_frequency'];
            }

            $network->distinct_users = (int) SecurityNetwork::query()
                ->where('ip_hash', $ipHash)
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');

            $network->risk_score = $this->scoreFor($network);
            $network->last_seen_at = CarbonImmutable::now();
            $network->save();

            return $network;
        }, 3);
    }

    private function scoreFor(SecurityNetwork $network): float
    {
        $score = 0.0;
        $score += $network->is_tor ? 45.0 : 0.0;
        $score += $network->is_proxy ? 25.0 : 0.0;
        $score += $network->is_vpn ? 20.0 : 0.0;
        $score += $network->is_hosting ? 15.0 : 0.0;

        $limit = (int) config('security_platform.thresholds.network.max_users_per_ip', 8);
        if ($network->distinct_users > $limit) {
            $score += min(30.0, ($network->distinct_users - $limit) * 4.0);
        }

        return round(min(100.0, $score), 2);
    }

    private function text(mixed $value, int $length): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    private function upper(mixed $value, int $length): ?string
    {
        $text = $this->text($value, $length);

        return $text === null ? null : mb_strtoupper($text);
    }

    /**
     * A freshly created row has not round-tripped through the database yet, so
     * the previous value may legitimately be null.
     */
    private function flag(mixed $value, ?bool $fallback): bool
    {
        $fallback ??= false;

        if ($value === null) {
            return $fallback;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }
}
