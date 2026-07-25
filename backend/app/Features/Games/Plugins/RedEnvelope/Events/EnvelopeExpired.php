<?php

namespace App\Features\Games\Plugins\RedEnvelope\Events;

use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Plugins\RedEnvelope\Support\RedEnvelopeBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EnvelopeExpired implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RedEnvelope $envelope,
        public readonly int $refundedAmount = 0,
    ) {}

    public function broadcastOn(): array
    {
        return RedEnvelopeBroadcast::channelsFor($this->envelope);
    }

    public function broadcastAs(): string
    {
        return 'red_envelope.expired';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return array_merge(RedEnvelopeBroadcast::envelopePayload($this->envelope), [
            'refunded_amount' => $this->refundedAmount,
        ]);
    }
}
