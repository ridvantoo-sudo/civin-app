<?php

namespace App\Features\Reports\Notifications;

use App\Features\Reports\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class ReportReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Report $report)
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
            'type' => 'report_reviewed',
            'report_id' => $this->report->getKey(),
            'status' => $this->report->status,
        ];
    }
}
