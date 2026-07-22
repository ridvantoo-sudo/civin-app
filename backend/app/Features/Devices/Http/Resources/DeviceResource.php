<?php

namespace App\Features\Devices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_uuid' => $this->device_uuid,
            'platform' => $this->platform,
            'name' => $this->name,
            'app_version' => $this->app_version,
            'os_version' => $this->os_version,
            'last_seen_at' => $this->last_seen_at,
        ];
    }
}
