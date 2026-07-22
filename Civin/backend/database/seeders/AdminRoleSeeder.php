<?php

namespace Database\Seeders;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Admin\Support\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (AdminPermission::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (AdminRole::permissionMap() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
