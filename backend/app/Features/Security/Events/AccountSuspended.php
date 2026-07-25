<?php

namespace App\Features\Security\Events;

use App\Features\Security\Models\SecurityBan;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AccountSuspended implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SecurityBan $ban) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel((string) config('security_platform.realtime.channel', 'security.monitor')),
            new PrivateChannel('user.security.'.$this->ban->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.account_suspended';
    }

    public function broadcastWith(): array
    {
        return [
            'ban_id' => $this->ban->id,
            'user_id' => $this->ban->user_id,
            'scope' => $this->ban->scope,
            'action' => $this->ban->action,
            'reason' => $this->ban->reason,
            'rule_key' => $this->ban->rule_key,
            'is_automatic' => $this->ban->is_automatic,
            'expires_at' => $this->ban->expires_at?->toISOString(),
        ];
    }
}
