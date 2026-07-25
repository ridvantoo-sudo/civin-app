<?php

namespace App\Features\Security\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FraudDetected implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $signals
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public readonly ?string $userId,
        public readonly string $surface,
        public readonly array $signals,
        public readonly float $confidence,
        public readonly array $evidence = [],
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel((string) config('security_platform.realtime.channel', 'security.monitor'))];

        if ($this->userId !== null) {
            $channels[] = new PrivateChannel('user.security.'.$this->userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'security.fraud_detected';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'surface' => $this->surface,
            'signals' => $this->signals,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
        ];
    }
}
