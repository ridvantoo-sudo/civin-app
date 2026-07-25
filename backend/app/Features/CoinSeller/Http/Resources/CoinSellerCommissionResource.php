<?php

namespace App\Features\CoinSeller\Http\Resources;

use App\Features\CoinSeller\Models\CoinSellerCommission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoinSellerCommission */
final class CoinSellerCommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'gross_coins' => (int) $this->gross_coins,
            'commission_rate' => (float) $this->commission_rate,
            'commission_amount' => (int) $this->commission_amount,
            'seller_net_coins' => (int) $this->seller_net_coins,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
