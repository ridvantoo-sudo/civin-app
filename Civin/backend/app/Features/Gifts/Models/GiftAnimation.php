<?php

namespace App\Features\Gifts\Models;

final readonly class GiftAnimation
{
    public function __construct(
        public string $giftId,
        public string $giftName,
        public ?string $url,
        public ?string $icon,
    ) {}

    public static function fromGift(Gift $gift): self
    {
        return new self(
            giftId: (string) $gift->getKey(),
            giftName: $gift->name,
            url: $gift->animation_url,
            icon: $gift->icon,
        );
    }

    /**
     * @return array{gift_id: string, gift_name: string, url: ?string, icon: ?string}
     */
    public function toArray(): array
    {
        return [
            'gift_id' => $this->giftId,
            'gift_name' => $this->giftName,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}
