<?php

namespace App\Features\LiveShopping\Events;

use App\Features\LiveShopping\Models\LiveProductPin;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LiveProductUnpinned implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LiveProductPin $pin) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('live.room.'.$this->pin->room_id)];
    }

    public function broadcastAs(): string
    {
        return 'shopping.product.unpinned';
    }

    public function broadcastWith(): array
    {
        return [
            'pin_id' => $this->pin->id,
            'room_id' => $this->pin->room_id,
            'product_id' => $this->pin->product_id,
            'is_pinned' => false,
        ];
    }
}
