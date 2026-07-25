<?php

namespace App\Features\Security\DTO;

use Carbon\CarbonImmutable;

/**
 * Immutable, privacy-scrubbed view of an arbitrary domain event.
 *
 * This is the ONLY thing scanners and engines see. They never touch the
 * originating module's models, which is what keeps business logic untouched.
 */
final readonly class SecurityEventData
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventName,
        public string $source,
        public string $surface,
        public ?string $category,
        public ?string $idempotencyKey,
        public ?string $userId,
        public ?string $subjectId,
        public ?string $subjectType,
        public ?string $deviceHash,
        public ?string $sessionHash,
        public ?string $ipHash,
        public ?string $countryCode,
        public ?string $platform,
        public string $amount,
        public ?string $currency,
        public array $payload,
        public array $metadata,
        public CarbonImmutable $occurredAt,
    ) {}

    public function isIdentified(): bool
    {
        return $this->userId !== null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $this->metadata[$key] ?? $default;
    }

    public function numeric(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function amountAsFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistenceArray(): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey,
            'user_id' => $this->userId,
            'event' => mb_substr($this->eventName, 0, 96),
            'source' => mb_substr($this->source, 0, 64),
            'category' => $this->category !== null ? mb_substr($this->category, 0, 64) : null,
            'surface' => mb_substr($this->surface, 0, 32),
            'subject_id' => $this->subjectId,
            'subject_type' => $this->subjectType !== null ? mb_substr($this->subjectType, 0, 96) : null,
            'device_hash' => $this->deviceHash,
            'session_hash' => $this->sessionHash,
            'ip_hash' => $this->ipHash,
            'country_code' => $this->countryCode !== null ? mb_strtoupper(mb_substr($this->countryCode, 0, 8)) : null,
            'platform' => $this->platform !== null ? mb_strtolower(mb_substr($this->platform, 0, 32)) : null,
            'amount' => $this->amount,
            'currency' => $this->currency !== null ? mb_substr($this->currency, 0, 16) : null,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurredAt,
            'created_at' => CarbonImmutable::now(),
        ];
    }
}
