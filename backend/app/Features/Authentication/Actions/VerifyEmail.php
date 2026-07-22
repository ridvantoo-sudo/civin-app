<?php

namespace App\Features\Authentication\Actions;

use App\Features\Users\Models\User;
use Illuminate\Auth\Events\Verified;

final class VerifyEmail
{
    public function sendNotice(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    public function execute(User $user, string $hash): void
    {
        abort_unless(hash_equals($hash, sha1((string) $user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    }
}
