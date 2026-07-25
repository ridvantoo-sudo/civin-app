<?php

namespace App\Features\Security\Support;

use App\Features\Security\DTO\SecurityEventData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Turns an arbitrary domain event into a SecurityEventData.
 *
 * Assumption-light by design: it reflects over whatever the event carries and
 * never requires a module to expose anything specific. That is what lets the
 * platform observe all of Civin without a single change to those modules.
 */
final class SecurityEventNormalizer
{
    private const MAX_DEPTH = 3;

    /**
     * @param  array<int, mixed>  $payload
     */
    public function normalize(string $eventName, array $payload = []): ?SecurityEventData
    {
        if ($this->shouldIgnore($eventName)) {
            return null;
        }

        $bag = $this->flatten($payload[0] ?? null);
        $bag = array_merge($bag, $this->flatten($payload));

        if ($bag === []) {
            $bag = ['event' => $eventName];
        }

        $shortName = class_exists($eventName)
            ? class_basename($eventName)
            : (string) Str::of($eventName)->afterLast('\\')->afterLast('.');

        $occurredAt = $this->timestamp($bag) ?? CarbonImmutable::now();
        $surface = SecuritySurface::fromEventName($eventName);

        // Request-scoped context enriches the event without the emitting
        // module having to pass anything.
        $request = $this->currentRequest();

        $deviceHash = Fingerprint::device($this->firstString($bag, [
            'device_id', 'deviceId', 'device_uuid', 'deviceUuid',
        ])) ?? Fingerprint::device($this->headerValue($request, 'X-Device-Id'));

        $sessionHash = Fingerprint::session($this->firstString($bag, [
            'session_id', 'sessionId', 'session_hash',
        ])) ?? Fingerprint::session($this->headerValue($request, 'X-Session-Id'));

        $ipHash = Fingerprint::ip($this->firstString($bag, ['ip', 'ip_address', 'ipAddress']))
            ?? Fingerprint::ip($request?->ip());

        $payloadBag = $this->redact($bag);
        if ($request !== null) {
            $payloadBag = array_merge($payloadBag, $this->requestSignals($request));
        }

        return new SecurityEventData(
            eventName: Str::snake($shortName),
            source: $this->source($eventName),
            surface: $surface,
            category: $this->category($shortName),
            idempotencyKey: $this->idempotencyKey($eventName, $bag, $occurredAt),
            userId: $this->firstUuid($bag, [
                'user_id', 'userId', 'player_id', 'playerId',
                'sender_id', 'senderId', 'host_id', 'hostId',
            ]),
            subjectId: $this->firstUuid($bag, [
                'id', 'uuid', 'transaction_id', 'play_id', 'spin_id', 'bet_id',
                'match_id', 'session_id', 'round_id', 'battle_id', 'ticket_id',
            ]),
            subjectType: $this->subjectType($payload[0] ?? null),
            deviceHash: $deviceHash,
            sessionHash: $sessionHash,
            ipHash: $ipHash,
            countryCode: $this->firstString($bag, ['country_code', 'countryCode', 'country']),
            platform: $this->firstString($bag, ['platform', 'device_platform', 'os'])
                ?? $this->headerValue($request, 'X-Platform'),
            amount: $this->decimal($bag, [
                'amount', 'coins', 'bet_coins', 'reward_coins', 'total_coins',
                'price', 'value', 'diamonds',
            ]),
            currency: $this->firstString($bag, ['currency']),
            payload: $payloadBag,
            metadata: [
                'original_event' => $eventName,
                'normalized_at' => CarbonImmutable::now()->toIso8601String(),
            ],
            occurredAt: $occurredAt,
        );
    }

    private function shouldIgnore(string $eventName): bool
    {
        foreach ((array) config('security_platform.ignored_event_prefixes', []) as $prefix) {
            if (str_starts_with($eventName, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Client attestation travels on headers so no existing controller has to
     * change to enable device-aware detection.
     *
     * @return array<string, mixed>
     */
    private function requestSignals(Request $request): array
    {
        $flags = [
            'is_emulator' => 'X-Device-Emulator',
            'is_rooted' => 'X-Device-Rooted',
            'is_jailbroken' => 'X-Device-Jailbroken',
            'is_debug' => 'X-App-Debug',
            'is_tampered' => 'X-App-Tampered',
            'is_vpn' => 'X-Network-Vpn',
            'is_proxy' => 'X-Network-Proxy',
            'is_tor' => 'X-Network-Tor',
            'is_mock_location' => 'X-Location-Mock',
        ];

        $out = [];
        foreach ($flags as $key => $header) {
            $value = $request->header($header);
            if ($value !== null) {
                $out[$key] = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        foreach (['hardware_hash' => 'X-Device-Hardware', 'app_version' => 'X-App-Version', 'nonce' => 'X-Request-Nonce'] as $key => $header) {
            $value = $request->header($header);
            if (is_string($value) && $value !== '') {
                $out[$key] = mb_substr($value, 0, 191);
            }
        }

        if ((bool) config('security_platform.privacy.store_user_agent', true)) {
            $agent = $request->userAgent();
            if (is_string($agent) && $agent !== '') {
                $out['user_agent'] = mb_substr($agent, 0, 191);
            }
        }

        return $out;
    }

    private function currentRequest(): ?Request
    {
        try {
            if (! app()->bound('request')) {
                return null;
            }
            $request = app('request');

            return $request instanceof Request ? $request : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function headerValue(?Request $request, string $header): ?string
    {
        $value = $request?->header($header);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function source(string $eventName): string
    {
        if (! str_contains($eventName, '\\Features\\')) {
            return 'platform';
        }

        $after = Str::of($eventName)->after('\\Features\\')->before('\\')->toString();

        return $after === '' ? 'platform' : Str::snake($after);
    }

    private function category(string $shortName): string
    {
        $lower = Str::lower($shortName);

        foreach ([
            'created', 'started', 'finished', 'completed', 'rewarded', 'claimed',
            'joined', 'left', 'sent', 'updated', 'deleted', 'registered',
            'login', 'won', 'lost', 'played', 'spin', 'purchased',
        ] as $token) {
            if (str_contains($lower, $token)) {
                return $token;
            }
        }

        return 'event';
    }

    /**
     * @return array<string, mixed>
     */
    private function flatten(mixed $value, string $prefix = '', int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH || $value === null) {
            return [];
        }

        if ($value instanceof Model) {
            try {
                $out = [];
                foreach ($value->getAttributes() as $key => $attribute) {
                    if (! is_scalar($attribute) && $attribute !== null) {
                        continue;
                    }
                    $out[$prefix === '' ? $key : "{$prefix}.{$key}"] = $attribute;
                    if ($prefix === '') {
                        $out[$key] = $attribute;
                    }
                }

                return $out;
            } catch (Throwable) {
                return [];
            }
        }

        if (is_object($value)) {
            $out = [];
            try {
                foreach ((new ReflectionClass($value))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                    $propertyValue = $property->getValue($value);
                    $key = $prefix === '' ? $property->getName() : "{$prefix}.{$property->getName()}";

                    if (is_scalar($propertyValue) || $propertyValue === null) {
                        $out[$key] = $propertyValue;
                        $out[$property->getName()] = $propertyValue;
                    } else {
                        $out = array_merge($out, $this->flatten($propertyValue, $key, $depth + 1));
                    }
                }
            } catch (Throwable) {
                return $out;
            }

            return $out;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_int($key)) {
                    $out = array_merge($out, $this->flatten($item, $prefix, $depth + 1));

                    continue;
                }

                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
                if (is_scalar($item) || $item === null) {
                    $out[$path] = $item;
                    $out[(string) $key] = $item;
                } else {
                    $out = array_merge($out, $this->flatten($item, $path, $depth + 1));
                }
            }

            return $out;
        }

        return [];
    }

    private function subjectType(mixed $subject): ?string
    {
        if ($subject instanceof Model) {
            return class_basename($subject);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $bag
     * @param  list<string>  $keys
     */
    private function firstUuid(array $bag, array $keys): ?string
    {
        $value = $this->firstString($bag, $keys);

        return $value !== null && Str::isUuid($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $bag
     * @param  list<string>  $keys
     */
    private function firstString(array $bag, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $bag)) {
                continue;
            }
            $value = $bag[$key];
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $bag
     * @param  list<string>  $keys
     */
    private function decimal(array $bag, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $bag) && is_numeric($bag[$key])) {
                return number_format((float) $bag[$key], 4, '.', '');
            }
        }

        return '0.0000';
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function timestamp(array $bag): ?CarbonImmutable
    {
        foreach (['occurred_at', 'occurredAt', 'created_at', 'createdAt', 'finished_at', 'started_at', 'timestamp'] as $key) {
            if (! isset($bag[$key]) || blank($bag[$key])) {
                continue;
            }
            try {
                return CarbonImmutable::parse((string) $bag[$key]);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $bag
     * @return array<string, mixed>
     */
    private function redact(array $bag): array
    {
        $blocked = array_map('strtolower', (array) config('security_platform.redacted_payload_keys', []));
        $limit = (int) config('security_platform.privacy.max_payload_keys', 60);

        $out = [];
        foreach ($bag as $key => $value) {
            $lower = strtolower((string) $key);
            $leaf = strtolower((string) Str::of((string) $key)->afterLast('.'));

            if (in_array($lower, $blocked, true) || in_array($leaf, $blocked, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[(string) $key] = $value;
            }
        }

        return array_slice($out, 0, $limit, true);
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function idempotencyKey(string $eventName, array $bag, CarbonImmutable $occurredAt): string
    {
        return hash('sha256', implode('|', [
            $eventName,
            $this->firstString($bag, ['id', 'uuid', 'transaction_id', 'play_id', 'spin_id', 'match_id']) ?? '',
            $this->firstString($bag, ['user_id', 'userId', 'player_id']) ?? '',
            $occurredAt->format('YmdHis'),
        ]));
    }
}
