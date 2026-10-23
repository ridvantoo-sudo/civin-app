<?php

namespace App\Features\Vip\DTOs;

final readonly class VipPrivilegesData
{
    public function __construct(
        public ?string $badge,
        public ?string $profileFrame,
        public ?string $chatEffect,
        public ?string $entranceAnimation,
        public bool $exclusiveGifts,
    ) {}

    public static function fromLevelPrivileges(array $privileges): self
    {
        return new self(
            badge: $privileges['badge'] ?? null,
            profileFrame: $privileges['profile_frame'] ?? null,
            chatEffect: $privileges['chat_effect'] ?? null,
            entranceAnimation: $privileges['entrance_animation'] ?? null,
            exclusiveGifts: (bool) ($privileges['exclusive_gifts'] ?? false),
        );
    }

    /** @return array{badge: ?string, profile_frame: ?string, chat_effect: ?string, entrance_animation: ?string, exclusive_gifts: bool} */
    public function toArray(): array
    {
        return [
            'badge' => $this->badge,
            'profile_frame' => $this->profileFrame,
            'chat_effect' => $this->chatEffect,
            'entrance_animation' => $this->entranceAnimation,
            'exclusive_gifts' => $this->exclusiveGifts,
        ];
    }
}
