<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;
use App\Filament\Resources\AgencyResource;
use App\Filament\Resources\GiftResource;
use App\Filament\Resources\LiveRoomResource;
use App\Filament\Resources\ReportResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VipLevelResource;
use App\Filament\Resources\WalletTransactionResource;
use App\Filament\Resources\WithdrawalResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAdminUsers;
use Tests\TestCase;

class AdminResourceAccessTest extends TestCase
{
    use CreatesAdminUsers;
    use RefreshDatabase;

    public function test_guests_cannot_access_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_regular_users_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create(['is_guest' => false]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_dashboard_and_all_resources(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPER_ADMIN);

        $this->actingAs($admin)->get('/admin')->assertOk();

        foreach ([
            UserResource::getUrl('index'),
            LiveRoomResource::getUrl('index'),
            GiftResource::getUrl('index'),
            WalletTransactionResource::getUrl('index'),
            WithdrawalResource::getUrl('index'),
            VipLevelResource::getUrl('index'),
            AgencyResource::getUrl('index'),
            ReportResource::getUrl('index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_finance_admin_can_access_economy_resources_only(): void
    {
        $admin = $this->makeAdmin(AdminRole::FINANCE_ADMIN);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get(GiftResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(WalletTransactionResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(WithdrawalResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(VipLevelResource::getUrl('index'))->assertOk();

        $this->actingAs($admin)->get(UserResource::getUrl('index'))->assertForbidden();
        $this->actingAs($admin)->get(LiveRoomResource::getUrl('index'))->assertForbidden();
        $this->actingAs($admin)->get(AgencyResource::getUrl('index'))->assertForbidden();
        $this->actingAs($admin)->get(ReportResource::getUrl('index'))->assertForbidden();
    }

    public function test_moderator_can_access_moderation_resources(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);

        $this->actingAs($admin)->get(UserResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(LiveRoomResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(ReportResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(GiftResource::getUrl('index'))->assertForbidden();
        $this->actingAs($admin)->get(WithdrawalResource::getUrl('index'))->assertForbidden();
    }

    public function test_support_admin_can_access_users_reports_and_agencies(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPPORT_ADMIN);

        $this->actingAs($admin)->get(UserResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(ReportResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(AgencyResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(LiveRoomResource::getUrl('index'))->assertForbidden();
        $this->actingAs($admin)->get(WalletTransactionResource::getUrl('index'))->assertForbidden();
    }

    public function test_banned_admin_cannot_access_panel(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPER_ADMIN);
        $admin->forceFill([
            'status' => User::STATUS_BANNED,
            'banned_at' => now(),
        ])->save();

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_resource_authorization_helpers_match_permissions(): void
    {
        $finance = $this->makeAdmin(AdminRole::FINANCE_ADMIN);
        $this->actingAs($finance);

        $this->assertTrue(GiftResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertTrue($finance->can(AdminPermission::MANAGE_GIFTS));
        $this->assertFalse($finance->can(AdminPermission::MANAGE_USERS));
    }
}
