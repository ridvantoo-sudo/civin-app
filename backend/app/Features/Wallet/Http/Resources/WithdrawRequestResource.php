<?php

namespace App\Features\Wallet\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\Wallet\Models\WithdrawRequest */
class WithdrawRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'diamonds' => $this->diamonds,
            'amount' => $this->amount,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at,
        ];
    }
}
