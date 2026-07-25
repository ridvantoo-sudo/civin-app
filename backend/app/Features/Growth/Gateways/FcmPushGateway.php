<?php

namespace App\Features\Growth\Gateways;

use App\Features\Growth\Contracts\PushGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM HTTP v1 push sender. Authenticates with a Google service-account JWT
 * (same pattern as Google Play payments). When credentials are missing the
 * gateway reports disabled and CampaignService falls back to logged no-ops.
 */
final class FcmPushGateway implements PushGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isEnabled(): bool
    {
        return (bool) config('growth.campaigns.fcm.enabled')
            && ! empty(config('growth.campaigns.fcm.project_id'))
            && ! empty(config('growth.campaigns.fcm.service_account_json'));
    }

    public function send(string $deviceToken, string $title, string $body, ?string $actionUrl = null, array $data = []): array
    {
        if (! $this->isEnabled()) {
            Log::info('growth.push.skipped', ['reason' => 'fcm_disabled', 'title' => $title]);

            return ['ok' => true, 'reference' => 'log:'.uniqid('push_', true), 'error' => null];
        }

        try {
            $token = $this->accessToken();
            $projectId = (string) config('growth.campaigns.fcm.project_id');
            $base = rtrim((string) config('growth.campaigns.fcm.base_url'), '/');

            $message = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', array_filter([
                        'action_url' => $actionUrl,
                        ...$data,
                    ], static fn ($v) => $v !== null)),
                ],
            ];

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->post("{$base}/v1/projects/{$projectId}/messages:send", $message);

            if ($response->failed()) {
                return [
                    'ok' => false,
                    'reference' => null,
                    'error' => (string) $response->json('error.message', 'FCM send failed'),
                ];
            }

            return [
                'ok' => true,
                'reference' => $response->json('name'),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'reference' => null, 'error' => $exception->getMessage()];
        }
    }

    private function accessToken(): string
    {
        $credentials = json_decode((string) config('growth.campaigns.fcm.service_account_json'), true);
        if (! is_array($credentials) || empty($credentials['private_key'])) {
            throw new \RuntimeException('Invalid FCM service account JSON.');
        }

        $now = time();
        $header = $this->b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64((string) json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign($header.'.'.$claims, $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $assertion = $header.'.'.$claims.'.'.$this->b64($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new \RuntimeException('Unable to authenticate with FCM.');
        }

        return (string) $response->json('access_token');
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
