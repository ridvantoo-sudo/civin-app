<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesAdminUsers;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use CreatesAdminUsers;
    use RefreshDatabase;

    public function test_role_seeder_creates_expected_roles_and_permissions(): void
    {
        $this->seed(AdminRoleSeeder::class);

        foreach (AdminPermission::all() as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (AdminRole::all() as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        $this->assertSame(
            count(AdminPermission::all()),
            Role::findByName(AdminRole::SUPER_ADMIN, 'web')->permissions()->count(),
        );
    }

    public function test_moderator_permissions_are_scoped(): void
    {
        $moderator = $this->makeAdmin(AdminRole::MODERATOR);

        $this->assertTrue($moderator->can(AdminPermission::MANAGE_USERS));
        $this->assertTrue($moderator->can(AdminPermission::MANAGE_LIVE_ROOMS));
        $this->assertTrue($moderator->can(AdminPermission::REVIEW_REPORTS));
        $this->assertTrue($moderator->can(AdminPermission::MODERATE_CHAT));
        $this->assertFalse($moderator->can(AdminPermission::APPROVE_WITHDRAWALS));
        $this->assertFalse($moderator->can(AdminPermission::MANAGE_WALLETS));
        $this->assertFalse($moderator->can(AdminPermission::MANAGE_GIFTS));
        $this->assertFalse($moderator->can(AdminPermission::MANAGE_VIP));
        $this->assertFalse($moderator->can(AdminPermission::MANAGE_AGENCIES));
    }

    public function test_finance_admin_permissions_are_scoped(): void
    {
        $finance = $this->makeAdmin(AdminRole::FINANCE_ADMIN);

        $this->assertTrue($finance->can(AdminPermission::MANAGE_WALLETS));
        $this->assertTrue($finance->can(AdminPermission::APPROVE_WITHDRAWALS));
        $this->assertTrue($finance->can(AdminPermission::MANAGE_GIFTS));
        $this->assertTrue($finance->can(AdminPermission::MANAGE_VIP));
        $this->assertFalse($finance->can(AdminPermission::MANAGE_USERS));
        $this->assertFalse($finance->can(AdminPermission::REVIEW_REPORTS));
    }

    public function test_support_admin_permissions_are_scoped(): void
    {
        $support = $this->makeAdmin(AdminRole::SUPPORT_ADMIN);

        $this->assertTrue($support->can(AdminPermission::MANAGE_USERS));
        $this->assertTrue($support->can(AdminPermission::REVIEW_REPORTS));
        $this->assertTrue($support->can(AdminPermission::MANAGE_AGENCIES));
        $this->assertFalse($support->can(AdminPermission::MANAGE_LIVE_ROOMS));
        $this->assertFalse($support->can(AdminPermission::APPROVE_WITHDRAWALS));
    }

    public function test_super_admin_bypasses_permission_checks(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPER_ADMIN);
        $custom = Permission::findOrCreate('custom ability', 'web');

        $this->assertTrue($admin->can($custom->name));
        $this->assertTrue($admin->can(AdminPermission::MANAGE_USERS));
    }

    public function test_assigning_role_grants_panel_access(): void
    {
        $this->seedAdminRoles();
        $user = User::factory()->create(['is_guest' => false]);

        $this->assertFalse($user->canAccessPanel(filament()->getPanel('admin')));

        $user->assignRole(AdminRole::SUPPORT_ADMIN);

        $this->assertTrue($user->fresh()->canAccessPanel(filament()->getPanel('admin')));
    }
}
