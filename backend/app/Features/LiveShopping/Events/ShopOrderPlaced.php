<?php

namespace App\Features\LiveShopping\Events;

use App\Features\LiveShopping\Models\ShopOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ShopOrderPlaced implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ShopOrder $order) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.orders.'.$this->order->buyer_id),
            new PrivateChannel('user.orders.'.$this->order->seller_id),
        ];

        if ($this->order->room_id !== null) {
            $channels[] = new PrivateChannel('live.room.'.$this->order->room_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'shopping.order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'buyer_id' => $this->order->buyer_id,
            'seller_id' => $this->order->seller_id,
            'room_id' => $this->order->room_id,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'total_coins' => $this->order->total_coins,
        ];
    }
}
