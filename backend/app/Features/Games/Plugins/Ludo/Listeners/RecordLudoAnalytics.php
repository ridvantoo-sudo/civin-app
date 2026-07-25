<?php

namespace App\Features\Games\Plugins\Ludo\Listeners;

use App\Features\Games\Models\Game;
use App\Features\Games\Plugins\Ludo\Events\LudoEvent;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Users\Models\User;

final readonly class RecordLudoAnalytics
{
    public function __construct(private GameRepository $games) {}

    public function handle(LudoEvent $event): void
    {
        $game = Game::query()->where('slug', Game::SLUG_LUDO)->first();
        $subject = $event->subject;
        $actor = $subject instanceof LudoMatch ? User::query()->find($subject->host_id) : null;
        $this->games->trackAnalytics($actor, $game, str($event::class)->classBasename()->snake()->prepend('ludo_')->toString(), [
            'subject_id' => (string) $subject->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'payload' => $event->payload,
        ]);
    }
}
