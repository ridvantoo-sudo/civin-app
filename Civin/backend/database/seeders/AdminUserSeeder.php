<?php

namespace Database\Seeders;

use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@civin.app'],
            [
                'username' => 'civin_admin',
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
                'is_guest' => false,
            ],
        );

        $admin->forceFill(['is_admin' => true])->save();
        $admin->syncRoles([AdminRole::SUPER_ADMIN]);
    }
}
