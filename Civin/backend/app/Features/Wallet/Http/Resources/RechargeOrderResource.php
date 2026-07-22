<?php

namespace App\Features\Wallet\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Wallet\Models\RechargeOrder */
class RechargeOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'package_name' => $this->package_name,
            'coins' => $this->coins,
            'price' => $this->price,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_provider' => $this->payment_provider,
            'transaction_id' => $this->transaction_id,
            'created_at' => $this->created_at,
        ];
    }
}
