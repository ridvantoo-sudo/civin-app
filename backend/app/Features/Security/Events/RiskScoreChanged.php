<?php

namespace App\Features\Security\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RiskScoreChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly float $previousRisk,
        public readonly float $riskScore,
        public readonly float $fraudScore,
        public readonly float $botScore,
        public readonly float $trustScore,
        public readonly string $band,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel((string) config('security_platform.realtime.channel', 'security.monitor')),
            new PrivateChannel('user.security.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.risk_score_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'previous_risk' => $this->previousRisk,
            'risk_score' => $this->riskScore,
            'fraud_score' => $this->fraudScore,
            'bot_score' => $this->botScore,
            'trust_score' => $this->trustScore,
            'band' => $this->band,
            'delta' => round($this->riskScore - $this->previousRisk, 4),
        ];
    }
}
