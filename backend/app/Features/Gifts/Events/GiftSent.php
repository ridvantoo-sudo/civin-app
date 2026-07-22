<?php

namespace App\Features\Gifts\Events;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GiftSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly GiftTransaction $transaction) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("live.room.{$this->transaction->room_id}")];
    }

    public function broadcastAs(): string
    {
        return 'gift.sent';
    }

    public function broadcastWith(): array
    {
        $transaction = $this->transaction->loadMissing([
            'sender.profile', 'sender.socialStatus',
            'receiver.profile', 'receiver.socialStatus',
            'gift.category',
        ]);

        $gift = $transaction->gift;
        $animation = $gift->animation();

        return [
            'room_id' => $transaction->room_id,
            'transaction_id' => $transaction->id,
            'sender' => (new SocialUserResource($transaction->sender))->resolve(),
            'receiver' => (new SocialUserResource($transaction->receiver))->resolve(),
            'gift' => [
                'id' => $gift->id,
                'name' => $gift->name,
                'icon' => $gift->icon,
                'animation_url' => $gift->animation_url,
                'coin_price' => $gift->coin_price,
                'category' => $gift->category === null ? null : [
                    'id' => $gift->category->id,
                    'name' => $gift->category->name,
                    'icon' => $gift->category->icon,
                ],
            ],
            'quantity' => $transaction->quantity,
            'animation' => $animation->toArray(),
            'coins' => $transaction->coins,
            'created_at' => $transaction->created_at?->toISOString(),
        ];
    }
}
