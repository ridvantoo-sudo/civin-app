<?php

namespace App\Features\Notifications\Repositories\Eloquent;

use App\Features\Notifications\Repositories\Contracts\NotificationRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

final class EloquentNotificationRepository implements NotificationRepository
{
    public function paginateFor(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate($perPage);
    }

    public function findFor(User $user, string $id): DatabaseNotification
    {
        return $user->notifications()->findOrFail($id);
    }

    public function markRead(DatabaseNotification $notification): DatabaseNotification
    {
        $notification->markAsRead();

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function delete(DatabaseNotification $notification): void
    {
        $notification->delete();
    }
}
