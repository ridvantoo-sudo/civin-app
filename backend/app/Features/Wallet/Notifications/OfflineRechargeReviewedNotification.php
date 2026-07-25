<?php

namespace App\Features\Wallet\Notifications;

use App\Features\Wallet\Models\OfflineRechargeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class OfflineRechargeReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly OfflineRechargeRequest $request)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'offline_recharge_reviewed',
            'offline_recharge_request_id' => $this->request->getKey(),
            'status' => $this->request->status,
            'coins' => $this->request->coins,
            'amount' => $this->request->amount,
            'currency' => $this->request->currency,
            'reference_number' => $this->request->reference_number,
            'admin_notes' => $this->request->admin_notes,
        ];
    }
}
