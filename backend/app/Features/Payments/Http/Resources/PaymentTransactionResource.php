<?php

namespace App\Features\Payments\Http\Resources;

use App\Features\Payments\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentTransaction */
final class PaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'status' => $this->status,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'tax_amount' => $this->tax_amount,
            'net_amount' => $this->net_amount,
            'refunded_amount' => $this->refunded_amount,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'provider_reference' => $this->provider_reference,
            // client_secret / checkout_url are what the app needs to complete payment.
            'client_secret' => $this->client_secret,
            'checkout_url' => $this->checkout_url,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
