<?php

namespace App\Features\Admin\Concerns;

/**
 * Two-factor authentication readiness for admin panel users.
 * Columns and helpers exist so a Filament/Laravel 2FA provider can be enabled without schema changes.
 */
trait HasTwoFactorAuthentication
{
    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function twoFactorReady(): bool
    {
        return true;
    }
}
