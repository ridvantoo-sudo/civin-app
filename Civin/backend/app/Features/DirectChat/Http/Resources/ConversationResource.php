<?php

namespace App\Features\DirectChat\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerId = (string) $request->user()?->getKey();
        $other = $viewerId === $this->user_low_id ? $this->highUser : $this->lowUser;

        return [
            'id' => $this->id,
            'other_user' => new SocialUserResource($other),
            'last_message_at' => $this->last_message_at?->toISOString(),
        ];
    }
}
