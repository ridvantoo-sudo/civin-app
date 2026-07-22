<?php

namespace App\Features\Settings\Repositories\Contracts;

use App\Features\Users\Models\User;
use Illuminate\Support\Collection;

interface SettingRepository
{
    public function publicValues(): Collection;

    public function userValues(User $user): Collection;

    public function upsertUserValues(User $user, array $settings): Collection;
}
