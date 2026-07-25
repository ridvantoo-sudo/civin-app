<?php

namespace App\Features\Payments\Gateways;

use App\Features\Payments\DTOs\GatewayIntent;
use App\Features\Payments\DTOs\GatewayWebhookResult;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Exceptions\WebhookVerificationException;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Support\PaymentGatewayKey;
use App\Features\Payments\Support\PaymentStatus;
use Illuminate\Http\Request;

/**
 * Qi Card (International Smart Card, Iraq) — OAuth2 + hosted payment page.
 */
final class QiCardGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::QI_CARD;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled()
            && ! empty($this->config('terminal_id'))
            && ! empty($this->config('merchant_id'))
            && ! empty($this->config('secret'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $token = $this->accessToken();

        $response = $this->http()->withToken($token)->post($this->baseUrl().'/api/v1/payments', [
            'terminalId' => (string) $this->config('terminal_id'),
            'merchantId' => (string) $this->config('merchant_id'),
            'amount' => $this->toMajorAmount($transaction->amount, $transaction->currency),
            'currency' => strtoupper($transaction->currency),
            'orderId' => (string) $transaction->getKey(),
            'callbackUrl' => $options['return_url'] ?? config('app.url'),
        ]);

        if ($response->failed() || ! $response->json('paymentId')) {
            throw new PaymentGatewayException(
                (string) $response->json('message', 'Qi Card payment failed.'),
                'qi_card_error',
                $response->json() ?? [],
            );
        }

        return new GatewayIntent(
            status: PaymentStatus::REQUIRES_ACTION,
            providerReference: (string) $response->json('paymentId'),
            checkoutUrl: $response->json('paymentUrl'),
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        $payload = $request->json()->all();
        $signature = (string) $request->header('X-Qi-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), (string) $this->config('secret'));

        if (! hash_equals($expected, $signature)) {
            throw new WebhookVerificationException('Invalid Qi Card webhook signature.');
        }

        return new GatewayWebhookResult(
            status: $this->mapStatus((string) ($payload['status'] ?? '')),
            eventType: 'payment.'.(string) ($payload['status'] ?? 'updated'),
            providerEventId: $payload['paymentId'] ?? null,
            providerReference: $payload['orderId'] ?? null,
            providerPaymentId: $payload['paymentId'] ?? null,
            raw: $payload,
        );
    }

    private function accessToken(): string
    {
        $response = $this->http()->asForm()->post($this->baseUrl().'/api/v1/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => (string) $this->config('merchant_id'),
            'client_secret' => (string) $this->config('secret'),
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new PaymentGatewayException('Unable to authenticate with Qi Card.', 'qi_card_auth_error', $response->json() ?? []);
        }

        return (string) $response->json('access_token');
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid', 'success', 'approved', 'completed' => PaymentStatus::SUCCEEDED,
            'failed', 'declined' => PaymentStatus::FAILED,
            'pending' => PaymentStatus::PROCESSING,
            default => PaymentStatus::PENDING,
        };
    }
}
