<?php

namespace App\Features\SocialEngagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Features\SocialEngagement\Models\UserCheckIn */
final class UserCheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'check_in_date' => $this->check_in_date?->toDateString(),
            'streak_day' => $this->streak_day,
            'coins_awarded' => $this->coins_awarded,
            'diamonds_awarded' => $this->diamonds_awarded,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
