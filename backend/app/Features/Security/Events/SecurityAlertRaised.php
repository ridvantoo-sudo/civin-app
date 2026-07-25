<?php

namespace App\Features\Security\Events;

use App\Features\Security\Models\SecurityAlert;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast as `security.alert` on the admin monitor channel and, when the
 * alert concerns a specific account, that user's private security channel.
 */
final class SecurityAlertRaised implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SecurityAlert $alert) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel($this->monitorChannel())];

        if ($this->alert->user_id !== null) {
            $channels[] = new PrivateChannel('user.security.'.$this->alert->user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'security.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alert->id,
            'user_id' => $this->alert->user_id,
            'signal' => $this->alert->signal,
            'rule_key' => $this->alert->rule_key,
            'title' => $this->alert->title,
            'severity' => $this->alert->severity,
            'surface' => $this->alert->surface,
            'action' => $this->alert->action,
            'status' => $this->alert->status,
            'confidence' => (float) $this->alert->confidence,
            'created_at' => $this->alert->created_at?->toISOString(),
        ];
    }

    private function monitorChannel(): string
    {
        return (string) config('security_platform.realtime.channel', 'security.monitor');
    }
}
