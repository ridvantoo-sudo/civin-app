<?php

namespace App\Features\CreatorTools\Services;

use App\Features\CreatorTools\DTOs\CreateClipData;
use App\Features\CreatorTools\Jobs\ProcessStreamClipJob;
use App\Features\CreatorTools\Models\StreamClip;
use App\Features\CreatorTools\Support\ClipKind;
use App\Features\CreatorTools\Support\MediaStatus;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class StreamClipService
{
    private const CREATE_RATE_KEY = 'creator-clip:%s';

    public function create(User $actor, LiveRoom $room, CreateClipData $data): StreamClip
    {
        $this->ensureEligible($actor);
        $this->enforceRateLimit(sprintf(self::CREATE_RATE_KEY, $actor->getKey()));

        if ($room->host_id !== $actor->getKey()) {
            throw new AuthorizationException('Only the host can create clips for this room.');
        }

        if (! in_array($data->kind, ClipKind::all(), true)) {
            throw ValidationException::withMessages(['kind' => 'Invalid clip kind.']);
        }

        $duration = $data->endSeconds - $data->startSeconds;
        $min = (int) config('creator_tools.clips.min_duration_seconds', 3);
        $max = (int) config('creator_tools.clips.max_duration_seconds', 60);

        if ($data->startSeconds < 0 || $duration < $min || $duration > $max) {
            throw ValidationException::withMessages([
                'end_seconds' => "Clip duration must be between {$min} and {$max} seconds.",
            ]);
        }

        $maxPerRoom = (int) config('creator_tools.clips.max_per_room', 50);
        if (StreamClip::query()->where('room_id', $room->getKey())->count() >= $maxPerRoom) {
            throw ValidationException::withMessages(['clip' => 'Clip limit reached for this room.']);
        }

        $async = (bool) config('creator_tools.clips.async_processing', true);
        $cdn = rtrim((string) config('creator_tools.replays.cdn_base_url'), '/');

        $clip = StreamClip::query()->create([
            'room_id' => $room->getKey(),
            'host_id' => $room->host_id,
            'created_by' => $actor->getKey(),
            'title' => $data->title,
            'kind' => $data->kind,
            'start_seconds' => $data->startSeconds,
            'end_seconds' => $data->endSeconds,
            'duration_seconds' => $duration,
            'status' => $async ? MediaStatus::PROCESSING : MediaStatus::READY,
            'media_url' => $async ? null : "{$cdn}/clips/{$room->getKey()}/".uniqid('clip_', true).'.mp4',
            'thumbnail_url' => $data->thumbnailUrl ?? $room->thumbnail,
            'metadata' => $data->metadata,
        ]);

        if ($async) {
            ProcessStreamClipJob::dispatch($clip->getKey());
        }

        return $clip;
    }

    public function forRoom(LiveRoom $room, ?string $kind = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = StreamClip::query()->where('room_id', $room->getKey())->latest();
        if ($kind !== null) {
            $query->where('kind', $kind);
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    public function mine(User $host, int $perPage = 20): LengthAwarePaginator
    {
        $this->ensureEligible($host);

        return StreamClip::query()
            ->where('host_id', $host->getKey())
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }

    public function markReady(StreamClip $clip): StreamClip
    {
        $cdn = rtrim((string) config('creator_tools.replays.cdn_base_url'), '/');
        $clip->forceFill([
            'status' => MediaStatus::READY,
            'media_url' => $clip->media_url ?? "{$cdn}/clips/{$clip->room_id}/{$clip->getKey()}.mp4",
        ])->save();

        return $clip;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active hosts can manage clips.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw ValidationException::withMessages(['clip' => 'Too many clip actions.']);
        }
        RateLimiter::hit($key, 60);
    }
}
