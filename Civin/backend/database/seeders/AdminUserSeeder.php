<?php

namespace Database\Seeders;

use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'username' => env('ADMIN_USERNAME', 'civin_admin'),
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
                'is_guest' => false,
            ],
        );

        $admin->forceFill(['is_admin' => true])->save();
        $admin->syncRoles([AdminRole::SUPER_ADMIN]);
    }
}
