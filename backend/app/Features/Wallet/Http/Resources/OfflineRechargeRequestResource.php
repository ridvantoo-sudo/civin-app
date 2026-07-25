<?php

namespace App\Features\Wallet\Http\Resources;

use App\Features\Wallet\Models\OfflineRechargeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OfflineRechargeRequest */
class OfflineRechargeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'coins' => $this->coins,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference_number' => $this->reference_number,
            'receipt_url' => $this->receiptUrl(),
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'admin_notes' => $this->admin_notes,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
        ];
    }
}
