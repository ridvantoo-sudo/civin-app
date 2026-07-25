<?php

namespace App\Features\Levels\Events;

use App\Features\Levels\Models\UserBadge;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BadgeUnlocked implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly UserBadge $award) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->award->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'badge.unlocked';
    }

    public function broadcastWith(): array
    {
        $award = $this->award->loadMissing('badge');

        return [
            'user_id' => $award->user_id,
            'badge' => [
                'id' => $award->badge?->id,
                'code' => $award->badge?->code,
                'name' => $award->badge?->name,
                'description' => $award->badge?->description,
                'icon' => $award->badge?->icon,
                'animation_url' => $award->badge?->animation_url,
                'xp_reward' => $award->badge?->xp_reward,
            ],
            'awarded_at' => $award->awarded_at?->toISOString(),
        ];
    }
}
