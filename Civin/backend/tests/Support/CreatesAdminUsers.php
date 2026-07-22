<?php

namespace Tests\Support;

use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use Database\Seeders\AdminRoleSeeder;

trait CreatesAdminUsers
{
    protected function seedAdminRoles(): void
    {
        $this->seed(AdminRoleSeeder::class);
    }

    protected function makeAdmin(string $role = AdminRole::SUPER_ADMIN, array $attributes = []): User
    {
        $this->seedAdminRoles();

        $user = User::factory()->create(array_merge([
            'email' => fake()->unique()->safeEmail(),
            'is_guest' => false,
            'status' => User::STATUS_ACTIVE,
        ], $attributes));

        $user->forceFill([
            'is_admin' => $role === AdminRole::SUPER_ADMIN,
        ])->save();

        $user->syncRoles([$role]);

        return $user->fresh();
    }
}
