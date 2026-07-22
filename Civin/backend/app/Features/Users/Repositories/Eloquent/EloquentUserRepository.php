<?php

namespace App\Features\Users\Repositories\Eloquent;

use App\Features\Users\Models\User;
use App\Features\Users\Repositories\Contracts\UserRepository;

final class EloquentUserRepository implements UserRepository
{
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function findByLogin(string $login): ?User
    {
        return User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();
    }

    public function findByFirebaseUid(string $uid, bool $lockForUpdate = false): ?User
    {
        $query = User::query()->where('firebase_uid', $uid);

        return ($lockForUpdate ? $query->lockForUpdate() : $query)->first();
    }

    public function find(string $id): ?User
    {
        return User::query()->find($id);
    }

    public function findForUpdate(string $id): ?User
    {
        return User::query()->lockForUpdate()->find($id);
    }

    public function firebaseUidBelongsToAnother(string $uid, string $userId): bool
    {
        return User::query()->where('firebase_uid', $uid)->whereKeyNot($userId)->exists();
    }

    public function emailExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function usernameExists(string $username): bool
    {
        return User::query()->where('username', $username)->exists();
    }

    public function update(User $user, array $attributes): User
    {
        $user->forceFill($attributes)->save();

        return $user->fresh();
    }
}
