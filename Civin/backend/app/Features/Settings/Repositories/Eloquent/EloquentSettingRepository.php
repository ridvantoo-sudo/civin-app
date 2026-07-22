<?php

namespace App\Features\Settings\Repositories\Eloquent;

use App\Features\Settings\Models\Setting;
use App\Features\Settings\Repositories\Contracts\SettingRepository;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentSettingRepository implements SettingRepository
{
    public function publicValues(): Collection
    {
        return Setting::query()->where('is_public', true)->pluck('value', 'key');
    }

    public function userValues(User $user): Collection
    {
        return $user->settings()->pluck('value', 'key');
    }

    public function upsertUserValues(User $user, array $settings): Collection
    {
        DB::transaction(function () use ($user, $settings): void {
            foreach ($settings as $key => $value) {
                $user->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        return $this->userValues($user);
    }
}
