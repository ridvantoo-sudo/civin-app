<?php

namespace Database\Seeders;

use App\Features\Settings\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['key' => 'support_url', 'type' => 'string', 'value' => 'https://example.com/support', 'is_public' => true],
            ['key' => 'minimum_app_version', 'type' => 'string', 'value' => '1.0.0', 'is_public' => true],
            ['key' => 'maintenance_mode', 'type' => 'boolean', 'value' => false, 'is_public' => true],
            ['key' => 'internal_notification_batch_size', 'type' => 'integer', 'value' => 500, 'is_public' => false],
        ] as $setting) {
            Setting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
