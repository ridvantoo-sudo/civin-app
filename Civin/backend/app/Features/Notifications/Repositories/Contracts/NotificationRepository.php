<?php

namespace App\Features\Notifications\Repositories\Contracts;

use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

interface NotificationRepository
{
    public function paginateFor(User $user, int $perPage = 20): LengthAwarePaginator;

    public function findFor(User $user, string $id): DatabaseNotification;

    public function markRead(DatabaseNotification $notification): DatabaseNotification;

    public function markAllRead(User $user): int;

    public function delete(DatabaseNotification $notification): void;
}
