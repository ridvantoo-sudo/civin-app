<?php

use App\Features\Games\Models\GameMatch;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\LiveChat\Models\LiveChatModerator;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', fn (User $user, string $userId): bool => $user->id === $userId);

Broadcast::channel('user.wallet.{userId}', fn (User $user, string $userId): bool => $user->id === $userId);

Broadcast::channel('user.games.{userId}', fn (User $user, string $userId): bool => $user->id === $userId);

Broadcast::channel('user.lucky-wheel.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

Broadcast::channel('user.slot-machine.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

Broadcast::channel('user.lucky-number.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

Broadcast::channel('user.quiz.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

Broadcast::channel('quiz.session.{sessionId}', function (User $user, string $sessionId): bool {
    if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
        return false;
    }

    $session = QuizSession::query()->find($sessionId);
    if ($session === null) {
        return false;
    }

    if ((string) $session->host_id === (string) $user->getKey()) {
        return true;
    }

    return $session->players()->where('user_id', $user->getKey())->exists();
});

Broadcast::channel(
    'room.quiz.{roomType}.{roomId}',
    fn (User $user, string $roomType, string $roomId): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest,
);

Broadcast::channel('quiz.tournaments', fn (User $user): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest);

Broadcast::channel('user.rps.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

// Only the two players may listen to a match channel. Round payloads still
// withhold move values until the round resolves, so this is defence in depth.
Broadcast::channel('rps.match.{matchId}', function (User $user, string $matchId): bool {
    if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
        return false;
    }

    $match = RpsMatch::query()->find($matchId);
    if ($match === null) {
        return false;
    }

    if ($match->roleFor((string) $user->getKey()) !== null) {
        return true;
    }

    return $match->visibility === 'public';
});

Broadcast::channel(
    'room.rps.{roomType}.{roomId}',
    fn (User $user, string $roomType, string $roomId): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest,
);

Broadcast::channel(
    'rps.tournament.{tournamentId}',
    fn (User $user, string $tournamentId): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest,
);

Broadcast::channel('rps.tournaments', fn (User $user): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest);

Broadcast::channel('rps.leaderboard', fn (User $user): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest);

Broadcast::channel('user.ludo.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);
Broadcast::channel('ludo.match.{matchId}', function (User $user, string $matchId): bool {
    $match = LudoMatch::query()->find($matchId);

    return $match !== null
        && $user->status === User::STATUS_ACTIVE
        && ! $user->is_guest
        && ($match->spectators_enabled || $match->players()->where('user_id', $user->getKey())->exists());
});
Broadcast::channel('ludo.tournaments', fn (User $user): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest);
Broadcast::channel('ludo.leaderboard', fn (User $user): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest);
Broadcast::channel('room.ludo.{roomType}.{roomId}', fn (User $user, string $roomType, string $roomId): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest);
Broadcast::channel('ludo.tournament.{id}', fn (User $user, string $id): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest);

Broadcast::channel('user.lucky-dice.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

Broadcast::channel('user.red-envelope.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

// Room envelopes fan out to whoever is watching that room. Room membership is
// owned by the Live/Voice modules, so this only gates on account standing.
Broadcast::channel(
    'room.red-envelope.{roomType}.{roomId}',
    fn (User $user, string $roomType, string $roomId): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest,
);

Broadcast::channel('user.treasure-box.{userId}', fn (User $user, string $userId): bool => (string) $user->getKey() === (string) $userId);

Broadcast::channel(
    'room.treasure-box.{roomType}.{roomId}',
    fn (User $user, string $roomType, string $roomId): bool => $user->status === User::STATUS_ACTIVE && ! $user->is_guest,
);

Broadcast::channel('game.match.{matchId}', function (User $user, string $matchId): bool {
    $match = GameMatch::query()->find($matchId);

    if ($match === null) {
        return false;
    }

    if ($match->host_user_id === $user->getKey()) {
        return true;
    }

    return $match->players()->where('user_id', $user->getKey())->exists();
});

Broadcast::channel('user.orders.{userId}', fn (User $user, string $userId): bool => $user->id === $userId);

Broadcast::channel('discovery.online', fn (User $user): bool => $user->status === User::STATUS_ACTIVE);

Broadcast::channel('live.room.{roomId}', function (User $user, string $roomId): bool {
    $room = LiveRoom::query()->find($roomId);

    if ($room === null) {
        return false;
    }

    if ($room->host_id === $user->getKey()) {
        return true;
    }

    if ($room->viewers()->where('user_id', $user->getKey())->whereNull('left_at')->exists()) {
        return true;
    }

    return LiveChatModerator::query()
        ->where('room_id', $roomId)
        ->where('user_id', $user->getKey())
        ->exists();
});

Broadcast::channel('voice.room.{roomId}', function (User $user, string $roomId): bool {
    $room = VoiceRoom::query()->find($roomId);

    if ($room === null) {
        return false;
    }

    if ($room->host_id === $user->getKey()) {
        return true;
    }

    return $room->participants()
        ->where('user_id', $user->getKey())
        ->whereNull('left_at')
        ->exists();
});
