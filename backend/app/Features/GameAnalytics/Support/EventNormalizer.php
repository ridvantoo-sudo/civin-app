<?php

namespace App\Features\GameAnalytics\Support;

use App\Features\GameAnalytics\DTO\NormalizedAnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Best-effort, assumption-light normalizer for arbitrary domain events.
 */
final class EventNormalizer
{
    /**
     * @param  array<int, mixed>  $payload
     */
    public function normalize(string $eventName, array $payload = []): ?NormalizedAnalyticsEvent
    {
        if ($this->shouldIgnore($eventName)) {
            return null;
        }

        $primary = $payload[0] ?? null;
        $bag = $this->flatten($primary);
        $bag = array_merge($bag, $this->flatten($payload));

        $shortName = class_exists($eventName)
            ? class_basename($eventName)
            : (string) Str::of($eventName)->afterLast('\\')->afterLast('.');

        $source = $this->inferSource($eventName, $bag);
        $category = $this->inferCategory($eventName, $shortName, $bag);
        $occurredAt = $this->extractTimestamp($bag) ?? CarbonImmutable::now();

        $userId = $this->firstUuid($bag, [
            'user_id', 'userId', 'player_id', 'playerId', 'sender_id', 'senderId',
            'host_id', 'hostId', 'winner_id', 'winnerId',
        ]);
        $gameId = $this->firstUuid($bag, ['game_id', 'gameId']);
        $gameSlug = $this->firstString($bag, ['game_slug', 'gameSlug', 'slug', 'plugin', 'game']);
        $roomId = $this->firstUuid($bag, ['room_id', 'roomId', 'live_room_id', 'voice_room_id', 'match_id', 'matchId', 'session_id', 'sessionId']);
        $roomType = $this->firstString($bag, ['room_type', 'roomType']);
        if ($roomType === null && isset($bag['voice_room_id'])) {
            $roomType = 'voice';
        } elseif ($roomType === null && isset($bag['live_room_id'])) {
            $roomType = 'live';
        }

        $countryCode = $this->firstString($bag, ['country_code', 'countryCode', 'country']);
        $devicePlatform = $this->firstString($bag, ['device_platform', 'devicePlatform', 'platform', 'os']);

        $coinsWagered = $this->firstDecimal($bag, [
            'coins_wagered', 'coinsWagered', 'bet', 'bet_coins', 'betCoins', 'wager', 'stake', 'ticket_price', 'cost', 'coins',
        ]);
        $coinsWon = $this->firstDecimal($bag, [
            'coins_won', 'coinsWon', 'reward', 'reward_coins', 'rewardCoins', 'payout', 'prize', 'win_amount',
        ]);
        $coinsBurned = $this->firstDecimal($bag, ['coins_burned', 'coinsBurned', 'burned']);
        $diamonds = $this->firstDecimal($bag, ['diamonds_consumed', 'diamondsConsumed', 'diamonds', 'diamond_cost']);
        $revenue = $this->firstDecimal($bag, ['revenue', 'gross_revenue', 'amount', 'total_coins', 'price']);

        if ($revenue === '0.0000' && $coinsWagered !== '0.0000' && $this->looksLikeSpend($shortName)) {
            $revenue = $coinsWagered;
        }

        $safePayload = $this->redact($bag);
        $idempotencyKey = $this->buildIdempotencyKey($eventName, $shortName, $bag, $occurredAt);

        return new NormalizedAnalyticsEvent(
            eventName: Str::snake($shortName),
            source: $source,
            category: $category,
            idempotencyKey: $idempotencyKey,
            userId: $userId,
            gameId: $gameId,
            gameSlug: $gameSlug !== null ? Str::slug($gameSlug) : $this->inferGameSlug($eventName),
            roomType: $roomType,
            roomId: $roomId,
            countryCode: $countryCode,
            devicePlatform: $devicePlatform,
            coinsWagered: $coinsWagered,
            coinsWon: $coinsWon,
            coinsBurned: $coinsBurned,
            diamondsConsumed: $diamonds,
            revenue: $revenue,
            payload: $safePayload,
            metadata: [
                'original_event' => $eventName,
                'normalized_at' => CarbonImmutable::now()->toIso8601String(),
            ],
            occurredAt: $occurredAt,
        );
    }

    private function shouldIgnore(string $eventName): bool
    {
        foreach (config('game_analytics.ignored_event_prefixes', []) as $prefix) {
            if (str_starts_with($eventName, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function flatten(mixed $value, string $prefix = '', int $depth = 0): array
    {
        if ($depth > 3 || $value === null) {
            return [];
        }

        if ($value instanceof Model) {
            try {
                $attrs = $value->getAttributes();
                $out = [];
                foreach ($attrs as $key => $attr) {
                    $out[$prefix === '' ? $key : "{$prefix}.{$key}"] = $attr;
                    if ($prefix === '') {
                        $out[$key] = $attr;
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
                $ref = new ReflectionClass($value);
                foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                    $propValue = $property->getValue($value);
                    $key = $prefix === '' ? $property->getName() : "{$prefix}.{$property->getName()}";
                    if (is_scalar($propValue) || $propValue === null) {
                        $out[$key] = $propValue;
                        $out[$property->getName()] = $propValue;
                    } elseif ($propValue instanceof Model || is_object($propValue) || is_array($propValue)) {
                        $out = array_merge($out, $this->flatten($propValue, $key, $depth + 1));
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

    /**
     * @param  array<string, mixed>  $bag
     */
    private function inferSource(string $eventName, array $bag): string
    {
        if (isset($bag['source']) && is_string($bag['source'])) {
            return $bag['source'];
        }

        if (str_contains($eventName, '\\Games\\')) {
            return 'games';
        }
        if (str_contains($eventName, '\\Wallet\\')) {
            return 'wallet';
        }
        if (str_contains($eventName, '\\Gifts\\')) {
            return 'gifts';
        }
        if (str_contains($eventName, '\\LiveStreaming\\') || str_contains($eventName, '\\LiveChat\\')) {
            return 'live';
        }
        if (str_contains($eventName, '\\VoiceRoom\\') || str_contains($eventName, '\\VoicePk')) {
            return 'voice';
        }
        if (str_contains($eventName, '\\PkBattle\\') || str_contains($eventName, '\\MultiPk')) {
            return 'pk';
        }
        if (str_contains($eventName, '\\Vip\\') || str_contains($eventName, '\\Svip\\')) {
            return 'vip';
        }
        if (str_contains($eventName, '\\Agency\\')) {
            return 'agency';
        }
        if (str_contains($eventName, '\\Ranking\\')) {
            return 'ranking';
        }
        if (str_contains($eventName, '\\Authentication\\')) {
            return 'auth';
        }

        return 'platform';
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function inferCategory(string $eventName, string $shortName, array $bag): string
    {
        if (isset($bag['category']) && is_string($bag['category'])) {
            return $bag['category'];
        }

        $lower = Str::lower($shortName);
        foreach (['join', 'start', 'finish', 'complete', 'reward', 'claim', 'leave', 'reconnect', 'view', 'open', 'abandon', 'cancel', 'win', 'loss', 'play', 'spin', 'bet'] as $token) {
            if (str_contains($lower, $token)) {
                return $token;
            }
        }

        return 'event';
    }

    private function inferGameSlug(string $eventName): ?string
    {
        $map = [
            'LuckyWheel' => 'lucky-wheel',
            'LuckyDice' => 'lucky-dice',
            'RedEnvelope' => 'red-envelope',
            'TreasureBox' => 'treasure-box',
            'SlotMachine' => 'slot-machine',
            'LuckyNumber' => 'lucky-number',
            'Quiz' => 'quiz',
            'RockPaperScissors' => 'rock-paper-scissors',
            'Ludo' => 'ludo',
        ];

        foreach ($map as $needle => $slug) {
            if (str_contains($eventName, $needle)) {
                return $slug;
            }
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
        if ($value === null) {
            return null;
        }

        return Str::isUuid($value) ? $value : null;
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
    private function firstDecimal(array $bag, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $bag)) {
                continue;
            }
            $value = $bag[$key];
            if (is_numeric($value)) {
                return number_format((float) $value, 4, '.', '');
            }
        }

        return '0.0000';
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function extractTimestamp(array $bag): ?CarbonImmutable
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

    private function looksLikeSpend(string $shortName): bool
    {
        $lower = Str::lower($shortName);

        return str_contains($lower, 'played')
            || str_contains($lower, 'purchased')
            || str_contains($lower, 'bet')
            || str_contains($lower, 'spin')
            || str_contains($lower, 'sent')
            || str_contains($lower, 'joined');
    }

    /**
     * @param  array<string, mixed>  $bag
     * @return array<string, mixed>
     */
    private function redact(array $bag): array
    {
        $blocked = array_map('strtolower', config('game_analytics.redacted_payload_keys', []));
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

        // Cap payload size for storage.
        return array_slice($out, 0, 80, true);
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function buildIdempotencyKey(string $eventName, string $shortName, array $bag, CarbonImmutable $occurredAt): string
    {
        $parts = [
            $eventName,
            $this->firstString($bag, ['id', 'uuid', 'transaction_id', 'spin_id', 'bet_id', 'match_id', 'session_id']) ?? '',
            $this->firstString($bag, ['user_id', 'userId', 'player_id']) ?? '',
            $occurredAt->format('YmdHis'),
        ];

        return hash('sha256', implode('|', $parts));
    }
}
