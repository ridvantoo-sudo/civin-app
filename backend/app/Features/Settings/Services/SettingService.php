<?php

namespace App\Features\Settings\Services;

use App\Features\Settings\Repositories\Contracts\SettingRepository;
use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

final readonly class SettingService
{
    public function __construct(private SettingRepository $settings) {}

    public function publicValues(): Collection
    {
        return $this->settings->publicValues();
    }

    public function userValues(User $user): Collection
    {
        return $this->settings->userValues($user);
    }

    public function updateUserValues(User $user, array $values): Collection
    {
        return $this->settings->upsertUserValues($user, $values);
    }
}
