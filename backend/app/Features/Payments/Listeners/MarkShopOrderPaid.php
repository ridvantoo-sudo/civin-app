<?php

namespace App\Features\Payments\Listeners;

use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Repositories\Contracts\LiveShoppingRepository;
use App\Features\Payments\Events\PaymentSucceeded;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cross-feature seam: when a real-money payment succeeds, mark the linked
 * merchant ShopOrder as paid. Kept resilient so a shopping-side hiccup never
 * rolls back a captured payment.
 */
final class MarkShopOrderPaid
{
    public function __construct(private readonly LiveShoppingRepository $shopping) {}

    public function handle(PaymentSucceeded $event): void
    {
        $transaction = $event->transaction;

        if ($transaction->payable_type !== ShopOrder::class) {
            return;
        }

        /** @var ShopOrder|null $order */
        $order = ShopOrder::query()->find($transaction->payable_id);

        if ($order === null || $order->isPaid()) {
            return;
        }

        try {
            $this->shopping->completeExternalPayment(
                $order,
                $transaction->gateway,
                (string) ($transaction->provider_payment_id ?? $transaction->getKey()),
            );
        } catch (Throwable $exception) {
            Log::warning('payments.order_mark_paid_failed', [
                'transaction_id' => $transaction->getKey(),
                'order_id' => $order->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
