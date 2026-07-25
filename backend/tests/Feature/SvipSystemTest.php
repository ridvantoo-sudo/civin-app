<?php

namespace Tests\Feature;

use App\Features\Profiles\Models\Profile;
use App\Features\Svip\Actions\CheckSvipPrivileges;
use App\Features\Svip\Events\SvipActivated;
use App\Features\Svip\Events\SvipExpired;
use App\Features\Svip\Jobs\ExpireSvipSubscriptions;
use App\Features\Svip\Models\SvipLevel;
use App\Features\Svip\Models\SvipTransaction;
use App\Features\Svip\Models\UserSvip;
use App\Features\Svip\Services\SvipService;
use App\Features\Users\Models\User;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Models\VipLevel;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SvipSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_svip_levels_lists_active_catalog(): void
    {
        $elite = SvipLevel::factory()->create([
            'name' => 'Elite',
            'level' => 1,
            'coin_price' => 5000,
            'sort_order' => 1,
            'badge' => 'elite-badge',
            'exclusive_frames' => true,
        ]);
        SvipLevel::factory()->create([
            'name' => 'Hidden',
            'level' => 2,
            'status' => SvipLevel::STATUS_INACTIVE,
            'sort_order' => 2,
        ]);
        $legend = SvipLevel::factory()->create([
            'name' => 'Legend',
            'level' => 3,
            'coin_price' => 12000,
            'sort_order' => 3,
            'special_privileges' => true,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/svip/levels')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $elite->id)
            ->assertJsonPath('data.0.privileges.badge', 'elite-badge')
            ->assertJsonPath('data.0.privileges.exclusive_frames', true)
            ->assertJsonPath('data.1.id', $legend->id)
            ->assertJsonPath('data.1.privileges.special_privileges', true);
    }

    public function test_svip_me_returns_empty_state_without_subscription(): void
    {
        Sanctum::actingAs($this->userWithProfile());

        $this->getJson('/api/v1/svip/me')
            ->assertOk()
            ->assertJsonPath('data.is_svip', false)
            ->assertJsonPath('data.level', null)
            ->assertJsonPath('data.privileges', null);
    }

    public function test_user_can_purchase_svip_with_wallet_coins(): void
    {
        Event::fake([SvipActivated::class]);

        $user = $this->userWithProfile(['is_svip' => false], coins: 10000);
        $level = SvipLevel::factory()->create([
            'name' => 'Elite',
            'level' => 1,
            'coin_price' => 4000,
            'duration_days' => 30,
            'badge' => 'svip-badge',
            'animated_profile' => 'svip-profile',
            'profile_frame' => 'svip-frame',
            'chat_effect' => 'svip-chat',
            'entrance_animation' => 'svip-entrance',
            'exclusive_gifts' => true,
            'exclusive_frames' => true,
            'special_privileges' => true,
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/svip/purchase', [
            'svip_level_id' => $level->id,
            'metadata' => ['source' => 'store'],
        ])->assertCreated()
            ->assertJsonPath('data.is_svip', true)
            ->assertJsonPath('data.level.id', $level->id)
            ->assertJsonPath('data.privileges.badge', 'svip-badge')
            ->assertJsonPath('data.privileges.animated_profile', 'svip-profile')
            ->assertJsonPath('data.privileges.exclusive_frames', true)
            ->assertJsonPath('data.privileges.special_privileges', true);

        $this->assertDatabaseHas('user_svips', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'svip_level_id' => $level->id,
            'status' => UserSvip::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('svip_transactions', [
            'user_id' => $user->id,
            'svip_level_id' => $level->id,
            'type' => SvipTransaction::TYPE_PURCHASE,
            'coins' => 4000,
            'from_level' => null,
            'to_level' => 1,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => SvipTransaction::WALLET_TYPE_SVIP_PURCHASE,
            'amount' => -4000,
            'currency' => WalletTransaction::CURRENCY_COINS,
        ]);

        $this->assertSame(6000, $user->fresh()->wallet->coins_balance);
        $this->assertTrue((bool) $user->fresh()->profile->is_svip);

        Event::assertDispatched(SvipActivated::class, function (SvipActivated $event) use ($user, $level): bool {
            return $event->subscription->user_id === $user->id
                && $event->subscription->svip_level_id === $level->id;
        });
    }

    public function test_svip_purchase_supersedes_active_vip(): void
    {
        $user = $this->userWithProfile(['is_vip' => true], coins: 10000);
        $vipLevel = VipLevel::factory()->create(['level' => 1, 'coin_price' => 500]);
        UserVip::factory()->create([
            'user_id' => $user->id,
            'vip_level_id' => $vipLevel->id,
            'status' => UserVip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(20),
        ]);
        $svipLevel = SvipLevel::factory()->create(['level' => 1, 'coin_price' => 3000]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/svip/purchase', [
            'svip_level_id' => $svipLevel->id,
        ])->assertCreated()->assertJsonPath('data.is_svip', true);

        $this->assertDatabaseMissing('user_vips', ['user_id' => $user->id]);
        $this->assertFalse((bool) $user->fresh()->profile->is_vip);
        $this->assertTrue((bool) $user->fresh()->profile->is_svip);
    }

    public function test_vip_purchase_is_blocked_for_active_svip(): void
    {
        $user = $this->userWithProfile(['is_svip' => true], coins: 5000);
        $svipLevel = SvipLevel::factory()->create(['level' => 1, 'coin_price' => 3000]);
        UserSvip::factory()->create([
            'user_id' => $user->id,
            'svip_level_id' => $svipLevel->id,
            'status' => UserSvip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(20),
        ]);
        $vipLevel = VipLevel::factory()->create(['level' => 1, 'coin_price' => 400]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/vip/purchase', [
            'vip_level_id' => $vipLevel->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['vip']);
    }

    public function test_user_can_upgrade_svip_paying_price_difference(): void
    {
        Event::fake([SvipActivated::class]);

        $user = $this->userWithProfile(['is_svip' => true], coins: 8000);
        $current = SvipLevel::factory()->create(['level' => 1, 'coin_price' => 3000]);
        $next = SvipLevel::factory()->create(['level' => 2, 'coin_price' => 7000]);
        UserSvip::factory()->create([
            'user_id' => $user->id,
            'svip_level_id' => $current->id,
            'status' => UserSvip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(15),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/svip/upgrade', [
            'svip_level_id' => $next->id,
        ])->assertCreated()
            ->assertJsonPath('data.level.id', $next->id);

        $this->assertDatabaseHas('svip_transactions', [
            'user_id' => $user->id,
            'type' => SvipTransaction::TYPE_UPGRADE,
            'coins' => 4000,
            'from_level' => 1,
            'to_level' => 2,
        ]);
        $this->assertSame(4000, $user->fresh()->wallet->coins_balance);
    }

    public function test_expired_svip_is_cleared_by_job(): void
    {
        Event::fake([SvipExpired::class]);

        $user = $this->userWithProfile(['is_svip' => true]);
        $level = SvipLevel::factory()->create(['level' => 1]);
        UserSvip::factory()->create([
            'user_id' => $user->id,
            'svip_level_id' => $level->id,
            'status' => UserSvip::STATUS_ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        (new ExpireSvipSubscriptions)->handle(app(SvipService::class));

        $this->assertDatabaseMissing('user_svips', ['user_id' => $user->id]);
        $this->assertFalse((bool) $user->fresh()->profile->is_svip);
        Event::assertDispatched(SvipExpired::class);
    }

    public function test_svip_privileges_are_available_for_active_subscription(): void
    {
        $user = $this->userWithProfile(['is_svip' => true]);
        $level = SvipLevel::factory()->create([
            'level' => 1,
            'badge' => 'svip-badge',
            'animated_profile' => 'svip-profile',
            'profile_frame' => 'svip-frame',
            'chat_effect' => 'svip-chat',
            'entrance_animation' => 'svip-entrance',
            'exclusive_gifts' => true,
            'exclusive_frames' => true,
            'special_privileges' => true,
        ]);
        UserSvip::factory()->create([
            'user_id' => $user->id,
            'svip_level_id' => $level->id,
            'status' => UserSvip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        $checker = app(CheckSvipPrivileges::class);
        $privileges = $checker->execute($user);

        $this->assertNotNull($privileges);
        $this->assertSame('svip-badge', $privileges->badge);
        $this->assertTrue($checker->has($user, 'animated_profile'));
        $this->assertTrue($checker->has($user, 'exclusive_frames'));
        $this->assertTrue($checker->has($user, 'special_privileges'));
        $this->assertTrue($checker->has($user, 'exclusive_gifts'));
        $this->assertFalse($checker->has($user, 'unknown'));
    }

    public function test_guests_cannot_purchase_or_upgrade_svip(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        Profile::factory()->create(['user_id' => $guest->id]);
        $level = SvipLevel::factory()->create(['level' => 1, 'coin_price' => 100]);

        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/svip/purchase', [
            'svip_level_id' => $level->id,
        ])->assertForbidden();

        $this->postJson('/api/v1/svip/upgrade', [
            'svip_level_id' => $level->id,
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/svip/levels')->assertUnauthorized();
        $this->getJson('/api/v1/svip/me')->assertUnauthorized();
        $this->postJson('/api/v1/svip/purchase', [])->assertUnauthorized();
        $this->postJson('/api/v1/svip/upgrade', [])->assertUnauthorized();
    }

    private function userWithProfile(array $profileAttributes = [], int $coins = 0): User
    {
        $user = $coins > 0
            ? User::factory()->withCoins($coins)->create()
            : User::factory()->create();

        Profile::factory()->create(array_merge([
            'user_id' => $user->id,
            'display_name' => $user->username,
            'is_vip' => false,
            'is_svip' => false,
        ], $profileAttributes));

        return $user->fresh(['profile', 'wallet']);
    }
}
