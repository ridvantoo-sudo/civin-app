<?php

namespace App\Features\Games\Plugins\RedEnvelope\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeClaimed;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeCreated;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeExpired;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeFinished;
use App\Features\Games\Repositories\Contracts\GameRepository;

final class RecordRedEnvelopeAnalytics
{
    public function __construct(private readonly GameRepository $games) {}

    public function handle(EnvelopeCreated|EnvelopeClaimed|EnvelopeExpired|EnvelopeFinished $event): void
    {
        $envelope = $event->envelope;
        $game = $envelope->game_id !== null
            ? Game::query()->find($envelope->game_id)
            : Game::query()->where('slug', Game::SLUG_RED_ENVELOPE)->first();

        $actor = $event instanceof EnvelopeClaimed
            ? $event->claim->user
            : ($envelope->creator ?? $envelope->sender);

        $eventName = match (true) {
            $event instanceof EnvelopeClaimed => 'red_envelope_claimed',
            $event instanceof EnvelopeExpired => 'red_envelope_expired',
            $event instanceof EnvelopeFinished => 'red_envelope_finished',
            default => 'red_envelope_created',
        };

        $payload = [
            'envelope_id' => $envelope->id,
            'creator_id' => $envelope->ownerId(),
            'reward_type' => $envelope->rewardType(),
            'distribution_type' => $envelope->distribution_type,
            'visibility' => $envelope->visibility,
            'claim_scope' => $envelope->claim_scope,
            'room_type' => $envelope->room_type,
            'room_id' => $envelope->room_id,
            'total_amount' => (int) ($envelope->total_amount ?? $envelope->total_coins ?? 0),
            'remaining_amount' => $envelope->amountLeft(),
            'winner_count' => (int) ($envelope->winner_count ?? $envelope->total_slots ?? 0),
            'remaining_count' => $envelope->slotsLeft(),
            'status' => $envelope->status,
        ];

        if ($event instanceof EnvelopeClaimed) {
            $payload['claim_id'] = $event->claim->id;
            $payload['reward_amount'] = $event->claim->rewardAmount();
        }

        if ($event instanceof EnvelopeExpired) {
            $payload['refunded_amount'] = $event->refundedAmount;
        }

        $this->games->trackAnalytics($actor, $game, $eventName, $payload);
    }
}
