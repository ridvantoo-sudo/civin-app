<?php

namespace App\Features\Notifications\Services;

use App\Features\Notifications\Events\NotificationRead;
use App\Features\Notifications\Repositories\Contracts\NotificationRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

final readonly class NotificationService
{
    public function __construct(private NotificationRepository $notifications) {}

    public function paginate(User $user): LengthAwarePaginator
    {
        return $this->notifications->paginateFor($user);
    }

    public function read(User $user, string $id): DatabaseNotification
    {
        $notification = $this->notifications->markRead($this->notifications->findFor($user, $id));
        event(new NotificationRead($user->id, $notification->id));

        return $notification;
    }

    public function readAll(User $user): int
    {
        return $this->notifications->markAllRead($user);
    }

    public function delete(User $user, string $id): void
    {
        $this->notifications->delete($this->notifications->findFor($user, $id));
    }
}
