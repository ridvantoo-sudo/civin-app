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
 * First Iraqi Bank (FIB) — OAuth2 client credentials + QR/app deep-link
 * payment. Status is delivered via status callback and can be re-queried.
 */
final class FibGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::FIB;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled()
            && ! empty($this->config('client_id'))
            && ! empty($this->config('client_secret'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $token = $this->accessToken();

        $response = $this->http()->withToken($token)->post($this->baseUrl().'/protected/v1/payments', [
            'monetaryValue' => [
                'amount' => $this->toMajorAmount($transaction->amount, $transaction->currency),
                'currency' => strtoupper($transaction->currency),
            ],
            'statusCallbackUrl' => $options['callback_url'] ?? config('app.url'),
            'description' => 'Civin merchant order '.$transaction->getKey(),
            'category' => 'ECOMMERCE',
        ]);

        if ($response->failed() || ! $response->json('paymentId')) {
            throw new PaymentGatewayException(
                (string) $response->json('errors.0.detail', 'FIB payment creation failed.'),
                'fib_error',
                $response->json() ?? [],
            );
        }

        return new GatewayIntent(
            status: PaymentStatus::REQUIRES_ACTION,
            providerReference: (string) $response->json('paymentId'),
            providerPaymentId: (string) $response->json('paymentId'),
            checkoutUrl: $response->json('personalAppLink') ?? $response->json('businessAppLink'),
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        // FIB posts status changes; re-query the authoritative status to confirm.
        $payload = $request->json()->all();
        $paymentId = (string) ($payload['id'] ?? $payload['paymentId'] ?? '');

        if ($paymentId === '') {
            throw new WebhookVerificationException('Missing FIB payment id in callback.');
        }

        $token = $this->accessToken();
        $status = $this->http()->withToken($token)->get($this->baseUrl().'/protected/v1/payments/'.$paymentId.'/status');

        if ($status->failed()) {
            throw new WebhookVerificationException('Unable to verify FIB payment status.');
        }

        $providerStatus = (string) $status->json('status');

        return new GatewayWebhookResult(
            status: $this->mapStatus($providerStatus),
            eventType: 'payment.'.$providerStatus,
            providerEventId: $paymentId,
            providerReference: $paymentId,
            providerPaymentId: $paymentId,
            raw: $status->json() ?? [],
        );
    }

    private function accessToken(): string
    {
        $response = $this->http()
            ->asForm()
            ->post($this->baseUrl().'/auth/realms/fib-online-shop/protocol/openid-connect/token', [
                'grant_type' => 'client_credentials',
                'client_id' => (string) $this->config('client_id'),
                'client_secret' => (string) $this->config('client_secret'),
            ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new PaymentGatewayException('Unable to authenticate with FIB.', 'fib_auth_error', $response->json() ?? []);
        }

        return (string) $response->json('access_token');
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID' => PaymentStatus::SUCCEEDED,
            'DECLINED' => PaymentStatus::FAILED,
            'UNPAID' => PaymentStatus::PROCESSING,
            default => PaymentStatus::PENDING,
        };
    }
}
