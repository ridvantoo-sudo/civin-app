<?php

namespace App\Features\Kyc\Notifications;

use App\Features\Kyc\Models\KycSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class KycReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly KycSubmission $submission) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'kyc.reviewed',
            'kyc_id' => $this->submission->getKey(),
            'status' => $this->submission->status,
            'withdrawal_verified' => $this->submission->withdrawal_verified,
            'admin_notes' => $this->submission->admin_notes,
            'reviewed_at' => $this->submission->reviewed_at?->toISOString(),
        ];
    }
}
