<?php

namespace Tests\Feature;

use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\Vip\Actions\CheckVipPrivileges;
use App\Features\Vip\Events\VipActivated;
use App\Features\Vip\Events\VipExpired;
use App\Features\Vip\Jobs\ExpireVipSubscriptions;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Models\VipLevel;
use App\Features\Vip\Models\VipTransaction;
use App\Features\Vip\Services\VipService;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VipSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_vip_levels_lists_active_catalog(): void
    {
        $bronze = VipLevel::factory()->create([
            'name' => 'Bronze',
            'level' => 1,
            'coin_price' => 500,
            'sort_order' => 1,
            'badge' => 'bronze-badge',
            'exclusive_gifts' => false,
        ]);
        VipLevel::factory()->create([
            'name' => 'Hidden',
            'level' => 2,
            'status' => VipLevel::STATUS_INACTIVE,
            'sort_order' => 2,
        ]);
        $gold = VipLevel::factory()->create([
            'name' => 'Gold',
            'level' => 3,
            'coin_price' => 2000,
            'sort_order' => 3,
            'exclusive_gifts' => true,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/vip/levels')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $bronze->id)
            ->assertJsonPath('data.0.name', 'Bronze')
            ->assertJsonPath('data.0.coin_price', 500)
            ->assertJsonPath('data.0.privileges.badge', 'bronze-badge')
            ->assertJsonPath('data.0.privileges.exclusive_gifts', false)
            ->assertJsonPath('data.1.id', $gold->id)
            ->assertJsonPath('data.1.privileges.exclusive_gifts', true);
    }

    public function test_vip_me_returns_empty_state_without_subscription(): void
    {
        Sanctum::actingAs($this->userWithProfile());

        $this->getJson('/api/v1/vip/me')
            ->assertOk()
            ->assertJsonPath('data.is_vip', false)
            ->assertJsonPath('data.level', null)
            ->assertJsonPath('data.privileges', null);
    }

    public function test_user_can_purchase_vip_with_wallet_coins(): void
    {
        Event::fake([VipActivated::class]);

        $user = $this->userWithProfile(['is_vip' => false], coins: 1000);
        $level = VipLevel::factory()->create([
            'name' => 'Silver',
            'level' => 1,
            'coin_price' => 400,
            'duration_days' => 30,
            'badge' => 'silver-badge',
            'profile_frame' => 'silver-frame',
            'chat_effect' => 'silver-chat',
            'entrance_animation' => 'silver-entrance',
            'exclusive_gifts' => true,
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/vip/purchase', [
            'vip_level_id' => $level->id,
            'metadata' => ['source' => 'store'],
        ])->assertCreated()
            ->assertJsonPath('data.is_vip', true)
            ->assertJsonPath('data.level.id', $level->id)
            ->assertJsonPath('data.privileges.badge', 'silver-badge')
            ->assertJsonPath('data.privileges.exclusive_gifts', true);

        $this->assertDatabaseHas('user_vips', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'vip_level_id' => $level->id,
            'status' => UserVip::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('vip_transactions', [
            'user_id' => $user->id,
            'vip_level_id' => $level->id,
            'type' => VipTransaction::TYPE_PURCHASE,
            'coins' => 400,
            'from_level' => null,
            'to_level' => 1,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => VipTransaction::WALLET_TYPE_VIP_PURCHASE,
            'amount' => -400,
            'currency' => WalletTransaction::CURRENCY_COINS,
        ]);

        $this->assertSame(600, $user->fresh()->wallet->coins_balance);
        $this->assertTrue((bool) $user->fresh()->profile->is_vip);

        Event::assertDispatched(VipActivated::class, function (VipActivated $event) use ($user, $level): bool {
            return $event->subscription->user_id === $user->id
                && $event->subscription->vip_level_id === $level->id;
        });

        $this->getJson('/api/v1/vip/me')
            ->assertOk()
            ->assertJsonPath('data.is_vip', true)
            ->assertJsonPath('data.level.id', $level->id);
    }

    public function test_purchase_validates_insufficient_coin_balance(): void
    {
        $user = $this->userWithProfile(coins: 100);
        $level = VipLevel::factory()->create([
            'level' => 1,
            'coin_price' => 500,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/vip/purchase', [
            'vip_level_id' => $level->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['coins']);

        $this->assertDatabaseCount('user_vips', 0);
        $this->assertDatabaseCount('vip_transactions', 0);
        $this->assertSame(100, $user->fresh()->wallet->coins_balance);
        $this->assertFalse((bool) $user->fresh()->profile->is_vip);
    }

    public function test_user_can_upgrade_vip_to_higher_level(): void
    {
        Event::fake([VipActivated::class]);

        $user = $this->userWithProfile(['is_vip' => true], coins: 2000);
        $bronze = VipLevel::factory()->create([
            'name' => 'Bronze',
            'level' => 1,
            'coin_price' => 500,
            'exclusive_gifts' => false,
        ]);
        $gold = VipLevel::factory()->create([
            'name' => 'Gold',
            'level' => 2,
            'coin_price' => 1500,
            'badge' => 'gold-badge',
            'exclusive_gifts' => true,
        ]);

        $subscription = UserVip::factory()->create([
            'user_id' => $user->id,
            'vip_level_id' => $bronze->id,
            'status' => UserVip::STATUS_ACTIVE,
            'started_at' => now()->subDays(5),
            'expires_at' => now()->addDays(25),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/vip/upgrade', [
            'vip_level_id' => $gold->id,
        ])->assertCreated()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.level.id', $gold->id)
            ->assertJsonPath('data.privileges.badge', 'gold-badge')
            ->assertJsonPath('data.privileges.exclusive_gifts', true);

        $this->assertDatabaseHas('vip_transactions', [
            'user_id' => $user->id,
            'type' => VipTransaction::TYPE_UPGRADE,
            'coins' => 1000,
            'from_level' => 1,
            'to_level' => 2,
        ]);

        $this->assertSame(1000, $user->fresh()->wallet->coins_balance);
        $this->assertTrue((bool) $user->fresh()->profile->is_vip);

        Event::assertDispatched(VipActivated::class);
    }

    public function test_upgrade_rejects_same_or_lower_level_and_missing_subscription(): void
    {
        $user = $this->userWithProfile(coins: 5000);
        $bronze = VipLevel::factory()->create(['level' => 1, 'coin_price' => 500]);
        $silver = VipLevel::factory()->create(['level' => 2, 'coin_price' => 1000]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/vip/upgrade', [
            'vip_level_id' => $silver->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['vip']);

        UserVip::factory()->create([
            'user_id' => $user->id,
            'vip_level_id' => $silver->id,
            'status' => UserVip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        $this->postJson('/api/v1/vip/upgrade', [
            'vip_level_id' => $bronze->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['vip_level_id']);

        $this->assertSame(5000, $user->fresh()->wallet->coins_balance);
    }

    public function test_purchase_rejects_when_active_vip_already_exists(): void
    {
        $user = $this->userWithProfile(coins: 5000);
        $bronze = VipLevel::factory()->create(['level' => 1, 'coin_price' => 500]);
        $silver = VipLevel::factory()->create(['level' => 2, 'coin_price' => 1000]);

        UserVip::factory()->create([
            'user_id' => $user->id,
            'vip_level_id' => $bronze->id,
            'status' => UserVip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/vip/purchase', [
            'vip_level_id' => $silver->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['vip_level_id']);

        $this->assertSame(5000, $user->fresh()->wallet->coins_balance);
    }

    public function test_expire_vip_subscriptions_job_removes_expired_vip(): void
    {
        Event::fake([VipExpired::class]);

        $user = $this->userWithProfile(['is_vip' => true]);
        $activeUser = $this->userWithProfile(['is_vip' => true]);
        $level = VipLevel::factory()->create(['level' => 1]);

        $expired = UserVip::factory()->create([
            'user_id' => $user->id,
            'vip_level_id' => $level->id,
            'status' => UserVip::STATUS_ACTIVE,
            'started_at' => now()->subDays(40),
            'expires_at' => now()->subMinute(),
        ]);

        $active = UserVip::factory()->create([
            'user_id' => $activeUser->id,
            'vip_level_id' => $level->id,
            'status' => UserVip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(5),
        ]);

        (new ExpireVipSubscriptions)->handle(app(VipService::class));

        $this->assertDatabaseMissing('user_vips', ['id' => $expired->id]);
        $this->assertDatabaseHas('user_vips', [
            'id' => $active->id,
            'status' => UserVip::STATUS_ACTIVE,
        ]);
        $this->assertFalse((bool) $user->fresh()->profile->is_vip);
        $this->assertTrue((bool) $activeUser->fresh()->profile->is_vip);

        Event::assertDispatched(VipExpired::class, function (VipExpired $event) use ($expired): bool {
            return $event->subscription->id === $expired->id
                && $event->subscription->status === UserVip::STATUS_EXPIRED;
        });
    }

    public function test_me_endpoint_expires_stale_subscription_lazily(): void
    {
        Event::fake([VipExpired::class]);

        $user = $this->userWithProfile(['is_vip' => true]);
        $level = VipLevel::factory()->create(['level' => 1]);

        $subscription = UserVip::factory()->create([
            'user_id' => $user->id,
            'vip_level_id' => $level->id,
            'status' => UserVip::STATUS_ACTIVE,
            'expires_at' => now()->subSecond(),
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/vip/me')
            ->assertOk()
            ->assertJsonPath('data.is_vip', false)
            ->assertJsonPath('data.level', null);

        $this->assertDatabaseMissing('user_vips', ['id' => $subscription->id]);
        $this->assertFalse((bool) $user->fresh()->profile->is_vip);
        Event::assertDispatched(VipExpired::class);
    }

    public function test_vip_privileges_are_available_while_active(): void
    {
        $user = $this->userWithProfile(['is_vip' => true]);
        $level = VipLevel::factory()->create([
            'level' => 2,
            'badge' => 'vip-badge',
            'profile_frame' => 'vip-frame',
            'chat_effect' => 'vip-chat',
            'entrance_animation' => 'vip-entrance',
            'exclusive_gifts' => true,
        ]);

        UserVip::factory()->create([
            'user_id' => $user->id,
            'vip_level_id' => $level->id,
            'status' => UserVip::STATUS_ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);

        $checker = app(CheckVipPrivileges::class);
        $privileges = $checker->execute($user);

        $this->assertNotNull($privileges);
        $this->assertSame('vip-badge', $privileges->badge);
        $this->assertSame('vip-frame', $privileges->profileFrame);
        $this->assertTrue($checker->has($user, 'badge'));
        $this->assertTrue($checker->has($user, 'profile_frame'));
        $this->assertTrue($checker->has($user, 'chat_effect'));
        $this->assertTrue($checker->has($user, 'entrance_animation'));
        $this->assertTrue($checker->has($user, 'exclusive_gifts'));
        $this->assertFalse($checker->has($user, 'unknown'));
    }

    public function test_vip_privileges_are_denied_without_active_subscription(): void
    {
        $user = $this->userWithProfile();

        $checker = app(CheckVipPrivileges::class);

        $this->assertNull($checker->execute($user));
        $this->assertFalse($checker->has($user, 'badge'));
        $this->assertFalse($checker->has($user, 'exclusive_gifts'));
    }

    public function test_guests_cannot_purchase_or_upgrade_vip(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        Profile::factory()->create(['user_id' => $guest->id]);
        $level = VipLevel::factory()->create(['level' => 1, 'coin_price' => 100]);

        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/vip/purchase', [
            'vip_level_id' => $level->id,
        ])->assertForbidden();

        $this->postJson('/api/v1/vip/upgrade', [
            'vip_level_id' => $level->id,
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/vip/levels')->assertUnauthorized();
        $this->getJson('/api/v1/vip/me')->assertUnauthorized();
        $this->postJson('/api/v1/vip/purchase', [])->assertUnauthorized();
        $this->postJson('/api/v1/vip/upgrade', [])->assertUnauthorized();
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
        ], $profileAttributes));

        return $user->fresh(['profile', 'wallet']);
    }
}
