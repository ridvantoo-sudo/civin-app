<?php

namespace App\Features\CoinSeller\Http\Resources;

use App\Features\CoinSeller\Models\CoinSellerSale;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoinSellerSale */
final class CoinSellerSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $buyer = $this->relationLoaded('buyer') ? $this->buyer : null;
        $code = $this->relationLoaded('rechargeCode') ? $this->rechargeCode : null;

        return [
            'id' => $this->id,
            'coin_seller_id' => $this->coin_seller_id,
            'type' => $this->type,
            'coins' => (int) $this->coins,
            'price_amount' => (int) $this->price_amount,
            'currency' => $this->currency,
            'commission_rate' => (float) $this->commission_rate,
            'commission_amount' => (int) $this->commission_amount,
            'seller_net_coins' => (int) $this->seller_net_coins,
            'status' => $this->status,
            'completed_at' => $this->completed_at?->toISOString(),
            'buyer' => $buyer === null ? null : new SocialUserResource($buyer),
            'recharge_code' => $code === null ? null : new CoinRechargeCodeResource($code),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
