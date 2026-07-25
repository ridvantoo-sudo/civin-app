<?php

namespace App\Features\Admin\Services;

use App\Features\Admin\Support\AdminRole;
use App\Features\LiveChat\Events\MessageDeleted;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Repositories\Contracts\LiveMessageRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Services\LiveStreamingService;
use App\Features\MultiPkBattle\Events\MultiPkFinished;
use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Features\MultiPkBattle\Services\MultiPkBattleService;
use App\Features\PkBattle\Events\PkFinished;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Services\PkBattleService;
use App\Features\Users\Models\User;
use App\Features\VoicePkBattle\Events\VoicePkFinished;
use App\Features\VoicePkBattle\Models\VoicePkBattle;
use App\Features\VoicePkBattle\Services\VoicePkBattleService;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Services\VoiceRoomService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AdminModerationService
{
    public function __construct(
        private LiveStreamingService $liveStreaming,
        private VoiceRoomService $voiceRooms,
        private PkBattleService $pkBattles,
        private MultiPkBattleService $multiPkBattles,
        private VoicePkBattleService $voicePkBattles,
        private LiveMessageRepository $messages,
        private AdminAuditLogger $audit,
    ) {}

    public function banUser(User $admin, User $user, ?string $reason = null): User
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot ban yourself.']);
        }

        if ($user->hasRole(AdminRole::SUPER_ADMIN) && ! $admin->hasRole(AdminRole::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['user' => 'Only a Super Admin can ban another Super Admin.']);
        }

        return DB::transaction(function () use ($admin, $user, $reason): User {
            $user->forceFill([
                'status' => User::STATUS_BANNED,
                'banned_at' => now(),
                'ban_reason' => $reason,
            ])->save();

            $user->tokens()->delete();

            $liveRooms = LiveRoom::query()
                ->where('host_id', $user->getKey())
                ->where('status', 'live')
                ->get();

            foreach ($liveRooms as $room) {
                $this->liveStreaming->end($room);
            }

            $voiceRooms = VoiceRoom::query()
                ->where('host_id', $user->getKey())
                ->where('status', VoiceRoom::STATUS_LIVE)
                ->get();

            foreach ($voiceRooms as $room) {
                $this->voiceRooms->end($room);
            }

            $this->audit->log($admin, 'user.banned', $user, [
                'reason' => $reason,
                'terminated_rooms' => $liveRooms->pluck('id')->all(),
                'terminated_voice_rooms' => $voiceRooms->pluck('id')->all(),
            ]);

            return $user->fresh();
        });
    }

    public function unbanUser(User $admin, User $user): User
    {
        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'banned_at' => null,
            'ban_reason' => null,
        ])->save();

        $this->audit->log($admin, 'user.unbanned', $user);

        return $user->fresh();
    }

    public function forceLogout(User $admin, User $user): User
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot force-logout yourself from this action.']);
        }

        $revoked = $user->tokens()->count();
        $user->tokens()->delete();

        $this->audit->log($admin, 'user.force_logout', $user, [
            'revoked_tokens' => $revoked,
        ]);

        return $user->fresh() ?? $user;
    }

    public function resetPassword(User $admin, User $user, string $password): User
    {
        if ($user->hasRole(AdminRole::SUPER_ADMIN) && ! $admin->hasRole(AdminRole::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['user' => 'Only a Super Admin can reset another Super Admin password.']);
        }

        $user->forceFill([
            'password' => $password,
        ])->save();

        $user->tokens()->delete();

        $this->audit->log($admin, 'user.password_reset', $user);

        return $user->fresh() ?? $user;
    }

    public function verifyEmail(User $admin, User $user): User
    {
        if ($user->email_verified_at !== null) {
            return $user;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $this->audit->log($admin, 'user.email_verified', $user);

        return $user->fresh() ?? $user;
    }

    public function disableTwoFactor(User $admin, User $user): User
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['user' => 'Disable your own two-factor from your profile settings.']);
        }

        if ($user->hasRole(AdminRole::SUPER_ADMIN) && ! $admin->hasRole(AdminRole::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['user' => 'Only a Super Admin can disable another Super Admin two-factor.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log($admin, 'user.two_factor_disabled', $user);

        return $user->fresh() ?? $user;
    }

    public function softDeleteUser(User $admin, User $user): User
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot delete yourself.']);
        }

        if ($user->hasRole(AdminRole::SUPER_ADMIN) && ! $admin->hasRole(AdminRole::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['user' => 'Only a Super Admin can delete another Super Admin.']);
        }

        return DB::transaction(function () use ($admin, $user): User {
            $user->forceFill([
                'status' => User::STATUS_DELETED,
            ])->save();

            $user->tokens()->delete();
            $user->delete();

            $this->audit->log($admin, 'user.deleted', $user);

            return $user;
        });
    }

    public function restoreUser(User $admin, User $user): User
    {
        if (! $user->trashed()) {
            throw ValidationException::withMessages(['user' => 'This user is not deleted.']);
        }

        $user->restore();
        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'banned_at' => null,
            'ban_reason' => null,
        ])->save();

        $this->audit->log($admin, 'user.restored', $user);

        return $user->fresh() ?? $user;
    }

    public function forceDeleteUser(User $admin, User $user): void
    {
        if (! $admin->hasRole(AdminRole::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['user' => 'Only a Super Admin can permanently delete users.']);
        }

        if ($admin->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot permanently delete yourself.']);
        }

        if ($user->hasRole(AdminRole::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['user' => 'Super Admin accounts cannot be permanently deleted.']);
        }

        DB::transaction(function () use ($admin, $user): void {
            $user->tokens()->delete();

            $this->audit->log($admin, 'user.force_deleted', $user, [
                'username' => $user->username,
                'email' => $user->email,
            ]);

            $user->forceDelete();
        });
    }

    public function terminateLiveRoom(User $admin, LiveRoom $room): LiveRoom
    {
        if ($room->status === 'ended') {
            throw ValidationException::withMessages(['room' => 'This live room has already ended.']);
        }

        $ended = $this->liveStreaming->end($room);

        $this->audit->log($admin, 'live_room.terminated', $ended, [
            'host_id' => $ended->host_id,
            'previous_status' => $room->status,
        ]);

        return $ended;
    }

    public function terminateVoiceRoom(User $admin, VoiceRoom $room): VoiceRoom
    {
        if ($room->status === VoiceRoom::STATUS_ENDED) {
            throw ValidationException::withMessages(['room' => 'This voice room has already ended.']);
        }

        $ended = $this->voiceRooms->end($room);

        $this->audit->log($admin, 'voice_room.terminated', $ended, [
            'host_id' => $ended->host_id,
            'previous_status' => $room->status,
        ]);

        return $ended;
    }

    public function endPkBattle(User $admin, PkBattle $battle): PkBattle
    {
        if (in_array($battle->status, [PkBattle::STATUS_FINISHED, PkBattle::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['battle' => 'This PK battle is already closed.']);
        }

        $previous = $battle->status;

        if ($battle->status === PkBattle::STATUS_WAITING) {
            $battle->forceFill([
                'status' => PkBattle::STATUS_CANCELLED,
                'ended_at' => now(),
            ])->save();

            $closed = $battle->fresh([
                'scores.user.profile',
                'hostA.profile',
                'hostB.profile',
                'roomA',
                'roomB',
            ]) ?? $battle;

            PkFinished::dispatch($closed);
        } else {
            $host = User::query()->findOrFail($battle->host_a_id);
            $closed = $this->pkBattles->end($battle, $host);
        }

        $this->audit->log($admin, 'pk_battle.ended', $closed, [
            'previous_status' => $previous,
            'status' => $closed->status,
        ]);

        return $closed;
    }

    public function endMultiPkBattle(User $admin, MultiPkBattle $battle): MultiPkBattle
    {
        if (in_array($battle->status, [MultiPkBattle::STATUS_FINISHED, MultiPkBattle::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['battle' => 'This multi PK battle is already closed.']);
        }

        $previous = $battle->status;

        if ($battle->status === MultiPkBattle::STATUS_WAITING) {
            $closed = DB::transaction(function () use ($battle): MultiPkBattle {
                $locked = MultiPkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
                if ($locked->status === MultiPkBattle::STATUS_WAITING) {
                    $locked->update([
                        'status' => MultiPkBattle::STATUS_CANCELLED,
                        'ended_at' => now(),
                    ]);
                }

                return $locked->fresh(['participants', 'initiator', 'winner']) ?? $locked;
            });
            MultiPkFinished::dispatch($closed);
        } else {
            $closed = $this->multiPkBattles->finish($battle);
        }

        $this->audit->log($admin, 'multi_pk_battle.ended', $closed, [
            'previous_status' => $previous,
            'status' => $closed->status,
        ]);

        return $closed;
    }

    public function endVoicePkBattle(User $admin, VoicePkBattle $battle): VoicePkBattle
    {
        if (in_array($battle->status, [VoicePkBattle::STATUS_FINISHED, VoicePkBattle::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['battle' => 'This voice PK battle is already closed.']);
        }

        $previous = $battle->status;

        if ($battle->status === VoicePkBattle::STATUS_WAITING) {
            $closed = DB::transaction(function () use ($battle): VoicePkBattle {
                $locked = VoicePkBattle::query()->lockForUpdate()->findOrFail($battle->getKey());
                if ($locked->status === VoicePkBattle::STATUS_WAITING) {
                    $locked->update([
                        'status' => VoicePkBattle::STATUS_CANCELLED,
                        'ended_at' => now(),
                    ]);
                }

                return $locked->fresh(['teams.room', 'teams.host', 'initiator', 'winnerRoom']) ?? $locked;
            });
            VoicePkFinished::dispatch($closed);
        } else {
            $closed = $this->voicePkBattles->finish($battle);
        }

        $this->audit->log($admin, 'voice_pk_battle.ended', $closed, [
            'previous_status' => $previous,
            'status' => $closed->status,
        ]);

        return $closed;
    }

    public function deleteLiveMessage(User $admin, LiveMessage $message): LiveMessage
    {
        $room = $message->room()->firstOrFail();
        $deleted = $this->messages->delete($message);

        MessageDeleted::dispatch($room->getKey(), $message->getKey());

        $this->audit->log($admin, 'live_message.deleted', $deleted, [
            'room_id' => $room->getKey(),
            'message_user_id' => $message->user_id,
            'type' => $message->type,
        ]);

        return $deleted;
    }
}
