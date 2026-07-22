<?php

namespace App\Features\Gifts\Actions;

use App\Features\Gifts\DTOs\SendGiftData;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Gifts\Services\GiftService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;

final readonly class SendGift
{
    public function __construct(private GiftService $gifts) {}

    public function execute(LiveRoom $room, User $sender, SendGiftData $data): GiftTransaction
    {
        return $this->gifts->send($room, $sender, $data);
    }
}
