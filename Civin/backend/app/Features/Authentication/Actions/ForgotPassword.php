<?php

namespace App\Features\Authentication\Actions;

use Illuminate\Support\Facades\Password;

final class ForgotPassword
{
    public function execute(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }
}
