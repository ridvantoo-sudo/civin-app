<?php

namespace App\Features\Authentication\Listeners;

use App\Features\Authentication\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class SendEmailVerification implements ShouldQueueAfterCommit
{
    public function handle(UserRegistered $event): void
    {
        if ($event->user->email && ! $event->user->hasVerifiedEmail()) {
            $event->user->sendEmailVerificationNotification();
        }
    }
}
