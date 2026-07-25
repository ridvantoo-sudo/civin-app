<?php

namespace App\Features\Payments\Http\Resources;

use App\Features\Payments\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceLine */
final class InvoiceLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
            'tax_rate_bps' => $this->tax_rate_bps,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
        ];
    }
}
