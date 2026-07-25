<?php

namespace App\Features\Svip\DTOs;

final readonly class SvipPrivilegesData
{
    public function __construct(
        public ?string $badge,
        public ?string $animatedProfile,
        public ?string $profileFrame,
        public ?string $chatEffect,
        public ?string $entranceAnimation,
        public bool $exclusiveGifts,
        public bool $exclusiveFrames,
        public bool $specialPrivileges,
    ) {}

    public static function fromLevelPrivileges(array $privileges): self
    {
        return new self(
            badge: $privileges['badge'] ?? null,
            animatedProfile: $privileges['animated_profile'] ?? null,
            profileFrame: $privileges['profile_frame'] ?? null,
            chatEffect: $privileges['chat_effect'] ?? null,
            entranceAnimation: $privileges['entrance_animation'] ?? null,
            exclusiveGifts: (bool) ($privileges['exclusive_gifts'] ?? false),
            exclusiveFrames: (bool) ($privileges['exclusive_frames'] ?? false),
            specialPrivileges: (bool) ($privileges['special_privileges'] ?? false),
        );
    }

    /**
     * @return array{
     *     badge: ?string,
     *     animated_profile: ?string,
     *     profile_frame: ?string,
     *     chat_effect: ?string,
     *     entrance_animation: ?string,
     *     exclusive_gifts: bool,
     *     exclusive_frames: bool,
     *     special_privileges: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'badge' => $this->badge,
            'animated_profile' => $this->animatedProfile,
            'profile_frame' => $this->profileFrame,
            'chat_effect' => $this->chatEffect,
            'entrance_animation' => $this->entranceAnimation,
            'exclusive_gifts' => $this->exclusiveGifts,
            'exclusive_frames' => $this->exclusiveFrames,
            'special_privileges' => $this->specialPrivileges,
        ];
    }
}
