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

final class MyFatoorahGateway extends AbstractGateway
{
    public function key(): string
    {
        return PaymentGatewayKey::MYFATOORAH;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && ! empty($this->config('api_key'));
    }

    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent
    {
        $response = $this->http()
            ->withToken((string) $this->config('api_key'))
            ->post($this->baseUrl().'/v2/SendPayment', [
                'InvoiceValue' => (float) $this->toMajorAmount($transaction->amount, $transaction->currency),
                'DisplayCurrencyIso' => strtoupper($transaction->currency),
                'CustomerName' => (string) ($options['customer_name'] ?? 'Civin Customer'),
                'CustomerReference' => (string) $transaction->getKey(),
                'CallBackUrl' => $options['return_url'] ?? config('app.url'),
                'ErrorUrl' => $options['cancel_url'] ?? $options['return_url'] ?? config('app.url'),
                'NotificationOption' => 'LNK',
            ]);

        if ($response->failed() || ! $response->json('IsSuccess')) {
            throw new PaymentGatewayException(
                (string) $response->json('Message', 'MyFatoorah payment failed.'),
                'myfatoorah_error',
                $response->json() ?? [],
            );
        }

        return new GatewayIntent(
            status: PaymentStatus::REQUIRES_ACTION,
            providerReference: (string) $response->json('Data.InvoiceId'),
            checkoutUrl: $response->json('Data.InvoiceURL'),
            raw: $response->json() ?? [],
        );
    }

    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult
    {
        $secret = (string) $this->config('webhook_secret', '');
        $signature = (string) $request->header('MyFatoorah-Signature', '');

        if ($secret !== '') {
            $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
            if (! hash_equals($expected, $signature)) {
                throw new WebhookVerificationException('Invalid MyFatoorah webhook signature.');
            }
        }

        $event = $request->json()->all();
        $data = $event['Data'] ?? $event;
        $status = (string) ($data['TransactionStatus'] ?? $data['InvoiceStatus'] ?? '');

        return new GatewayWebhookResult(
            status: $this->mapStatus($status),
            eventType: (string) ($event['EventType'] ?? 'TransactionsStatusChanged'),
            providerEventId: $data['InvoiceId'] ?? null,
            providerReference: $data['InvoiceId'] ?? null,
            providerPaymentId: $data['PaymentId'] ?? ($data['InvoiceId'] ?? null),
            currency: isset($data['InvoiceValueInDisplayCurrency']) ? strtoupper((string) ($data['DisplayCurrency'] ?? '')) : null,
            raw: $event,
        );
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SUCCESS', 'SUCCESSFUL' => PaymentStatus::SUCCEEDED,
            'FAILED', 'DECLINED' => PaymentStatus::FAILED,
            'PENDING' => PaymentStatus::PROCESSING,
            default => PaymentStatus::PENDING,
        };
    }
}
