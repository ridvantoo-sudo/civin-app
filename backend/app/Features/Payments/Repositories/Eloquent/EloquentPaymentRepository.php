<?php

namespace App\Features\Payments\Repositories\Eloquent;

use App\Features\Payments\DTOs\GatewayIntent;
use App\Features\Payments\DTOs\GatewayWebhookResult;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Models\PaymentWebhookEvent;
use App\Features\Payments\Repositories\Contracts\PaymentRepository;
use App\Features\Payments\Support\PaymentStatus;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentPaymentRepository implements PaymentRepository
{
    public function create(array $attributes): PaymentTransaction
    {
        return PaymentTransaction::query()->create($attributes);
    }

    public function findByIdempotencyKey(string $key): ?PaymentTransaction
    {
        return PaymentTransaction::query()->where('idempotency_key', $key)->first();
    }

    public function findByProviderReference(string $gateway, ?string $reference, ?string $paymentId): ?PaymentTransaction
    {
        return PaymentTransaction::query()
            ->where('gateway', $gateway)
            ->where(function ($query) use ($reference, $paymentId): void {
                if ($reference !== null) {
                    $query->orWhere('provider_reference', $reference);
                }
                if ($paymentId !== null) {
                    $query->orWhere('provider_payment_id', $paymentId);
                }
            })
            ->first();
    }

    public function applyIntent(PaymentTransaction $transaction, GatewayIntent $intent): PaymentTransaction
    {
        $transaction->fill(array_filter([
            'status' => $intent->status,
            'provider_reference' => $intent->providerReference,
            'provider_payment_id' => $intent->providerPaymentId,
            'client_secret' => $intent->clientSecret,
            'checkout_url' => $intent->checkoutUrl,
        ], static fn ($value): bool => $value !== null));

        if ($intent->status === PaymentStatus::SUCCEEDED && $transaction->paid_at === null) {
            $transaction->paid_at = now();
        }

        $transaction->save();

        return $transaction->fresh() ?? $transaction;
    }

    public function transition(PaymentTransaction $transaction, string $status, array $extra = []): PaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $status, $extra): PaymentTransaction {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            // Never downgrade a succeeded transaction from a late/duplicate event.
            if ($locked->status === PaymentStatus::SUCCEEDED && $status !== PaymentStatus::REFUNDED && $status !== PaymentStatus::PARTIALLY_REFUNDED) {
                return $locked->fresh() ?? $locked;
            }

            $locked->fill($extra);
            $locked->status = $status;

            if ($status === PaymentStatus::SUCCEEDED && $locked->paid_at === null) {
                $locked->paid_at = now();
            }

            if ($status === PaymentStatus::FAILED && $locked->failed_at === null) {
                $locked->failed_at = now();
            }

            $locked->save();

            return $locked->fresh() ?? $locked;
        });
    }

    public function recordWebhook(string $gateway, GatewayWebhookResult $result, ?string $transactionId): PaymentWebhookEvent
    {
        // Fall back to a synthetic id when the provider gives none, so the
        // unique (gateway, provider_event_id) constraint still de-dupes retries.
        $eventId = $result->providerEventId
            ?? ($result->providerPaymentId ? $gateway.':'.$result->providerPaymentId.':'.$result->status : null);

        $attributes = [
            'gateway' => $gateway,
            'event_type' => $result->eventType,
            'status' => PaymentWebhookEvent::STATUS_RECEIVED,
            'payment_transaction_id' => $transactionId,
            'payload' => $result->raw,
        ];

        if ($eventId === null) {
            return PaymentWebhookEvent::query()->create($attributes + ['provider_event_id' => null]);
        }

        return PaymentWebhookEvent::query()->firstOrCreate(
            ['gateway' => $gateway, 'provider_event_id' => $eventId],
            $attributes,
        );
    }

    public function markWebhookProcessed(PaymentWebhookEvent $event, ?string $transactionId = null): void
    {
        $event->forceFill([
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
            'payment_transaction_id' => $transactionId ?? $event->payment_transaction_id,
        ])->save();
    }

    public function markWebhookFailed(PaymentWebhookEvent $event, string $message): void
    {
        $event->forceFill([
            'status' => PaymentWebhookEvent::STATUS_FAILED,
            'error_message' => mb_substr($message, 0, 500),
        ])->save();
    }

    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return PaymentTransaction::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->paginate(max(1, min($perPage, 100)));
    }
}
