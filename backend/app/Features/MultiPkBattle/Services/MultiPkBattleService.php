<?php

namespace App\Features\MultiPkBattle\Services;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\MultiPkBattle\Events\MultiPkAccepted;
use App\Features\MultiPkBattle\Events\MultiPkFinished;
use App\Features\MultiPkBattle\Events\MultiPkInvited;
use App\Features\MultiPkBattle\Events\MultiPkScoreUpdated;
use App\Features\MultiPkBattle\Events\MultiPkStarted;
use App\Features\MultiPkBattle\Jobs\ExpireMultiPkInvitation;
use App\Features\MultiPkBattle\Jobs\FinalizeMultiPkBattle;
use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Features\MultiPkBattle\Models\MultiPkParticipant;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MultiPkBattleService
{
    public function create(
        LiveRoom $initiatorRoom,
        User $initiator,
        string $mode,
        array $invitedRoomIds,
        int $durationSeconds,
        int $countdownSeconds,
        int $invitationSeconds = MultiPkBattle::DEFAULT_INVITATION_SECONDS,
    ): MultiPkBattle {
        $roomIds = array_values(array_unique([$initiatorRoom->getKey(), ...$invitedRoomIds]));
        if (count($invitedRoomIds) !== 3 || count($roomIds) !== 4) {
            throw ValidationException::withMessages(['invited_room_ids' => 'Exactly three distinct opponent rooms must be invited.']);
        }

        $battle = DB::transaction(function () use (
            $initiatorRoom, $initiator, $mode, $roomIds, $durationSeconds, $countdownSeconds, $invitationSeconds
        ): MultiPkBattle {
            $rooms = LiveRoom::query()->whereKey($roomIds)->lockForUpdate()->get()->keyBy('id');
            if ($rooms->count() !== count($roomIds) || $rooms->contains(fn (LiveRoom $room) => $room->status !== 'live')) {
                throw ValidationException::withMessages(['invited_room_ids' => 'All selected rooms must be live.']);
            }
            if ($rooms->pluck('host_id')->unique()->count() !== count($roomIds) || $initiatorRoom->host_id !== $initiator->getKey()) {
                throw ValidationException::withMessages(['invited_room_ids' => 'Every occupied slot must have a distinct live host.']);
            }

            $legacyConflict = PkBattle::query()
                ->whereIn('status', PkBattle::ACTIVE_STATUSES)
                ->where(fn ($query) => $query->whereIn('room_a_id', $roomIds)->orWhereIn('room_b_id', $roomIds))
                ->exists();
            $multiConflict = MultiPkParticipant::query()
                ->whereIn('room_id', $roomIds)
                ->whereHas('battle', fn ($query) => $query->whereIn('status', [
                    MultiPkBattle::STATUS_WAITING,
                    MultiPkBattle::STATUS_RUNNING,
                ]))
                ->exists();
            if ($legacyConflict || $multiConflict) {
                throw ValidationException::withMessages(['rooms' => 'A selected room already has an active PK battle.']);
            }

            $battle = MultiPkBattle::query()->create([
                'initiator_id' => $initiator->getKey(),
                'mode' => $mode,
                'status' => MultiPkBattle::STATUS_WAITING,
                'duration_seconds' => $durationSeconds,
                'countdown_seconds' => $countdownSeconds,
                'invitation_expires_at' => now()->addSeconds($invitationSeconds),
            ]);

            foreach ($roomIds as $index => $roomId) {
                $room = $rooms->get($roomId);
                $accepted = $roomId === $initiatorRoom->getKey();
                $battle->participants()->create([
                    'room_id' => $roomId,
                    'user_id' => $room->host_id,
                    'team' => $mode === MultiPkBattle::MODE_TEAM_2V2 ? ($index % 2 === 0 ? 1 : 2) : null,
                    'accepted' => $accepted,
                    'active' => false,
                    'accepted_at' => $accepted ? now() : null,
                ]);
            }

            return $this->load($battle);
        });

        MultiPkInvited::dispatch($battle);
        ExpireMultiPkInvitation::dispatch($battle->getKey())->delay($battle->invitation_expires_at);

        return $battle;
    }

    public function accept(MultiPkBattle $battle, LiveRoom $room, User $user): MultiPkBattle
    {
        $battle->refresh();
        if ($battle->status !== MultiPkBattle::STATUS_WAITING || now()->gte($battle->invitation_expires_at)) {
            if ($battle->status === MultiPkBattle::STATUS_WAITING) {
                $this->handleInvitationExpiry($battle->getKey());
            }
            throw ValidationException::withMessages(['battle' => 'This invitation has expired or the battle has started.']);
        }

        $battle = DB::transaction(function () use ($battle, $room, $user): MultiPkBattle {
            $locked = MultiPkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
            if ($locked->status !== MultiPkBattle::STATUS_WAITING || now()->gte($locked->invitation_expires_at)) {
                throw ValidationException::withMessages(['battle' => 'This battle no longer accepts invitations.']);
            }

            $participant = $locked->participants()
                ->where('room_id', $room->getKey())
                ->where('user_id', $user->getKey())
                ->first();
            if ($participant === null) {
                throw ValidationException::withMessages(['room' => 'This room was not invited to the battle.']);
            }
            if (! $participant->accepted) {
                $participant->update(['accepted' => true, 'accepted_at' => now()]);
            }

            return $this->load($locked);
        });

        MultiPkAccepted::dispatch($battle);

        if ($battle->participants->isNotEmpty() && $battle->participants->every->accepted) {
            return $this->start($battle);
        }

        return $battle;
    }

    public function start(MultiPkBattle $battle): MultiPkBattle
    {
        $battle = DB::transaction(function () use ($battle): MultiPkBattle {
            $locked = MultiPkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
            if ($locked->status !== MultiPkBattle::STATUS_WAITING) {
                throw ValidationException::withMessages(['battle' => 'Only a waiting battle can be started.']);
            }

            $accepted = $locked->participants()->where('accepted', true)->get();
            if ($accepted->count() !== 4) {
                throw ValidationException::withMessages(['battle' => 'All four hosts must accept before the battle can start.']);
            }

            $locked->participants()->update(['active' => false]);
            $locked->participants()->where('accepted', true)->update(['active' => true]);
            $startedAt = now()->addSeconds($locked->countdown_seconds);
            $locked->update(['status' => MultiPkBattle::STATUS_RUNNING, 'started_at' => $startedAt]);

            return $this->load($locked);
        });

        MultiPkStarted::dispatch($battle);
        FinalizeMultiPkBattle::dispatch($battle->getKey())
            ->delay($battle->started_at->copy()->addSeconds($battle->duration_seconds));

        return $battle;
    }

    public function handleInvitationExpiry(string $battleId): ?MultiPkBattle
    {
        $battle = MultiPkBattle::query()->with('participants')->find($battleId);
        if ($battle === null || $battle->status !== MultiPkBattle::STATUS_WAITING) {
            return $battle;
        }
        if (now()->lt($battle->invitation_expires_at)) {
            return $this->load($battle);
        }

        if ($battle->participants->count() === 4 && $battle->participants->every->accepted) {
            return $this->start($battle);
        }

        $battle = DB::transaction(function () use ($battle): MultiPkBattle {
            $locked = MultiPkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
            if ($locked->status === MultiPkBattle::STATUS_WAITING) {
                $locked->update([
                    'status' => MultiPkBattle::STATUS_CANCELLED,
                    'ended_at' => now(),
                ]);
            }

            return $this->load($locked);
        });
        MultiPkFinished::dispatch($battle);

        return $battle;
    }

    public function finish(MultiPkBattle $battle): MultiPkBattle
    {
        $wasFinished = false;
        $battle = DB::transaction(function () use ($battle, &$wasFinished): MultiPkBattle {
            $locked = MultiPkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
            if ($locked->status === MultiPkBattle::STATUS_FINISHED) {
                return $this->load($locked);
            }
            if ($locked->status !== MultiPkBattle::STATUS_RUNNING) {
                throw ValidationException::withMessages(['battle' => 'Only a running battle can be ended.']);
            }

            $active = $locked->participants()->where('active', true)->get();
            $winnerId = null;
            $winningTeam = null;
            if ($locked->mode === MultiPkBattle::MODE_FFA_4) {
                $top = $active->max('score');
                $leaders = $active->where('score', $top);
                $winnerId = $leaders->count() === 1 ? $leaders->first()->user_id : null;
            } else {
                $totals = $active->groupBy('team')->map->sum('score');
                if ($totals->count() === 2 && $totals->values()->unique()->count() > 1) {
                    $winningTeam = (int) $totals->sortDesc()->keys()->first();
                }
            }

            $locked->update([
                'status' => MultiPkBattle::STATUS_FINISHED,
                'ended_at' => now(),
                'winner_id' => $winnerId,
                'winning_team' => $winningTeam,
            ]);
            $wasFinished = true;

            return $this->load($locked);
        });

        if ($wasFinished) {
            MultiPkFinished::dispatch($battle);
        }

        return $battle;
    }

    public function finalizeIfDue(string $battleId): ?MultiPkBattle
    {
        $battle = MultiPkBattle::query()->find($battleId);
        if ($battle === null || $battle->status !== MultiPkBattle::STATUS_RUNNING) {
            return $battle;
        }
        if (now()->lt($battle->started_at->copy()->addSeconds($battle->duration_seconds))) {
            return $this->load($battle);
        }

        return $this->finish($battle);
    }

    public function applyGift(GiftTransaction $transaction): ?MultiPkBattle
    {
        $participant = MultiPkParticipant::query()
            ->where('room_id', $transaction->room_id)
            ->where('user_id', $transaction->receiver_id)
            ->where('active', true)
            ->whereHas('battle', fn ($query) => $query
                ->where('status', MultiPkBattle::STATUS_RUNNING)
                ->where('started_at', '<=', $transaction->created_at))
            ->first();
        if ($participant === null) {
            return null;
        }

        $participant->increment('score', $transaction->coins);
        $battle = $this->load($participant->battle);
        MultiPkScoreUpdated::dispatch($battle);

        return $battle;
    }

    public function load(MultiPkBattle $battle): MultiPkBattle
    {
        return $battle->fresh([
            'initiator.profile', 'winner.profile', 'participants.room',
            'participants.user.profile', 'participants.user.socialStatus',
        ]);
    }
}
