<?php

namespace App\Features\Payments\Gateways;

use App\Features\Payments\DTOs\GatewayIntent;
use App\Features\Payments\DTOs\GatewayWebhookResult;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Support\PaymentGatewayKey;
use App\Features\Payments\Support\PaymentStatus;
use Illuminate\Http\Request;

/**
 * Google Play — purchase token verification via the Android Publisher API,
 * authenticated with a service-account JWT (RS256) exchanged for an OAuth
 * access token. The client posts `token` (purchaseToken) and a productId in
 * metadata.
 */
final class GooglePlayGateway extends AbstractGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public function key(): string
    {
        return PaymentGatewayKey::GOOGLE_PLAY;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled()
            && ! empty($this->config('package_name'))
            && ! empty($this->config('service_account_json'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $purchaseToken = (string) ($options['token'] ?? '');
        $productId = (string) ($options['product_id'] ?? ($transaction->metadata['product_id'] ?? ''));

        if ($purchaseToken === '' || $productId === '') {
            throw new PaymentGatewayException('Missing Google Play purchase token or product id.', 'google_missing_token');
        }

        $accessToken = $this->accessToken();
        $package = (string) $this->config('package_name');

        $response = $this->http()->withToken($accessToken)->get(
            $this->baseUrl()."/androidpublisher/v3/applications/{$package}/purchases/products/{$productId}/tokens/{$purchaseToken}",
        );

        if ($response->failed()) {
            throw new PaymentGatewayException(
                (string) $response->json('error.message', 'Google Play verification failed.'),
                'google_play_error',
                $response->json() ?? [],
            );
        }

        // purchaseState: 0 = purchased, 1 = cancelled, 2 = pending.
        $purchaseState = (int) $response->json('purchaseState', 1);

        return new GatewayIntent(
            status: match ($purchaseState) {
                0 => PaymentStatus::SUCCEEDED,
                2 => PaymentStatus::PROCESSING,
                default => PaymentStatus::FAILED,
            },
            providerReference: $response->json('orderId'),
            providerPaymentId: $response->json('orderId'),
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        // Real-time developer notifications arrive base64-encoded via Pub/Sub.
        $data = $request->input('message.data');
        $decoded = is_string($data) ? json_decode((string) base64_decode($data), true) : [];
        $decoded = is_array($decoded) ? $decoded : [];

        return new GatewayWebhookResult(
            status: PaymentStatus::PENDING,
            eventType: 'google.rtdn',
            providerEventId: $request->input('message.messageId'),
            ignored: true, // authoritative state is confirmed via createIntent verification
            raw: $decoded,
        );
    }

    private function accessToken(): string
    {
        $credentials = json_decode((string) $this->config('service_account_json'), true);
        if (! is_array($credentials) || empty($credentials['private_key'])) {
            throw new PaymentGatewayException('Invalid Google service account JSON.', 'google_bad_credentials');
        }

        $now = time();
        $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url((string) json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign($header.'.'.$claims, $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $assertion = $header.'.'.$claims.'.'.$this->base64Url($signature);

        $response = $this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new PaymentGatewayException('Unable to authenticate with Google Play.', 'google_auth_error', $response->json() ?? []);
        }

        return (string) $response->json('access_token');
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
