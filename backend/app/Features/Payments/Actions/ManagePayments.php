<?php

namespace App\Features\Payments\Actions;

use App\Features\Payments\DTOs\CreatePaymentIntentData;
use App\Features\Payments\Models\PaymentRefund;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Services\PaymentService;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ManagePayments
{
    public function __construct(private PaymentService $payments) {}

    /**
     * @return list<array{gateway:string, mode:string, publishable_key:?string}>
     */
    public function availableGateways(): array
    {
        return $this->payments->availableGateways();
    }

    public function createIntent(User $user, CreatePaymentIntentData $data): PaymentTransaction
    {
        return $this->payments->createIntent($user, $data);
    }

    public function list(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->payments->paginateForUser($user, $perPage);
    }

    public function refund(PaymentTransaction $transaction, ?int $amountMinor, ?string $reason, User $initiator): PaymentRefund
    {
        return $this->payments->refund($transaction, $amountMinor, $reason, $initiator);
    }
}
