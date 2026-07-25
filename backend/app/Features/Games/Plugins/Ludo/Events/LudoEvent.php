<?php

namespace App\Features\Games\Plugins\Ludo\Events;

use App\Features\Games\Plugins\Ludo\Support\LudoBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class LudoEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly object $subject, public readonly array $payload = []) {}

    public function broadcastOn(): array
    {
        return LudoBroadcast::channelsFor($this->subject);
    }

    public function broadcastAs(): string
    {
        return 'ludo.'.str($this::class)->classBasename()->snake('.');
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->subject->getKey(), 'payload' => $this->payload];
    }
}
