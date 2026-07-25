<?php

namespace App\Features\Payments\Repositories\Contracts;

use App\Features\Payments\DTOs\GatewayIntent;
use App\Features\Payments\DTOs\GatewayWebhookResult;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Models\PaymentWebhookEvent;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PaymentTransaction;

    public function findByIdempotencyKey(string $key): ?PaymentTransaction;

    public function findByProviderReference(string $gateway, ?string $reference, ?string $paymentId): ?PaymentTransaction;

    public function applyIntent(PaymentTransaction $transaction, GatewayIntent $intent): PaymentTransaction;

    /**
     * Transition a transaction to a new status under a row lock. The callback
     * receives the locked model and may mutate it; return value is ignored.
     * No-ops (and returns fresh) if the transaction is already terminal-paid.
     *
     * @param  array<string, mixed>  $extra
     */
    public function transition(PaymentTransaction $transaction, string $status, array $extra = []): PaymentTransaction;

    /**
     * Idempotently record an inbound webhook. The returned model's
     * wasRecentlyCreated flag indicates whether it is the first occurrence.
     */
    public function recordWebhook(string $gateway, GatewayWebhookResult $result, ?string $transactionId): PaymentWebhookEvent;

    public function markWebhookProcessed(PaymentWebhookEvent $event, ?string $transactionId = null): void;

    public function markWebhookFailed(PaymentWebhookEvent $event, string $message): void;

    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator;
}
