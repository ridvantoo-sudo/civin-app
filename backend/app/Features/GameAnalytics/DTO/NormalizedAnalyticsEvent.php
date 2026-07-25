<?php

namespace App\Features\GameAnalytics\DTO;

use Carbon\CarbonImmutable;

/**
 * Immutable normalized analytics event extracted from arbitrary domain events.
 */
final readonly class NormalizedAnalyticsEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventName,
        public string $source,
        public ?string $category,
        public ?string $idempotencyKey,
        public ?string $userId,
        public ?string $gameId,
        public ?string $gameSlug,
        public ?string $roomType,
        public ?string $roomId,
        public ?string $countryCode,
        public ?string $devicePlatform,
        public string $coinsWagered,
        public string $coinsWon,
        public string $coinsBurned,
        public string $diamondsConsumed,
        public string $revenue,
        public array $payload,
        public array $metadata,
        public CarbonImmutable $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPersistenceArray(): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey,
            'user_id' => $this->userId,
            'game_id' => $this->gameId,
            'game_slug' => $this->gameSlug,
            'event' => mb_substr($this->eventName, 0, 64),
            'source' => mb_substr($this->source, 0, 64),
            'category' => $this->category !== null ? mb_substr($this->category, 0, 64) : null,
            'room_type' => $this->roomType,
            'room_id' => $this->roomId,
            'country_code' => $this->countryCode !== null ? mb_strtoupper(mb_substr($this->countryCode, 0, 8)) : null,
            'device_platform' => $this->devicePlatform !== null ? mb_strtolower(mb_substr($this->devicePlatform, 0, 32)) : null,
            'coins_wagered' => $this->coinsWagered,
            'coins_won' => $this->coinsWon,
            'coins_burned' => $this->coinsBurned,
            'diamonds_consumed' => $this->diamondsConsumed,
            'revenue' => $this->revenue,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
            'created_at' => $this->occurredAt,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
