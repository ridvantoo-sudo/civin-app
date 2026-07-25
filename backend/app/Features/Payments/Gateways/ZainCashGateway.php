<?php

namespace App\Features\Payments\Gateways;

use App\Features\Payments\DTOs\GatewayIntent;
use App\Features\Payments\DTOs\GatewayWebhookResult;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Exceptions\WebhookVerificationException;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Support\Jwt;
use App\Features\Payments\Support\PaymentGatewayKey;
use App\Features\Payments\Support\PaymentStatus;
use Illuminate\Http\Request;

/**
 * ZainCash (Iraq) — JWT signed transaction init + hosted redirect.
 * Docs flow: POST /transaction/init -> id -> redirect /transaction/pay?id=...
 */
final class ZainCashGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::ZAINCASH;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled()
            && ! empty($this->config('merchant_id'))
            && ! empty($this->config('secret'))
            && ! empty($this->config('msisdn'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $now = time();
        $payload = [
            'amount' => (int) $this->toMajorAmount($transaction->amount, $transaction->currency),
            'serviceType' => 'Civin merchant order',
            'msisdn' => (string) $this->config('msisdn'),
            'orderId' => (string) $transaction->getKey(),
            'redirectUrl' => $options['return_url'] ?? config('app.url'),
            'iat' => $now,
            'exp' => $now + 60 * 60 * 4,
        ];

        $token = Jwt::encode($payload, (string) $this->config('secret'));

        $response = $this->http()->asForm()->post($this->baseUrl().'/transaction/init', [
            'token' => $token,
            'merchantId' => (string) $this->config('merchant_id'),
            'lang' => (string) ($options['lang'] ?? 'en'),
        ]);

        if ($response->failed() || ! $response->json('id')) {
            throw new PaymentGatewayException(
                (string) $response->json('err.msg', $response->json('msg', 'ZainCash init failed.')),
                'zaincash_error',
                $response->json() ?? [],
            );
        }

        $id = (string) $response->json('id');

        return new GatewayIntent(
            status: PaymentStatus::REQUIRES_ACTION,
            providerReference: $id,
            checkoutUrl: $this->baseUrl().'/transaction/pay?id='.$id,
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        $token = (string) $request->input('token', '');

        try {
            $payload = Jwt::decode($token, (string) $this->config('secret'));
        } catch (\Throwable) {
            throw new WebhookVerificationException('Invalid ZainCash redirect token.');
        }

        $status = (string) ($payload['status'] ?? '');

        return new GatewayWebhookResult(
            status: $this->mapStatus($status),
            eventType: 'transaction.'.$status,
            providerEventId: $payload['id'] ?? null,
            providerReference: $payload['orderid'] ?? ($payload['orderId'] ?? null),
            providerPaymentId: $payload['id'] ?? null,
            raw: $payload,
        );
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'completed' => PaymentStatus::SUCCEEDED,
            'failed' => PaymentStatus::FAILED,
            'pending' => PaymentStatus::PROCESSING,
            default => PaymentStatus::PENDING,
        };
    }
}
