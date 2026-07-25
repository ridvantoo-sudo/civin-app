<?php

namespace App\Features\Games\Plugins\Ludo\Support;

use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Ludo\Models\LudoTournament;
use Illuminate\Broadcasting\PrivateChannel;

final class LudoBroadcast
{
    public static function channelsFor(object $subject): array
    {
        if ($subject instanceof LudoMatch) {
            $channels = [new PrivateChannel("ludo.match.{$subject->getKey()}")];
            foreach ($subject->players()->whereNotNull('user_id')->pluck('user_id') as $userId) {
                $channels[] = new PrivateChannel("user.ludo.{$userId}");
            }
            if ($subject->room_id) {
                $channels[] = new PrivateChannel("room.ludo.{$subject->room_type}.{$subject->room_id}");
            }

            return $channels;
        }
        if ($subject instanceof LudoTournament) {
            return [new PrivateChannel('ludo.tournaments'), new PrivateChannel("ludo.tournament.{$subject->getKey()}")];
        }

        return [new PrivateChannel('ludo.leaderboard')];
    }
}
