<?php

namespace App\Features\CreatorTools\Services;

use App\Features\CreatorTools\Models\RtmpIngestCredential;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RtmpIngestService
{
    public function issue(User $host, ?LiveRoom $room = null): RtmpIngestCredential
    {
        $this->ensureEligible($host);

        if (! (bool) config('creator_tools.rtmp.enabled', true)) {
            throw ValidationException::withMessages(['rtmp' => 'RTMP ingest is disabled.']);
        }

        if ($room !== null && $room->host_id !== $host->getKey()) {
            throw new AuthorizationException('You do not own this room.');
        }

        // Revoke previous active credentials for this host (+ room scope).
        RtmpIngestCredential::query()
            ->where('host_id', $host->getKey())
            ->where('status', RtmpIngestCredential::STATUS_ACTIVE)
            ->when($room !== null, fn ($q) => $q->where('room_id', $room->getKey()))
            ->when($room === null, fn ($q) => $q->whereNull('room_id'))
            ->update(['status' => RtmpIngestCredential::STATUS_REVOKED]);

        $ttlHours = max(1, (int) config('creator_tools.rtmp.key_ttl_hours', 24));
        $ingestUrl = (string) config('creator_tools.rtmp.ingest_base_url');
        $streamKey = $this->generateStreamKey($host, $room);

        $metadata = ['provider' => 'self_hosted'];

        if ((bool) config('creator_tools.rtmp.agora_media_gateway.enabled')) {
            $metadata = [
                'provider' => 'agora_media_gateway',
                'channel' => $room?->agora_channel_name,
            ];
            // Agora Media Gateway uses the same channel name as the RTC room when bound.
            if ($room !== null) {
                $ingestUrl = rtrim((string) config('creator_tools.rtmp.ingest_base_url'), '/');
            }
        }

        return RtmpIngestCredential::query()->create([
            'host_id' => $host->getKey(),
            'room_id' => $room?->getKey(),
            'ingest_url' => $ingestUrl,
            'stream_key' => $streamKey,
            'status' => RtmpIngestCredential::STATUS_ACTIVE,
            'expires_at' => now()->addHours($ttlHours),
            'metadata' => $metadata,
        ]);
    }

    public function activeForHost(User $host, ?LiveRoom $room = null): ?RtmpIngestCredential
    {
        $this->ensureEligible($host);

        $credential = RtmpIngestCredential::query()
            ->where('host_id', $host->getKey())
            ->where('status', RtmpIngestCredential::STATUS_ACTIVE)
            ->when($room !== null, fn ($q) => $q->where('room_id', $room->getKey()))
            ->when($room === null, fn ($q) => $q->whereNull('room_id'))
            ->latest()
            ->first();

        if ($credential !== null && ! $credential->isUsable()) {
            $credential->forceFill(['status' => RtmpIngestCredential::STATUS_EXPIRED])->save();

            return null;
        }

        return $credential;
    }

    public function revoke(User $host, RtmpIngestCredential $credential): RtmpIngestCredential
    {
        $this->ensureEligible($host);
        if ($credential->host_id !== $host->getKey()) {
            throw new AuthorizationException('You do not own this RTMP credential.');
        }

        $credential->forceFill(['status' => RtmpIngestCredential::STATUS_REVOKED])->save();

        return $credential;
    }

    private function generateStreamKey(User $host, ?LiveRoom $room): string
    {
        $prefix = $room?->getKey() ?? $host->getKey();

        do {
            $key = 'civ_'.Str::lower(substr(str_replace('-', '', (string) $prefix), 0, 12)).'_'.Str::lower(Str::random(20));
        } while (RtmpIngestCredential::query()->where('stream_key', $key)->exists());

        return $key;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active hosts can manage RTMP ingest.');
        }
    }
}
