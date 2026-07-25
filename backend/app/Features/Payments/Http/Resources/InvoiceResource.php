<?php

namespace App\Features\Payments\Http\Resources;

use App\Features\Payments\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
final class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'tax_country' => $this->tax_country,
            'billing_name' => $this->billing_name,
            'seller_id' => $this->seller_id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'issued_at' => $this->issued_at?->toISOString(),
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
