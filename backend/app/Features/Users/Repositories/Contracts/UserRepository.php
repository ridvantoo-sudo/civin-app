<?php

namespace App\Features\Users\Repositories\Contracts;

use App\Features\Users\Models\User;

interface UserRepository
{
    public function create(array $attributes): User;

    public function findByLogin(string $login): ?User;

    public function findByFirebaseUid(string $uid, bool $lockForUpdate = false): ?User;

    public function find(string $id): ?User;

    public function findForUpdate(string $id): ?User;

    public function firebaseUidBelongsToAnother(string $uid, string $userId): bool;

    public function emailExists(string $email): bool;

    public function usernameExists(string $username): bool;

    public function update(User $user, array $attributes): User;
}
