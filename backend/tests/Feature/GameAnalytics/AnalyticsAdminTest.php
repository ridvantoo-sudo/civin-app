<?php

namespace Tests\Feature\GameAnalytics;

use App\Features\Admin\Support\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAdminUsers;
use Tests\TestCase;

final class AnalyticsAdminTest extends TestCase
{
    use CreatesAdminUsers;
    use RefreshDatabase;

    public function test_super_admin_can_open_analytics_dashboard(): void
    {
        $admin = $this->makeAdmin(AdminRole::SUPER_ADMIN);

        $this->actingAs($admin)
            ->get('/admin/analytics-dashboard')
            ->assertOk();
    }

    public function test_finance_admin_can_open_analytics_dashboard(): void
    {
        $admin = $this->makeAdmin(AdminRole::FINANCE_ADMIN);

        $this->actingAs($admin)
            ->get('/admin/analytics-dashboard')
            ->assertOk();
    }

    public function test_moderator_cannot_open_analytics_dashboard(): void
    {
        $admin = $this->makeAdmin(AdminRole::MODERATOR);

        $this->actingAs($admin)
            ->get('/admin/analytics-dashboard')
            ->assertForbidden();
    }
}
