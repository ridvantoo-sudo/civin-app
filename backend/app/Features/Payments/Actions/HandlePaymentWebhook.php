<?php

namespace App\Features\Payments\Actions;

use App\Features\Payments\Models\PaymentWebhookEvent;
use App\Features\Payments\Services\PaymentService;
use Illuminate\Http\Request;

final readonly class HandlePaymentWebhook
{
    public function __construct(private PaymentService $payments) {}

    public function handle(string $gateway, Request $request): PaymentWebhookEvent
    {
        return $this->payments->handleWebhook($gateway, $request);
    }
}
