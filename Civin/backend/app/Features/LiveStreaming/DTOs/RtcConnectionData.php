<?php

namespace App\Features\LiveStreaming\DTOs;

use DateTimeImmutable;

final readonly class RtcConnectionData
{
    public function __construct(
        public string $appId,
        public string $channel,
        public int $uid,
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {}

    public function toArray(): array
    {
        return [
            'app_id' => $this->appId,
            'channel' => $this->channel,
            'uid' => $this->uid,
            'token' => $this->token,
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
        ];
    }
}
