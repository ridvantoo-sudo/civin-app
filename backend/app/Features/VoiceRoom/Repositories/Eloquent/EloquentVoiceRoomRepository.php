<?php

namespace App\Features\VoiceRoom\Repositories\Eloquent;

use App\Features\Users\Models\User;
use App\Features\VoiceRoom\DTOs\CreateVoiceRoomData;
use App\Features\VoiceRoom\DTOs\SeatActionData;
use App\Features\VoiceRoom\Models\VoiceParticipant;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Models\VoiceSeat;
use App\Features\VoiceRoom\Models\VoiceSession;
use App\Features\VoiceRoom\Repositories\Contracts\VoiceRoomRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentVoiceRoomRepository implements VoiceRoomRepository
{
    public function create(
        User $host,
        CreateVoiceRoomData $data,
        string $roomId,
        string $channel,
        int $hostUid,
    ): VoiceRoom {
        return DB::transaction(function () use ($host, $data, $roomId, $channel, $hostUid): VoiceRoom {
            if (VoiceRoom::query()
                ->where('host_id', $host->getKey())
                ->where('status', VoiceRoom::STATUS_LIVE)
                ->exists()) {
                throw ValidationException::withMessages(['room' => 'The host already has an active voice room.']);
            }

            $startedAt = now();

            $room = VoiceRoom::query()->create([
                'id' => $roomId,
                'host_id' => $host->getKey(),
                'title' => $data->title,
                'description' => $data->description,
                'thumbnail' => $data->thumbnail,
                'agora_channel_name' => $channel,
                'host_uid' => $hostUid,
                'status' => VoiceRoom::STATUS_LIVE,
                'seat_count' => $data->seatCount,
                'participant_count' => 1,
                'started_at' => $startedAt,
            ]);

            for ($index = 0; $index < $data->seatCount; $index++) {
                VoiceSeat::query()->create([
                    'room_id' => $room->getKey(),
                    'seat_index' => $index,
                    'user_id' => $index === 0 ? $host->getKey() : null,
                    'status' => $index === 0 ? VoiceSeat::STATUS_OCCUPIED : VoiceSeat::STATUS_EMPTY,
                    'is_muted' => false,
                    'stream_uid' => $index === 0 ? $hostUid : null,
                    'updated_at' => $startedAt,
                ]);
            }

            VoiceParticipant::query()->create([
                'room_id' => $room->getKey(),
                'user_id' => $host->getKey(),
                'role' => VoiceParticipant::ROLE_HOST,
                'joined_at' => $startedAt,
            ]);

            VoiceSession::query()->create([
                'room_id' => $room->getKey(),
                'peak_participants' => 1,
            ]);

            return $this->show($room);
        });
    }

    public function join(VoiceRoom $room, User $user): array
    {
        return DB::transaction(function () use ($room, $user): array {
            $lockedRoom = VoiceRoom::query()->lockForUpdate()->findOrFail($room->getKey());

            if ($lockedRoom->status !== VoiceRoom::STATUS_LIVE) {
                throw ValidationException::withMessages(['room' => 'Participants can only join a live voice room.']);
            }

            if ($lockedRoom->host_id === $user->getKey()) {
                return ['room' => $this->show($lockedRoom), 'changed' => false];
            }

            $membership = VoiceParticipant::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership !== null && $membership->left_at === null) {
                return ['room' => $this->show($lockedRoom), 'changed' => false];
            }

            if ($membership === null) {
                VoiceParticipant::query()->create([
                    'room_id' => $lockedRoom->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => VoiceParticipant::ROLE_AUDIENCE,
                    'joined_at' => now(),
                ]);
            } else {
                $membership->update([
                    'role' => VoiceParticipant::ROLE_AUDIENCE,
                    'joined_at' => now(),
                    'left_at' => null,
                ]);
            }

            $participantCount = $lockedRoom->participant_count + 1;
            $lockedRoom->update(['participant_count' => $participantCount]);

            $session = VoiceSession::query()->where('room_id', $lockedRoom->getKey())->lockForUpdate()->firstOrFail();
            if ($participantCount > $session->peak_participants) {
                $session->update(['peak_participants' => $participantCount]);
            }

            return ['room' => $this->show($lockedRoom->fresh()), 'changed' => true];
        });
    }

    public function leave(VoiceRoom $room, User $user): array
    {
        return DB::transaction(function () use ($room, $user): array {
            $lockedRoom = VoiceRoom::query()->lockForUpdate()->findOrFail($room->getKey());

            if ($lockedRoom->host_id === $user->getKey()) {
                throw ValidationException::withMessages(['room' => 'The host cannot leave the room. End the room instead.']);
            }

            $membership = VoiceParticipant::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership === null || $membership->left_at !== null) {
                return [
                    'room' => $this->show($lockedRoom),
                    'changed' => false,
                    'freed_seat' => null,
                    'was_speaker' => false,
                ];
            }

            $freedSeat = VoiceSeat::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $user->getKey())
                ->whereIn('status', [VoiceSeat::STATUS_PENDING, VoiceSeat::STATUS_OCCUPIED])
                ->lockForUpdate()
                ->first();

            $wasSpeaker = $freedSeat !== null && $freedSeat->status === VoiceSeat::STATUS_OCCUPIED;

            if ($freedSeat !== null) {
                $freedSeat->update([
                    'user_id' => null,
                    'status' => VoiceSeat::STATUS_EMPTY,
                    'is_muted' => false,
                    'stream_uid' => null,
                    'updated_at' => now(),
                ]);
            }

            $membership->update([
                'role' => VoiceParticipant::ROLE_AUDIENCE,
                'left_at' => now(),
            ]);
            $lockedRoom->update(['participant_count' => max(0, $lockedRoom->participant_count - 1)]);

            return [
                'room' => $this->show($lockedRoom->fresh()),
                'changed' => true,
                'freed_seat' => $freedSeat?->fresh(),
                'was_speaker' => $wasSpeaker,
            ];
        });
    }

    public function requestSeat(VoiceRoom $room, User $user, SeatActionData $data): VoiceSeat
    {
        return DB::transaction(function () use ($room, $user, $data): VoiceSeat {
            $lockedRoom = $this->lockLiveRoom($room);

            if ($lockedRoom->host_id === $user->getKey()) {
                throw ValidationException::withMessages(['seat' => 'The host already occupies a seat.']);
            }

            $this->requireActiveParticipant($lockedRoom, $user);

            if ($data->seatIndex < 1 || $data->seatIndex >= $lockedRoom->seat_count) {
                throw ValidationException::withMessages(['seat_index' => 'The selected seat is not available.']);
            }

            $existing = VoiceSeat::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $user->getKey())
                ->whereIn('status', [VoiceSeat::STATUS_PENDING, VoiceSeat::STATUS_OCCUPIED])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages(['seat' => 'You already have a seat request or seat.']);
            }

            $seat = VoiceSeat::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('seat_index', $data->seatIndex)
                ->lockForUpdate()
                ->firstOrFail();

            if ($seat->status !== VoiceSeat::STATUS_EMPTY) {
                throw ValidationException::withMessages(['seat' => 'The selected seat is not empty.']);
            }

            $seat->update([
                'user_id' => $user->getKey(),
                'status' => VoiceSeat::STATUS_PENDING,
                'is_muted' => false,
                'stream_uid' => null,
                'updated_at' => now(),
            ]);

            return $seat->fresh(['user.profile']);
        });
    }

    public function approveSeat(VoiceRoom $room, SeatActionData $data): VoiceSeat
    {
        return DB::transaction(function () use ($room, $data): VoiceSeat {
            $lockedRoom = $this->lockLiveRoom($room);
            $seat = $this->lockSeat($lockedRoom, $data->seatIndex);

            if ($seat->status !== VoiceSeat::STATUS_PENDING || $seat->user_id === null) {
                throw ValidationException::withMessages(['seat' => 'Only a pending seat request can be approved.']);
            }

            do {
                $streamUid = random_int(1, 4294967295);
            } while ($streamUid === $lockedRoom->host_uid || $this->streamUidExists($streamUid));

            $seat->update([
                'status' => VoiceSeat::STATUS_OCCUPIED,
                'is_muted' => false,
                'stream_uid' => $streamUid,
                'updated_at' => now(),
            ]);

            VoiceParticipant::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $seat->user_id)
                ->whereNull('left_at')
                ->update(['role' => VoiceParticipant::ROLE_SPEAKER]);

            return $seat->fresh(['user.profile']);
        });
    }

    public function rejectSeat(VoiceRoom $room, SeatActionData $data): VoiceSeat
    {
        return DB::transaction(function () use ($room, $data): VoiceSeat {
            $lockedRoom = $this->lockLiveRoom($room);
            $seat = $this->lockSeat($lockedRoom, $data->seatIndex);

            if ($seat->status !== VoiceSeat::STATUS_PENDING) {
                throw ValidationException::withMessages(['seat' => 'Only a pending seat request can be rejected.']);
            }

            $seat->update([
                'user_id' => null,
                'status' => VoiceSeat::STATUS_EMPTY,
                'is_muted' => false,
                'stream_uid' => null,
                'updated_at' => now(),
            ]);

            return $seat->fresh();
        });
    }

    public function removeSpeaker(VoiceRoom $room, SeatActionData $data): array
    {
        return DB::transaction(function () use ($room, $data): array {
            $lockedRoom = $this->lockLiveRoom($room);
            $seat = $this->lockSeat($lockedRoom, $data->seatIndex);

            if ($seat->seat_index === 0) {
                throw ValidationException::withMessages(['seat' => 'The host seat cannot be removed.']);
            }

            if ($seat->status !== VoiceSeat::STATUS_OCCUPIED || $seat->user_id === null) {
                throw ValidationException::withMessages(['seat' => 'Only an occupied seat can be cleared.']);
            }

            $userId = $seat->user_id;

            $seat->update([
                'user_id' => null,
                'status' => VoiceSeat::STATUS_EMPTY,
                'is_muted' => false,
                'stream_uid' => null,
                'updated_at' => now(),
            ]);

            VoiceParticipant::query()
                ->where('room_id', $lockedRoom->getKey())
                ->where('user_id', $userId)
                ->whereNull('left_at')
                ->update(['role' => VoiceParticipant::ROLE_AUDIENCE]);

            return [
                'room' => $this->show($lockedRoom->fresh()),
                'seat' => $seat->fresh(),
                'user_id' => $userId,
            ];
        });
    }

    public function muteSpeaker(VoiceRoom $room, SeatActionData $data): VoiceSeat
    {
        return DB::transaction(function () use ($room, $data): VoiceSeat {
            $lockedRoom = $this->lockLiveRoom($room);
            $seat = $this->lockSeat($lockedRoom, $data->seatIndex);

            if ($seat->status !== VoiceSeat::STATUS_OCCUPIED || $seat->user_id === null) {
                throw ValidationException::withMessages(['seat' => 'Only an occupied seat can be muted.']);
            }

            if ($seat->seat_index === 0) {
                throw ValidationException::withMessages(['seat' => 'The host seat cannot be muted this way.']);
            }

            $seat->update([
                'is_muted' => $data->muted ?? true,
                'updated_at' => now(),
            ]);

            return $seat->fresh(['user.profile']);
        });
    }

    public function end(VoiceRoom $room): VoiceRoom
    {
        return DB::transaction(function () use ($room): VoiceRoom {
            $lockedRoom = VoiceRoom::query()->lockForUpdate()->findOrFail($room->getKey());
            User::query()->lockForUpdate()->findOrFail($lockedRoom->host_id);

            if ($lockedRoom->status !== VoiceRoom::STATUS_LIVE) {
                throw ValidationException::withMessages(['room' => 'Only a live voice room can be ended.']);
            }

            $endedAt = now();
            $duration = max(0, (int) $lockedRoom->started_at->diffInSeconds($endedAt));
            $finalCount = $lockedRoom->participant_count;

            $session = VoiceSession::query()->where('room_id', $lockedRoom->getKey())->lockForUpdate()->firstOrFail();
            $session->update([
                'duration' => $duration,
                'peak_participants' => max($session->peak_participants, $finalCount),
            ]);

            VoiceParticipant::query()
                ->where('room_id', $lockedRoom->getKey())
                ->whereNull('left_at')
                ->update(['left_at' => $endedAt]);

            VoiceSeat::query()
                ->where('room_id', $lockedRoom->getKey())
                ->update([
                    'user_id' => null,
                    'status' => VoiceSeat::STATUS_EMPTY,
                    'is_muted' => false,
                    'stream_uid' => null,
                    'updated_at' => $endedAt,
                ]);

            $lockedRoom->update([
                'status' => VoiceRoom::STATUS_ENDED,
                'ended_at' => $endedAt,
                'participant_count' => 0,
            ]);

            return $this->show($lockedRoom->fresh());
        });
    }

    public function show(VoiceRoom $room): VoiceRoom
    {
        return $room->load([
            'host.profile',
            'seats.user.profile',
            'session',
        ]);
    }

    public function hostUidExists(int $uid): bool
    {
        return VoiceRoom::query()->where('host_uid', $uid)->exists();
    }

    public function streamUidExists(int $uid): bool
    {
        return VoiceSeat::query()->where('stream_uid', $uid)->exists()
            || VoiceRoom::query()->where('host_uid', $uid)->exists();
    }

    public function occupiedSeatForUser(VoiceRoom $room, string $userId): ?VoiceSeat
    {
        return VoiceSeat::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $userId)
            ->where('status', VoiceSeat::STATUS_OCCUPIED)
            ->first();
    }

    private function lockLiveRoom(VoiceRoom $room): VoiceRoom
    {
        $lockedRoom = VoiceRoom::query()->lockForUpdate()->findOrFail($room->getKey());

        if ($lockedRoom->status !== VoiceRoom::STATUS_LIVE) {
            throw ValidationException::withMessages(['room' => 'The voice room is not live.']);
        }

        return $lockedRoom;
    }

    private function lockSeat(VoiceRoom $room, int $seatIndex): VoiceSeat
    {
        $seat = VoiceSeat::query()
            ->where('room_id', $room->getKey())
            ->where('seat_index', $seatIndex)
            ->lockForUpdate()
            ->first();

        if ($seat === null) {
            throw ValidationException::withMessages(['seat_index' => 'The selected seat does not exist.']);
        }

        return $seat;
    }

    private function requireActiveParticipant(VoiceRoom $room, User $user): void
    {
        $membership = VoiceParticipant::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages(['room' => 'You must join the room before requesting a seat.']);
        }
    }
}
