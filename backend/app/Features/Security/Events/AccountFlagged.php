<?php

namespace App\Features\Security\Events;

use App\Features\Security\Models\SecurityFlag;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AccountFlagged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SecurityFlag $flag) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel((string) config('security_platform.realtime.channel', 'security.monitor')),
            new PrivateChannel('user.security.'.$this->flag->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.account_flagged';
    }

    public function broadcastWith(): array
    {
        return [
            'flag_id' => $this->flag->id,
            'user_id' => $this->flag->user_id,
            'flag' => $this->flag->flag,
            'severity' => $this->flag->severity,
            'surface' => $this->flag->surface,
            'hit_count' => $this->flag->hit_count,
            'confidence' => (float) $this->flag->confidence,
        ];
    }
}
