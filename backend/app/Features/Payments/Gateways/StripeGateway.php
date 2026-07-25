<?php

namespace App\Features\Payments\Gateways;

use App\Features\Payments\DTOs\GatewayIntent;
use App\Features\Payments\DTOs\GatewayWebhookResult;
use App\Features\Payments\DTOs\RefundResult;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Exceptions\WebhookVerificationException;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Support\PaymentGatewayKey;
use App\Features\Payments\Support\PaymentStatus;
use Illuminate\Http\Request;

final class StripeGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::STRIPE;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && ! empty($this->config('secret_key'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $response = $this->http()
            ->withToken((string) $this->config('secret_key'))
            ->asForm()
            ->post($this->baseUrl().'/payment_intents', [
                'amount' => $transaction->amount,
                'currency' => strtolower($transaction->currency),
                'metadata' => [
                    'transaction_id' => $transaction->getKey(),
                    'payable_type' => $transaction->payable_type,
                    'payable_id' => $transaction->payable_id,
                ],
                'automatic_payment_methods' => ['enabled' => 'true'],
            ]);

        if ($response->failed()) {
            throw new PaymentGatewayException(
                (string) $response->json('error.message', 'Stripe payment intent failed.'),
                (string) $response->json('error.code', 'stripe_error'),
                $response->json() ?? [],
            );
        }

        return new GatewayIntent(
            status: $this->mapStatus((string) $response->json('status')),
            providerReference: $response->json('id'),
            providerPaymentId: $response->json('id'),
            clientSecret: $response->json('client_secret'),
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        $secret = (string) $this->config('webhook_secret');
        $signatureHeader = (string) $request->header('Stripe-Signature', '');
        $payload = $request->getContent();

        if ($secret === '' || ! $this->signatureValid($payload, $signatureHeader, $secret)) {
            throw new WebhookVerificationException('Invalid Stripe webhook signature.');
        }

        $event = $request->json()->all();
        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        return new GatewayWebhookResult(
            status: $this->mapStatus((string) ($object['status'] ?? ''), $type),
            eventType: $type,
            providerEventId: $event['id'] ?? null,
            providerReference: $object['id'] ?? null,
            providerPaymentId: $object['id'] ?? null,
            amountMinor: isset($object['amount']) ? (int) $object['amount'] : null,
            currency: isset($object['currency']) ? strtoupper((string) $object['currency']) : null,
            ignored: ! str_starts_with($type, 'payment_intent.') && ! str_starts_with($type, 'charge.'),
            raw: $event,
        );
    }

    public function refund(PaymentTransaction $transaction, int $amountMinor, ?string $reason = null): RefundResult
    {
        $response = $this->http()
            ->withToken((string) $this->config('secret_key'))
            ->asForm()
            ->post($this->baseUrl().'/refunds', array_filter([
                'payment_intent' => $transaction->provider_payment_id,
                'amount' => $amountMinor,
                'reason' => $reason,
            ]));

        if ($response->failed()) {
            throw new PaymentGatewayException(
                (string) $response->json('error.message', 'Stripe refund failed.'),
                (string) $response->json('error.code', 'stripe_refund_error'),
                $response->json() ?? [],
            );
        }

        return new RefundResult(
            succeeded: in_array((string) $response->json('status'), ['succeeded', 'pending'], true),
            amountMinor: (int) $response->json('amount', $amountMinor),
            providerReference: $response->json('id'),
            raw: $response->json() ?? [],
        );
    }

    private function signatureValid(string $payload, string $header, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $segment) {
            [$k, $v] = array_pad(explode('=', trim($segment), 2), 2, '');
            $parts[$k][] = $v;
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function mapStatus(string $providerStatus, string $eventType = ''): string
    {
        if (str_ends_with($eventType, 'payment_failed')) {
            return PaymentStatus::FAILED;
        }

        return match ($providerStatus) {
            'succeeded' => PaymentStatus::SUCCEEDED,
            'processing' => PaymentStatus::PROCESSING,
            'requires_action', 'requires_confirmation', 'requires_payment_method' => PaymentStatus::REQUIRES_ACTION,
            'canceled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };
    }
}
