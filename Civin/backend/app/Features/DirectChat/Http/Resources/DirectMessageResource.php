<?php

namespace App\Features\DirectChat\Http\Resources;

use App\Features\Profiles\Http\Resources\SocialUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DirectMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'sender' => new SocialUserResource($this->whenLoaded('sender')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
