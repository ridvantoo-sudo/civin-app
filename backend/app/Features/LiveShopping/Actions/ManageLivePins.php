<?php

namespace App\Features\LiveShopping\Actions;

use App\Features\LiveShopping\DTOs\PinProductData;
use App\Features\LiveShopping\Models\LiveProductPin;
use App\Features\LiveShopping\Services\LiveShoppingService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class ManageLivePins
{
    public function __construct(private LiveShoppingService $shopping) {}

    public function list(LiveRoom $room): Collection
    {
        return $this->shopping->roomPins($room);
    }

    public function pin(User $host, LiveRoom $room, PinProductData $data): LiveProductPin
    {
        return $this->shopping->pin($host, $room, $data);
    }

    public function unpin(User $host, LiveRoom $room, LiveProductPin $pin): LiveProductPin
    {
        return $this->shopping->unpin($host, $room, $pin);
    }

    public function update(User $host, LiveRoom $room, LiveProductPin $pin, PinProductData $data): LiveProductPin
    {
        return $this->shopping->updatePin($host, $room, $pin, $data);
    }
}
