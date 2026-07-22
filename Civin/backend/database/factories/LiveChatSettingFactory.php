<?php

namespace Database\Factories;

use App\Features\LiveChat\Models\LiveChatSetting;
use App\Features\LiveStreaming\Models\LiveRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveChatSettingFactory extends Factory
{
    protected $model = LiveChatSetting::class;

    public function definition(): array
    {
        return [
            'room_id' => LiveRoom::factory(),
            'slow_mode_seconds' => 0,
            'followers_only' => false,
            'allow_links' => true,
        ];
    }
}
