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
 * Apple Pay (App Store receipt verification). The client obtains a signed
 * receipt / payment token and posts it as `token`; we verify it synchronously
 * against Apple's verifyReceipt endpoint (auto-falling back to sandbox).
 */
final class ApplePayGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::APPLE_PAY;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && ! empty($this->config('shared_secret'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $receipt = (string) ($options['token'] ?? '');
        if ($receipt === '') {
            throw new PaymentGatewayException('Missing Apple Pay receipt token.', 'apple_missing_token');
        }

        $result = $this->verifyReceipt($receipt);
        $statusCode = (int) ($result['status'] ?? -1);

        return new GatewayIntent(
            status: $statusCode === 0 ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED,
            providerReference: $result['receipt']['in_app'][0]['transaction_id'] ?? ($transaction->getKey()),
            providerPaymentId: $result['receipt']['in_app'][0]['transaction_id'] ?? null,
            raw: $result,
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        // App Store Server Notifications V2 deliver a signed JWS payload.
        $signedPayload = (string) $request->input('signedPayload', '');
        $decoded = $this->decodeJwsPayload($signedPayload);
        $notificationType = (string) ($decoded['notificationType'] ?? '');

        return new GatewayWebhookResult(
            status: $this->mapNotification($notificationType),
            eventType: $notificationType,
            providerEventId: $decoded['notificationUUID'] ?? null,
            ignored: $notificationType === '',
            raw: $decoded,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyReceipt(string $receipt): array
    {
        $body = [
            'receipt-data' => $receipt,
            'password' => (string) $this->config('shared_secret'),
            'exclude-old-transactions' => true,
        ];

        $url = $this->isLive() ? (string) $this->config('verify_url_prod') : (string) $this->config('verify_url');
        $response = $this->http()->post($url, $body);
        $json = $response->json() ?? [];

        // 21007: sandbox receipt sent to production -> retry sandbox.
        if ((int) ($json['status'] ?? -1) === 21007) {
            $response = $this->http()->post((string) $this->config('verify_url'), $body);
            $json = $response->json() ?? [];
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwsPayload(string $jws): array
    {
        $parts = explode('.', $jws);
        if (count($parts) < 2) {
            return [];
        }

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) ? $payload : [];
    }

    private function mapNotification(string $type): string
    {
        return match ($type) {
            'DID_RENEW', 'SUBSCRIBED', 'ONE_TIME_CHARGE' => PaymentStatus::SUCCEEDED,
            'REFUND' => PaymentStatus::REFUNDED,
            'DID_FAIL_TO_RENEW' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
