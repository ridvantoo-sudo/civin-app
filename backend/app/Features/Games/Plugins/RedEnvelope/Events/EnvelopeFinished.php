<?php

namespace App\Features\Games\Plugins\RedEnvelope\Events;

use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Plugins\RedEnvelope\Support\RedEnvelopeBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Every slot has been claimed — the envelope is fully drained.
 */
final class EnvelopeFinished implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly RedEnvelope $envelope) {}

    public function broadcastOn(): array
    {
        return RedEnvelopeBroadcast::channelsFor($this->envelope);
    }

    public function broadcastAs(): string
    {
        return 'red_envelope.finished';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return RedEnvelopeBroadcast::envelopePayload($this->envelope);
    }
}
