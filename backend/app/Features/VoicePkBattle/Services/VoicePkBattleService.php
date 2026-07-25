<?php

namespace App\Features\VoicePkBattle\Services;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;
use App\Features\VoicePkBattle\Events\VoicePkAccepted;
use App\Features\VoicePkBattle\Events\VoicePkFinished;
use App\Features\VoicePkBattle\Events\VoicePkInvited;
use App\Features\VoicePkBattle\Events\VoicePkScoreUpdated;
use App\Features\VoicePkBattle\Events\VoicePkStarted;
use App\Features\VoicePkBattle\Jobs\ExpireVoicePkInvitation;
use App\Features\VoicePkBattle\Jobs\FinalizeVoicePkBattle;
use App\Features\VoicePkBattle\Models\VoicePkBattle;
use App\Features\VoicePkBattle\Models\VoicePkTeam;
use App\Features\VoiceRoom\Models\VoiceRoom;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class VoicePkBattleService
{
    public const GIFT_METADATA_TYPE = 'voice';

    public const GIFT_METADATA_TYPE_KEY = 'room_type';

    public const GIFT_METADATA_ROOM_KEY = 'voice_room_id';

    public function invite(
        VoiceRoom $initiatorRoom,
        VoiceRoom $opponentRoom,
        User $initiator,
        int $durationSeconds = VoicePkBattle::DEFAULT_DURATION_SECONDS,
        int $countdownSeconds = VoicePkBattle::DEFAULT_COUNTDOWN_SECONDS,
        int $invitationSeconds = VoicePkBattle::DEFAULT_INVITATION_SECONDS,
    ): VoicePkBattle {
        $this->validateTimings($durationSeconds, $countdownSeconds, $invitationSeconds);

        if ($initiatorRoom->is($opponentRoom)) {
            throw ValidationException::withMessages(['opponent_room' => 'A voice room cannot battle itself.']);
        }

        $battle = DB::transaction(function () use (
            $initiatorRoom,
            $opponentRoom,
            $initiator,
            $durationSeconds,
            $countdownSeconds,
            $invitationSeconds,
        ): VoicePkBattle {
            $roomIds = [$initiatorRoom->getKey(), $opponentRoom->getKey()];
            $rooms = VoiceRoom::query()->whereKey($roomIds)->lockForUpdate()->get()->keyBy('id');

            if ($rooms->count() !== 2 || $rooms->contains(fn (VoiceRoom $room) => ! $room->isLive())) {
                throw ValidationException::withMessages(['rooms' => 'Both voice rooms must be live.']);
            }
            if ($initiatorRoom->host_id !== $initiator->getKey()) {
                throw ValidationException::withMessages(['initiator' => 'Only the initiating room host may invite an opponent.']);
            }
            if ($rooms->pluck('host_id')->unique()->count() !== 2) {
                throw ValidationException::withMessages(['rooms' => 'The competing rooms must have different hosts.']);
            }

            $hasConflict = VoicePkTeam::query()
                ->whereIn('room_id', $roomIds)
                ->whereHas('battle', fn ($query) => $query->whereIn('status', VoicePkBattle::ACTIVE_STATUSES))
                ->exists();
            if ($hasConflict) {
                throw ValidationException::withMessages(['rooms' => 'A room already has an active voice PK battle.']);
            }

            $battle = VoicePkBattle::query()->create([
                'initiator_id' => $initiator->getKey(),
                'status' => VoicePkBattle::STATUS_WAITING,
                'duration_seconds' => $durationSeconds,
                'countdown_seconds' => $countdownSeconds,
                'invitation_expires_at' => now()->addSeconds($invitationSeconds),
            ]);

            foreach ($roomIds as $roomId) {
                $room = $rooms->get($roomId);
                $accepted = $roomId === $initiatorRoom->getKey();
                $battle->teams()->create([
                    'room_id' => $roomId,
                    'host_id' => $room->host_id,
                    'accepted' => $accepted,
                    'accepted_at' => $accepted ? now() : null,
                ]);
            }

            return $this->load($battle);
        });

        VoicePkInvited::dispatch($battle);
        ExpireVoicePkInvitation::dispatch($battle->getKey())->delay($battle->invitation_expires_at);

        return $battle;
    }

    public function accept(VoicePkBattle $battle, VoiceRoom $room, User $host): VoicePkBattle
    {
        $acceptedNow = false;
        $startedNow = false;
        $battle = DB::transaction(function () use ($battle, $room, $host, &$acceptedNow, &$startedNow): VoicePkBattle {
            $locked = VoicePkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
            if ($locked->status !== VoicePkBattle::STATUS_WAITING || now()->gte($locked->invitation_expires_at)) {
                throw ValidationException::withMessages(['battle' => 'This voice PK invitation is no longer available.']);
            }

            $team = $locked->teams()
                ->where('room_id', $room->getKey())
                ->where('host_id', $host->getKey())
                ->first();
            if ($team === null || $locked->initiator_id === $host->getKey()) {
                throw ValidationException::withMessages(['room' => 'Only the invited room host may accept.']);
            }

            if (! $team->accepted) {
                $team->update(['accepted' => true, 'accepted_at' => now()]);
                $acceptedNow = true;
            }

            $teams = $locked->teams()->get();
            if ($teams->count() === 2 && $teams->every->accepted) {
                $this->transitionToRunning($locked, $teams);
                $startedNow = true;
            }

            return $this->load($locked);
        });

        if ($acceptedNow) {
            VoicePkAccepted::dispatch($battle);
        }
        if ($startedNow) {
            VoicePkStarted::dispatch($battle);
            FinalizeVoicePkBattle::dispatch($battle->getKey())->delay($battle->ends_at);
        }

        return $battle;
    }

    public function start(VoicePkBattle $battle): VoicePkBattle
    {
        $startedNow = false;
        $battle = DB::transaction(function () use ($battle, &$startedNow): VoicePkBattle {
            $locked = VoicePkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
            if ($locked->status === VoicePkBattle::STATUS_RUNNING) {
                return $this->load($locked);
            }
            if ($locked->status !== VoicePkBattle::STATUS_WAITING || now()->gte($locked->invitation_expires_at)) {
                throw ValidationException::withMessages(['battle' => 'Only a current invitation can be started.']);
            }

            $teams = $locked->teams()->get();
            if ($teams->count() !== 2 || ! $teams->every->accepted) {
                throw ValidationException::withMessages(['battle' => 'Both room hosts must accept before the battle starts.']);
            }

            $this->transitionToRunning($locked, $teams);
            $startedNow = true;

            return $this->load($locked);
        });

        if ($startedNow) {
            VoicePkStarted::dispatch($battle);
            FinalizeVoicePkBattle::dispatch($battle->getKey())->delay($battle->ends_at);
        }

        return $battle;
    }

    public function expireInvitation(string $battleId): ?VoicePkBattle
    {
        $cancelledNow = false;
        $battle = DB::transaction(function () use ($battleId, &$cancelledNow): ?VoicePkBattle {
            $locked = VoicePkBattle::query()->lockForUpdate()->find($battleId);
            if ($locked === null || $locked->status !== VoicePkBattle::STATUS_WAITING) {
                return $locked === null ? null : $this->load($locked);
            }
            if (now()->lt($locked->invitation_expires_at)) {
                return $this->load($locked);
            }

            $locked->update([
                'status' => VoicePkBattle::STATUS_CANCELLED,
                'ended_at' => now(),
            ]);
            $cancelledNow = true;

            return $this->load($locked);
        });

        if ($cancelledNow && $battle !== null) {
            VoicePkFinished::dispatch($battle);
        }

        return $battle;
    }

    public function finalizeIfDue(string $battleId): ?VoicePkBattle
    {
        $finishedNow = false;
        $battle = DB::transaction(function () use ($battleId, &$finishedNow): ?VoicePkBattle {
            $locked = VoicePkBattle::query()->lockForUpdate()->find($battleId);
            if ($locked === null || $locked->status !== VoicePkBattle::STATUS_RUNNING) {
                return $locked === null ? null : $this->load($locked);
            }
            if ($locked->ends_at === null || now()->lt($locked->ends_at)) {
                return $this->load($locked);
            }

            $teams = $locked->teams()->orderBy('room_id')->get();
            $topScore = $teams->max('team_score');
            $leaders = $teams->where('team_score', $topScore);
            $isDraw = $leaders->count() !== 1;

            $locked->update([
                'status' => VoicePkBattle::STATUS_FINISHED,
                'ended_at' => now(),
                'winner_room_id' => $isDraw ? null : $leaders->first()->room_id,
                'is_draw' => $isDraw,
            ]);
            $finishedNow = true;

            return $this->load($locked);
        });

        if ($finishedNow && $battle !== null) {
            VoicePkFinished::dispatch($battle);
        }

        return $battle;
    }

    public function finish(VoicePkBattle $battle): VoicePkBattle
    {
        $finishedNow = false;
        $battle = DB::transaction(function () use ($battle, &$finishedNow): VoicePkBattle {
            $locked = VoicePkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
            if ($locked->status === VoicePkBattle::STATUS_FINISHED) {
                return $this->load($locked);
            }
            if ($locked->status !== VoicePkBattle::STATUS_RUNNING) {
                throw ValidationException::withMessages(['battle' => 'Only a running voice PK battle can be ended.']);
            }

            $teams = $locked->teams()->orderBy('room_id')->get();
            $topScore = $teams->max('team_score');
            $leaders = $teams->where('team_score', $topScore);
            $isDraw = $leaders->count() !== 1;
            $locked->update([
                'status' => VoicePkBattle::STATUS_FINISHED,
                'ended_at' => now(),
                'winner_room_id' => $isDraw ? null : $leaders->first()->room_id,
                'is_draw' => $isDraw,
            ]);
            $finishedNow = true;

            return $this->load($locked);
        });

        if ($finishedNow) {
            VoicePkFinished::dispatch($battle);
        }

        return $battle;
    }

    public function applyGift(GiftTransaction $transaction): ?VoicePkBattle
    {
        $metadata = $transaction->metadata ?? [];
        if (
            ($metadata[self::GIFT_METADATA_TYPE_KEY] ?? null) !== self::GIFT_METADATA_TYPE
            || ! is_string($transaction->voice_room_id ?? $metadata[self::GIFT_METADATA_ROOM_KEY] ?? null)
        ) {
            return null;
        }

        $roomId = $transaction->voice_room_id ?? $metadata[self::GIFT_METADATA_ROOM_KEY];
        $scoredNow = false;
        $battle = DB::transaction(function () use ($transaction, $roomId, &$scoredNow): ?VoicePkBattle {
            $team = VoicePkTeam::query()
                ->where('room_id', $roomId)
                ->whereHas('battle', fn ($query) => $query->where('status', VoicePkBattle::STATUS_RUNNING))
                ->first();
            if ($team === null) {
                return null;
            }

            $battle = VoicePkBattle::query()->lockForUpdate()->findOrFail($team->voice_pk_battle_id);
            if (
                $battle->status !== VoicePkBattle::STATUS_RUNNING
                || $battle->started_at === null
                || $battle->ends_at === null
                || $transaction->created_at->lt($battle->started_at)
                || $transaction->created_at->gte($battle->ends_at)
            ) {
                return null;
            }

            $inserted = DB::table('voice_pk_gift_contributions')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'voice_pk_battle_id' => $battle->getKey(),
                'voice_pk_team_id' => $team->getKey(),
                'gift_transaction_id' => $transaction->getKey(),
                'coins' => $transaction->coins,
                'created_at' => $transaction->created_at,
            ]);
            if ($inserted === 1) {
                VoicePkTeam::query()->whereKey($team->getKey())->incrementEach([
                    'team_score' => $transaction->coins,
                    'gift_score' => $transaction->coins,
                ]);
                $scoredNow = true;
            }

            return $this->load($battle);
        });

        if ($scoredNow && $battle !== null) {
            VoicePkScoreUpdated::dispatch($battle);
        }

        return $battle;
    }

    public function load(VoicePkBattle $battle): VoicePkBattle
    {
        return $battle->fresh([
            'initiator.profile', 'winnerRoom.host.profile', 'teams.room', 'teams.host.profile',
        ]);
    }

    private function transitionToRunning(VoicePkBattle $battle, Collection $teams): void
    {
        $rooms = VoiceRoom::query()->whereKey($teams->pluck('room_id'))->lockForUpdate()->get();
        if ($rooms->count() !== 2 || $rooms->contains(fn (VoiceRoom $room) => ! $room->isLive())) {
            throw ValidationException::withMessages(['rooms' => 'Both voice rooms must still be live.']);
        }

        $countdownStartedAt = now();
        $startedAt = $countdownStartedAt->copy()->addSeconds($battle->countdown_seconds);
        $battle->update([
            'status' => VoicePkBattle::STATUS_RUNNING,
            'countdown_started_at' => $countdownStartedAt,
            'started_at' => $startedAt,
            'ends_at' => $startedAt->copy()->addSeconds($battle->duration_seconds),
        ]);
    }

    private function validateTimings(int $duration, int $countdown, int $invitation): void
    {
        if ($duration < 15 || $duration > 3600) {
            throw ValidationException::withMessages(['duration_seconds' => 'Duration must be between 15 and 3600 seconds.']);
        }
        if ($countdown < 0 || $countdown > 10) {
            throw ValidationException::withMessages(['countdown_seconds' => 'Countdown must be between 0 and 10 seconds.']);
        }
        if ($invitation < 1 || $invitation > 120) {
            throw ValidationException::withMessages(['invitation_seconds' => 'Invitation expiry must be between 1 and 120 seconds.']);
        }
    }
}
