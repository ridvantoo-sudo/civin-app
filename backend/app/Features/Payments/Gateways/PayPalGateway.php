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

final class PayPalGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::PAYPAL;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && ! empty($this->config('client_id')) && ! empty($this->config('client_secret'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $token = $this->accessToken();

        $response = $this->http()
            ->withToken($token)
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $transaction->getKey(),
                    'custom_id' => (string) $transaction->getKey(),
                    'amount' => [
                        'currency_code' => strtoupper($transaction->currency),
                        'value' => $this->toMajorAmount($transaction->amount, $transaction->currency),
                    ],
                ]],
                'application_context' => array_filter([
                    'return_url' => $options['return_url'] ?? null,
                    'cancel_url' => $options['cancel_url'] ?? null,
                ]),
            ]);

        if ($response->failed()) {
            throw new PaymentGatewayException(
                (string) $response->json('message', 'PayPal order creation failed.'),
                (string) $response->json('name', 'paypal_error'),
                $response->json() ?? [],
            );
        }

        $checkoutUrl = null;
        foreach ((array) $response->json('links', []) as $link) {
            if (($link['rel'] ?? null) === 'approve') {
                $checkoutUrl = $link['href'] ?? null;
            }
        }

        return new GatewayIntent(
            status: $this->mapStatus((string) $response->json('status')),
            providerReference: $response->json('id'),
            providerPaymentId: $response->json('id'),
            checkoutUrl: $checkoutUrl,
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        $token = $this->accessToken();
        $event = $request->json()->all();

        $verify = $this->http()->withToken($token)->post(
            $this->baseUrl().'/v1/notifications/verify-webhook-signature',
            [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $this->config('webhook_id'),
                'webhook_event' => $event,
            ],
        );

        if ($verify->failed() || $verify->json('verification_status') !== 'SUCCESS') {
            throw new WebhookVerificationException('Invalid PayPal webhook signature.');
        }

        $type = (string) ($event['event_type'] ?? '');
        $resource = $event['resource'] ?? [];
        $custom = $resource['custom_id'] ?? ($resource['purchase_units'][0]['custom_id'] ?? null);

        return new GatewayWebhookResult(
            status: $this->mapStatus((string) ($resource['status'] ?? ''), $type),
            eventType: $type,
            providerEventId: $event['id'] ?? null,
            providerReference: $resource['id'] ?? $custom,
            providerPaymentId: $resource['id'] ?? null,
            ignored: ! str_contains($type, 'CHECKOUT.ORDER') && ! str_contains($type, 'PAYMENT.CAPTURE'),
            raw: $event,
        );
    }

    public function refund(PaymentTransaction $transaction, int $amountMinor, ?string $reason = null): RefundResult
    {
        $token = $this->accessToken();
        $captureId = (string) ($transaction->metadata['capture_id'] ?? $transaction->provider_payment_id);

        $response = $this->http()->withToken($token)->post(
            $this->baseUrl().'/v2/payments/captures/'.$captureId.'/refund',
            [
                'amount' => [
                    'value' => $this->toMajorAmount($amountMinor, $transaction->currency),
                    'currency_code' => strtoupper($transaction->currency),
                ],
                'note_to_payer' => $reason,
            ],
        );

        if ($response->failed()) {
            throw new PaymentGatewayException(
                (string) $response->json('message', 'PayPal refund failed.'),
                'paypal_refund_error',
                $response->json() ?? [],
            );
        }

        return new RefundResult(
            succeeded: in_array((string) $response->json('status'), ['COMPLETED', 'PENDING'], true),
            amountMinor: $amountMinor,
            providerReference: $response->json('id'),
            raw: $response->json() ?? [],
        );
    }

    private function accessToken(): string
    {
        $response = $this->http()
            ->withBasicAuth((string) $this->config('client_id'), (string) $this->config('client_secret'))
            ->asForm()
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new PaymentGatewayException('Unable to authenticate with PayPal.', 'paypal_auth_error', $response->json() ?? []);
        }

        return (string) $response->json('access_token');
    }

    private function mapStatus(string $providerStatus, string $eventType = ''): string
    {
        if (str_contains($eventType, 'DENIED') || str_contains($eventType, 'DECLINED')) {
            return PaymentStatus::FAILED;
        }

        return match ($providerStatus) {
            'COMPLETED', 'CAPTURED' => PaymentStatus::SUCCEEDED,
            'APPROVED', 'PENDING' => PaymentStatus::PROCESSING,
            'CREATED', 'SAVED' => PaymentStatus::REQUIRES_ACTION,
            'VOIDED' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };
    }
}
