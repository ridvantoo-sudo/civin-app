<?php

namespace App\Features\VoiceRoom\Services;

use App\Features\VoiceRoom\DTOs\VoiceRtcConnectionData;
use CyberDeep\LaravelAgoraTokenGenerator\Services\Token\RtcTokenBuilder2;
use DateTimeImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class VoiceAgoraService
{
    public function generateHostToken(string $channel, int $uid): VoiceRtcConnectionData
    {
        return $this->generateToken($channel, $uid, RtcTokenBuilder2::ROLE_PUBLISHER);
    }

    public function generateSpeakerToken(string $channel, int $uid): VoiceRtcConnectionData
    {
        return $this->generateToken($channel, $uid, RtcTokenBuilder2::ROLE_PUBLISHER);
    }

    public function generateAudienceToken(string $channel, string $userId): VoiceRtcConnectionData
    {
        return $this->generateToken($channel, $this->audienceUid($userId), RtcTokenBuilder2::ROLE_SUBSCRIBER);
    }

    public function createChannel(?string $roomId = null): string
    {
        $roomId ??= (string) Str::uuid();
        $channel = 'voice_'.str_replace('-', '', $roomId).'_'.Str::lower(Str::random(8));

        if (! $this->validateChannel($channel)) {
            throw new RuntimeException('Unable to create a valid Agora channel.');
        }

        return $channel;
    }

    public function validateChannel(string $channel): bool
    {
        return $channel !== ''
            && strlen($channel) <= 64
            && preg_match('/^[A-Za-z0-9 !#$%&()+\-:;<=>?@\[\]^_{}|~,.]+$/', $channel) === 1;
    }

    private function generateToken(string $channel, int $uid, int $role): VoiceRtcConnectionData
    {
        if (! $this->validateChannel($channel) || $uid < 1 || $uid > 4294967295) {
            throw new RuntimeException('Invalid Agora connection parameters.');
        }

        $appId = (string) config('agora.app_id');
        $certificate = (string) config('agora.app_certificate');
        $ttl = (int) config('agora.token_ttl', 3600);

        if (
            strlen($appId) !== 32
            || strlen($certificate) !== 32
            || ! ctype_xdigit($appId)
            || ! ctype_xdigit($certificate)
            || $ttl < 1
            || $ttl > 86400
        ) {
            throw new RuntimeException('Agora is not configured.');
        }

        $token = RtcTokenBuilder2::buildTokenWithUid(
            $appId,
            $certificate,
            $channel,
            $uid,
            $role,
            $ttl,
            $ttl,
        );

        if (! is_string($token) || ! str_starts_with($token, '007')) {
            throw new RuntimeException('Agora token generation failed.');
        }

        return new VoiceRtcConnectionData(
            $appId,
            $channel,
            $uid,
            $token,
            new DateTimeImmutable("+{$ttl} seconds"),
        );
    }

    private function audienceUid(string $userId): int
    {
        $uid = unpack('Nuid', substr(hash('sha256', 'voice:'.$userId, true), 0, 4))['uid'];

        return $uid === 0 ? 1 : $uid;
    }
}
