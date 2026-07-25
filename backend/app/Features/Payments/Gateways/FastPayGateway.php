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
 * FastPay (Iraq) merchant web-service — hosted bill + signed callback.
 */
final class FastPayGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::FASTPAY;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled()
            && ! empty($this->config('store_id'))
            && ! empty($this->config('store_password'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $amount = $this->toMajorAmount($transaction->amount, $transaction->currency);
        $orderId = (string) $transaction->getKey();
        $hash = hash_hmac(
            'sha256',
            $this->config('store_id').$orderId.$amount,
            (string) $this->config('store_password'),
        );

        $response = $this->http()->post($this->baseUrl().'/api/v1/bill/create', [
            'store_id' => (string) $this->config('store_id'),
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => strtoupper($transaction->currency),
            'redirect_url' => $options['return_url'] ?? config('app.url'),
            'hash' => $hash,
        ]);

        if ($response->failed() || ! $response->json('bill_id')) {
            throw new PaymentGatewayException(
                (string) $response->json('message', 'FastPay bill creation failed.'),
                'fastpay_error',
                $response->json() ?? [],
            );
        }

        return new GatewayIntent(
            status: PaymentStatus::REQUIRES_ACTION,
            providerReference: (string) $response->json('bill_id'),
            checkoutUrl: $response->json('payment_url'),
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        $payload = $request->json()->all();
        $signature = (string) ($payload['hash'] ?? $request->header('X-FastPay-Signature', ''));
        $expected = hash_hmac(
            'sha256',
            (string) ($payload['bill_id'] ?? '').(string) ($payload['order_id'] ?? '').(string) ($payload['status'] ?? ''),
            (string) $this->config('store_password'),
        );

        if (! hash_equals($expected, $signature)) {
            throw new WebhookVerificationException('Invalid FastPay callback signature.');
        }

        return new GatewayWebhookResult(
            status: $this->mapStatus((string) ($payload['status'] ?? '')),
            eventType: 'bill.'.(string) ($payload['status'] ?? 'updated'),
            providerEventId: $payload['bill_id'] ?? null,
            providerReference: $payload['order_id'] ?? null,
            providerPaymentId: $payload['bill_id'] ?? null,
            raw: $payload,
        );
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid', 'success', 'completed' => PaymentStatus::SUCCEEDED,
            'failed', 'cancelled', 'canceled' => PaymentStatus::FAILED,
            'pending' => PaymentStatus::PROCESSING,
            default => PaymentStatus::PENDING,
        };
    }
}
