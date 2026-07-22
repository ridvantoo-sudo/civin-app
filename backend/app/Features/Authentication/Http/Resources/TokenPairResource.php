<?php

namespace App\Features\Authentication\Http\Resources;

use App\Features\Authentication\DTOs\TokenPair;
use App\Features\Devices\Http\Resources\DeviceResource;
use App\Features\Profiles\Http\Resources\ProfileResource;
use App\Features\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TokenPair */
class TokenPairResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $profile = $this->user->profile()
            ->with('country', 'user.socialStatus')
            ->first();

        return [
            'token_type' => 'Bearer',
            'access_token' => $this->accessToken,
            'expires_at' => $this->accessExpiresAt->format(DATE_ATOM),
            'access_token_expires_at' => $this->accessExpiresAt->format(DATE_ATOM),
            'expires_in' => max(0, now()->diffInSeconds($this->accessExpiresAt, false)),
            'refresh_token' => $this->refreshToken,
            'refresh_token_expires_at' => $this->refreshExpiresAt->format(DATE_ATOM),
            'user' => (new UserResource($this->user))->resolve($request),
            'profile' => $profile ? (new ProfileResource($profile))->resolve($request) : null,
            'device' => (new DeviceResource($this->device))->resolve($request),
        ];
    }
}
