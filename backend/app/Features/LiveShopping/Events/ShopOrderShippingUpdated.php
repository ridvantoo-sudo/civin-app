<?php

namespace App\Features\LiveShopping\Events;

use App\Features\LiveShopping\Models\ShopOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ShopOrderShippingUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ShopOrder $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.orders.'.$this->order->buyer_id),
            new PrivateChannel('user.orders.'.$this->order->seller_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shopping.order.shipping';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'tracking_number' => $this->order->tracking_number,
            'shipped_at' => $this->order->shipped_at?->toISOString(),
            'delivered_at' => $this->order->delivered_at?->toISOString(),
        ];
    }
}
