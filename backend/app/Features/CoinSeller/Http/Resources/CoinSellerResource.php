<?php

namespace App\Features\CoinSeller\Http\Resources;

use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoinSeller */
final class CoinSellerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;
        $reviewer = $this->relationLoaded('reviewer') ? $this->reviewer : null;

        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'contact_info' => $this->contact_info,
            'application_message' => $this->application_message,
            'status' => $this->status,
            'commission_rate' => (float) $this->commission_rate,
            'inventory_balance' => (int) $this->inventory_balance,
            'total_sold' => (int) $this->total_sold,
            'total_commission' => (int) $this->total_commission,
            'sales_count' => (int) $this->sales_count,
            'admin_notes' => $this->admin_notes,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'user' => $user === null ? null : new SocialUserResource($user),
            'reviewer' => $reviewer === null ? null : new SocialUserResource($reviewer),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
