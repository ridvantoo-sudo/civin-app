<?php

namespace App\Features\Wallet\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Wallet\Models\WalletTransaction */
class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
