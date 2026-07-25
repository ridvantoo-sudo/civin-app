<?php

namespace App\Features\LiveShopping\Events;

use App\Features\LiveShopping\Models\LiveProductPin;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LiveProductPinned implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LiveProductPin $pin) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('live.room.'.$this->pin->room_id)];
    }

    public function broadcastAs(): string
    {
        return 'shopping.product.pinned';
    }

    public function broadcastWith(): array
    {
        $pin = $this->pin->loadMissing('product');
        $product = $pin->product;

        return [
            'pin_id' => $pin->id,
            'room_id' => $pin->room_id,
            'is_pinned' => (bool) $pin->is_pinned,
            'flash_deal_price' => $pin->flash_deal_price,
            'flash_deal_ends_at' => $pin->flash_deal_ends_at?->toISOString(),
            'effective_price' => $pin->effectivePrice(),
            'has_flash_deal' => $pin->hasActiveFlashDeal(),
            'sort_order' => $pin->sort_order,
            'product' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'image_url' => $product->image_url,
                'price_coins' => $product->price_coins,
                'stock' => $product->stock,
                'seller_id' => $product->seller_id,
            ],
        ];
    }
}
