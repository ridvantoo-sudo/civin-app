<?php

namespace App\Features\Authentication\Actions;

use App\Features\Authentication\Repositories\Contracts\RefreshTokenRepository;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ResetPassword
{
    public function __construct(private RefreshTokenRepository $refreshTokens) {}

    public function execute(array $credentials): string
    {
        $status = Password::reset(
            $credentials,
            function ($user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                $this->refreshTokens->revokeForUser($user->id);
                event(new PasswordResetEvent($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return __($status);
    }
}
