<?php

namespace App\Features\Payments\Services;

use App\Features\Payments\DTOs\CreatePaymentIntentData;
use App\Features\Payments\Events\PaymentFailed;
use App\Features\Payments\Events\PaymentRefunded;
use App\Features\Payments\Events\PaymentSucceeded;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Gateways\PaymentGatewayManager;
use App\Features\Payments\Models\PaymentRefund;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Models\PaymentWebhookEvent;
use App\Features\Payments\Repositories\Contracts\PaymentRepository;
use App\Features\Payments\Support\PaymentGatewayKey;
use App\Features\Payments\Support\PaymentStatus;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class PaymentService
{
    private const INTENT_RATE_KEY = 'payments-intent:%s';

    private const RATE_MAX = 20;

    private const RATE_DECAY_SECONDS = 60;

    public function __construct(
        private PaymentRepository $payments,
        private PaymentGatewayManager $gateways,
        private TaxService $tax,
        private RevenueSharingService $revenue,
        private InvoiceService $invoices,
    ) {}

    /**
     * Public list of enabled gateways with client-safe metadata.
     *
     * @return list<array{gateway:string, mode:string, publishable_key:?string}>
     */
    public function availableGateways(): array
    {
        return array_map(function (string $key): array {
            return [
                'gateway' => $key,
                'mode' => (string) config("payments.gateways.{$key}.mode", 'sandbox'),
                'publishable_key' => config("payments.gateways.{$key}.publishable_key"),
            ];
        }, $this->gateways->enabledKeys());
    }

    public function paginateForUser(User $user, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->payments->paginateForUser($user, $perPage);
    }

    public function createIntent(User $user, CreatePaymentIntentData $data): PaymentTransaction
    {
        $this->ensureEligible($user);
        $this->enforceRateLimit(sprintf(self::INTENT_RATE_KEY, $user->getKey()));

        if (! PaymentGatewayKey::isValid($data->gateway)) {
            throw ValidationException::withMessages(['gateway' => 'Unsupported payment gateway.']);
        }

        $driver = $this->gateways->enabledDriver($data->gateway);

        $payable = $this->resolvePayable($data->payableType, $data->payableId);
        $this->assertOwnership($user, $payable);
        $this->assertUnpaid($payable);

        $currency = strtoupper($data->currency ?? (string) config('payments.default_currency', 'USD'));
        $amount = $this->amountForPayable($payable, $currency);

        $taxInfo = $this->tax->resolveRate($payable->shipping_country ?? null);
        $breakdown = $this->tax->compute($amount, (int) $taxInfo['rate_bps'], (bool) $taxInfo['inclusive']);

        // Reuse an in-flight transaction for the same payable+gateway to avoid
        // creating duplicate provider intents on retry.
        $inFlight = PaymentTransaction::query()
            ->where('user_id', $user->getKey())
            ->where('gateway', $data->gateway)
            ->where('payable_type', $payable::class)
            ->where('payable_id', $payable->getKey())
            ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::REQUIRES_ACTION, PaymentStatus::PROCESSING])
            ->latest()
            ->first();

        if ($inFlight !== null && $inFlight->checkout_url !== null) {
            return $inFlight;
        }

        $transaction = $inFlight ?? $this->payments->create([
            'user_id' => $user->getKey(),
            'payable_type' => $payable::class,
            'payable_id' => $payable->getKey(),
            'gateway' => $data->gateway,
            'status' => PaymentStatus::PENDING,
            'currency' => $currency,
            'amount' => $breakdown['total'],
            'tax_amount' => $breakdown['tax'],
            'net_amount' => $breakdown['subtotal'],
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => array_filter([
                'tax_country' => $taxInfo['country'],
                'tax_rate_bps' => $breakdown['rate_bps'],
                'product_id' => $data->metadata['product_id'] ?? null,
            ] + ($data->metadata ?? [])),
        ]);

        try {
            $intent = $driver->createIntent($transaction, array_filter([
                'return_url' => $data->returnUrl,
                'token' => $data->token,
                'customer_name' => $payable->shipping_name ?? null,
            ]));
        } catch (PaymentGatewayException $exception) {
            $this->payments->transition($transaction, PaymentStatus::FAILED, [
                'error_code' => $exception->errorCode,
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw ValidationException::withMessages([
                'gateway' => 'Payment could not be started: '.$exception->getMessage(),
            ]);
        }

        $transaction = $this->payments->applyIntent($transaction, $intent);

        if ($transaction->status === PaymentStatus::SUCCEEDED) {
            $this->settle($transaction);
        } elseif ($transaction->status === PaymentStatus::FAILED) {
            PaymentFailed::dispatch($transaction);
        }

        return $transaction->fresh() ?? $transaction;
    }

    public function handleWebhook(string $gateway, Request $request): PaymentWebhookEvent
    {
        $driver = $this->gateways->enabledDriver($gateway);
        $result = $driver->verifyAndParseWebhook($request); // throws on invalid signature

        $transaction = $this->payments->findByProviderReference(
            $gateway,
            $result->providerReference,
            $result->providerPaymentId,
        );

        if ($transaction === null) {
            $internalId = $result->raw['data']['object']['metadata']['transaction_id']
                ?? ($result->raw['metadata']['transaction_id'] ?? null);
            if (is_string($internalId)) {
                $transaction = PaymentTransaction::query()->find($internalId);
            }
        }

        $event = $this->payments->recordWebhook($gateway, $result, $transaction?->getKey());

        // Duplicate delivery already handled.
        if (! $event->wasRecentlyCreated && $event->status === PaymentWebhookEvent::STATUS_PROCESSED) {
            return $event;
        }

        if ($result->ignored) {
            $this->payments->markWebhookProcessed($event, $transaction?->getKey());

            return $event;
        }

        if ($transaction === null) {
            $this->payments->markWebhookFailed($event, 'No matching transaction for webhook.');

            return $event;
        }

        $transaction = $this->payments->transition($transaction, $result->status, array_filter([
            'provider_payment_id' => $result->providerPaymentId,
        ]));

        if ($result->status === PaymentStatus::SUCCEEDED) {
            $this->settle($transaction);
        } elseif ($result->status === PaymentStatus::FAILED) {
            PaymentFailed::dispatch($transaction);
        }

        $this->payments->markWebhookProcessed($event, $transaction->getKey());

        return $event;
    }

    public function refund(PaymentTransaction $transaction, ?int $amountMinor, ?string $reason, ?User $initiator): PaymentRefund
    {
        if (! $transaction->isRefundable()) {
            throw ValidationException::withMessages(['refund' => 'This transaction cannot be refunded.']);
        }

        $amount = $amountMinor ?? $transaction->refundableAmount();
        $amount = max(1, min($amount, $transaction->refundableAmount()));

        $driver = $this->gateways->enabledDriver($transaction->gateway);

        try {
            $result = $driver->refund($transaction, $amount, $reason);
        } catch (PaymentGatewayException $exception) {
            throw ValidationException::withMessages(['refund' => $exception->getMessage()]);
        }

        $refund = PaymentRefund::query()->create([
            'payment_transaction_id' => $transaction->getKey(),
            'status' => $result->succeeded ? PaymentRefund::STATUS_SUCCEEDED : PaymentRefund::STATUS_FAILED,
            'currency' => $transaction->currency,
            'amount' => $result->amountMinor,
            'reason' => $reason,
            'provider_reference' => $result->providerReference,
            'initiated_by' => $initiator?->getKey(),
            'refunded_at' => $result->succeeded ? now() : null,
        ]);

        if ($result->succeeded) {
            $newRefunded = (int) $transaction->refunded_amount + $result->amountMinor;
            $status = $newRefunded >= (int) $transaction->amount
                ? PaymentStatus::REFUNDED
                : PaymentStatus::PARTIALLY_REFUNDED;

            $transaction = $this->payments->transition($transaction, $status, ['refunded_amount' => $newRefunded]);
            PaymentRefunded::dispatch($transaction, $refund);
        }

        return $refund;
    }

    /**
     * Finalize a succeeded transaction: revenue split + invoice + notify.
     * Idempotent thanks to guards inside each collaborator.
     */
    private function settle(PaymentTransaction $transaction): void
    {
        $payable = $transaction->payable;
        $sellerId = $payable?->seller_id ?? null;

        if ($sellerId !== null) {
            $this->revenue->split($transaction, (string) $sellerId);
        }

        $orderNumber = $payable?->order_number ?? $transaction->getKey();
        $this->invoices->generate(
            $transaction,
            $sellerId !== null ? (string) $sellerId : null,
            'Civin order '.$orderNumber,
            $payable?->shipping_name ?? null,
        );

        PaymentSucceeded::dispatch($transaction->fresh() ?? $transaction);
    }

    private function resolvePayable(string $type, string $id): Model
    {
        $this->assertPayableAllowed($type);

        /** @var class-string<Model> $type */
        $payable = $type::query()->find($id);

        if ($payable === null) {
            throw ValidationException::withMessages(['payable_id' => 'The item to pay for was not found.']);
        }

        return $payable;
    }

    private function assertPayableAllowed(string $type): void
    {
        $allowed = (array) config('payments.allowed_payable_types', []);

        if (! in_array($type, $allowed, true)) {
            // Store-policy guard: virtual goods (coins, gifts, VIP) must never
            // be routed through real-money gateways.
            throw ValidationException::withMessages([
                'payable_type' => 'Real-money gateways are only available for physical goods / merchant orders.',
            ]);
        }
    }

    private function assertOwnership(User $user, Model $payable): void
    {
        $buyerId = $payable->buyer_id ?? $payable->user_id ?? null;

        if ($buyerId !== null && (string) $buyerId !== (string) $user->getKey()) {
            throw new AuthorizationException('You can only pay for your own orders.');
        }
    }

    private function assertUnpaid(Model $payable): void
    {
        if (($payable->payment_status ?? null) === 'paid') {
            throw ValidationException::withMessages(['payable_id' => 'This order is already paid.']);
        }
    }

    private function amountForPayable(Model $payable, string $currency): int
    {
        $coins = (int) ($payable->total_coins ?? 0);

        $rates = (array) config('payments.pricing.coin_to_minor_units', []);
        if (! isset($rates[$currency])) {
            throw ValidationException::withMessages(['currency' => "Unsupported currency [{$currency}]."]);
        }

        $amount = (int) round($coins * (float) $rates[$currency]);

        if ($amount < 1) {
            throw ValidationException::withMessages(['payable_id' => 'Order amount is invalid for payment.']);
        }

        return $amount;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->is_guest || $user->status !== 'active') {
            throw new AuthorizationException('Only active registered users can make payments.');
        }
    }

    private function enforceRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::RATE_MAX)) {
            throw ValidationException::withMessages(['payment' => 'You are attempting payments too quickly.']);
        }

        RateLimiter::hit($key, self::RATE_DECAY_SECONDS);
    }
}
