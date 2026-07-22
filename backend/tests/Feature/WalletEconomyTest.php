<?php

namespace Tests\Feature;

use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\Gift;
use App\Features\LiveChat\Models\LiveChatSetting;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Models\LiveSession;
use App\Features\LiveStreaming\Models\LiveViewer;
use App\Features\Users\Models\User;
use App\Features\Wallet\Events\GiftBalanceChanged;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\RechargeOrder;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Models\WithdrawRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agora.app_id', str_repeat('a', 32));
        config()->set('agora.app_certificate', str_repeat('b', 32));
        config()->set('agora.token_ttl', 3600);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_wallet_is_created_for_new_users(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'coins_balance' => 0,
            'diamonds_balance' => 0,
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.coins_balance', 0)
            ->assertJsonPath('data.diamonds_balance', 0);
    }

    public function test_recharge_adds_coins_and_audits_transaction(): void
    {
        Event::fake([WalletUpdated::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/recharge', [
            'package_name' => 'Starter Pack',
            'coins' => 500,
            'price' => 499,
            'currency' => 'USD',
            'payment_provider' => 'apple',
            'transaction_id' => 'txn_apple_001',
        ])->assertCreated()
            ->assertJsonPath('data.package_name', 'Starter Pack')
            ->assertJsonPath('data.coins', 500)
            ->assertJsonPath('data.status', RechargeOrder::STATUS_COMPLETED);

        $this->assertSame(500, $user->fresh()->wallet->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_COIN_PURCHASE,
            'amount' => 500,
            'currency' => WalletTransaction::CURRENCY_COINS,
        ]);

        Event::assertDispatched(WalletUpdated::class, function (WalletUpdated $event) use ($user): bool {
            return $event->wallet->user_id === $user->id
                && $event->broadcastAs() === 'wallet.updated'
                && $event->broadcastOn()[0]->name === 'private-user.wallet.'.$user->id
                && $event->broadcastWith()['coins_balance'] === 500;
        });
    }

    public function test_recharge_is_idempotent_for_provider_transaction(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'package_name' => 'Starter Pack',
            'coins' => 200,
            'price' => 199,
            'currency' => 'USD',
            'payment_provider' => 'google',
            'transaction_id' => 'txn_google_dup',
        ];

        $first = $this->postJson('/api/v1/wallet/recharge', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/wallet/recharge', $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('recharge_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(200, $user->fresh()->wallet->coins_balance);
    }

    public function test_gift_deducts_coins_and_credits_diamonds_with_ledger(): void
    {
        Event::fake([GiftSent::class, WalletUpdated::class, GiftBalanceChanged::class]);

        $host = User::factory()->create();
        $viewer = User::factory()->withCoins(300)->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $viewer);
        $gift = Gift::factory()->create(['coin_price' => 40]);

        Sanctum::actingAs($viewer);
        $response = $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->assertSame(220, $viewer->fresh()->wallet->coins_balance);
        $this->assertSame(80, $host->fresh()->wallet->diamonds_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $viewer->id,
            'type' => WalletTransaction::TYPE_GIFT_SENT,
            'amount' => -80,
            'currency' => WalletTransaction::CURRENCY_COINS,
            'reference_id' => $response->json('data.id'),
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $host->id,
            'type' => WalletTransaction::TYPE_GIFT_RECEIVED,
            'amount' => 80,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
            'reference_id' => $response->json('data.id'),
        ]);

        Event::assertDispatched(GiftBalanceChanged::class);
        Event::assertDispatched(WalletUpdated::class);
    }

    public function test_withdraw_request_requires_admin_approval_before_diamond_deduction(): void
    {
        Event::fake([WalletUpdated::class]);

        $user = User::factory()->withDiamonds(250)->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/wallet/withdraw', [
            'diamonds' => 100,
            'amount' => 1500,
        ])->assertCreated()
            ->assertJsonPath('data.status', WithdrawRequest::STATUS_PENDING)
            ->assertJsonPath('data.diamonds', 100);

        $this->assertSame(250, $user->fresh()->wallet->diamonds_balance);
        $withdrawId = $response->json('data.id');

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/withdraw-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $withdrawId);

        $this->patchJson("/api/v1/admin/withdraw-requests/{$withdrawId}", [
            'status' => WithdrawRequest::STATUS_APPROVED,
            'notes' => 'Paid out',
        ])->assertOk()
            ->assertJsonPath('data.status', WithdrawRequest::STATUS_APPROVED)
            ->assertJsonPath('data.approved_by', $admin->id);

        $this->assertSame(150, $user->fresh()->wallet->diamonds_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_WITHDRAW,
            'amount' => -100,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
            'reference_id' => $withdrawId,
        ]);

        Event::assertDispatched(WalletUpdated::class);
    }

    public function test_withdraw_reject_does_not_deduct_diamonds(): void
    {
        $user = User::factory()->withDiamonds(80)->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Sanctum::actingAs($user);
        $withdrawId = $this->postJson('/api/v1/wallet/withdraw', [
            'diamonds' => 50,
            'amount' => 700,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/withdraw-requests/{$withdrawId}", [
            'status' => WithdrawRequest::STATUS_REJECTED,
        ])->assertOk()
            ->assertJsonPath('data.status', WithdrawRequest::STATUS_REJECTED);

        $this->assertSame(80, $user->fresh()->wallet->diamonds_balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_transaction_history_lists_audited_changes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/recharge', [
            'package_name' => 'Mega',
            'coins' => 1000,
            'price' => 999,
            'currency' => 'USD',
            'payment_provider' => 'stripe',
            'transaction_id' => 'txn_hist_1',
        ])->assertCreated();

        $this->getJson('/api/v1/wallet/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', WalletTransaction::TYPE_COIN_PURCHASE)
            ->assertJsonPath('data.0.amount', 1000)
            ->assertJsonPath('data.0.currency', WalletTransaction::CURRENCY_COINS);
    }

    public function test_security_checks_prevent_negative_balances_and_unauthorized_access(): void
    {
        $user = User::factory()->withCoins(10)->withDiamonds(5)->create();
        $other = User::factory()->create();
        $guest = User::factory()->create(['is_guest' => true]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw', [
            'diamonds' => 50,
            'amount' => 100,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['diamonds']);

        $host = User::factory()->create();
        $room = $this->liveRoom($host);
        $this->joinViewer($room, $user);
        $gift = Gift::factory()->create(['coin_price' => 50]);

        $this->postJson("/api/v1/live/{$room->id}/gifts/send", [
            'gift_id' => $gift->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['coins']);

        $this->assertSame(10, $user->fresh()->wallet->coins_balance);
        $this->assertSame(5, $user->fresh()->wallet->diamonds_balance);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/admin/withdraw-requests')->assertForbidden();

        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/wallet/withdraw', [
            'diamonds' => 1,
            'amount' => 1,
        ])->assertForbidden();
    }

    public function test_financial_actions_are_rate_limited(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        RateLimiter::clear('wallet-recharge:'.$user->id);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/wallet/recharge', [
                'package_name' => 'Pack',
                'coins' => 1,
                'price' => 1,
                'currency' => 'USD',
                'payment_provider' => 'apple',
                'transaction_id' => 'txn_rate_'.$i,
            ])->assertCreated();
        }

        $this->postJson('/api/v1/wallet/recharge', [
            'package_name' => 'Pack',
            'coins' => 1,
            'price' => 1,
            'currency' => 'USD',
            'payment_provider' => 'apple',
            'transaction_id' => 'txn_rate_overflow',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['recharge']);
    }

    public function test_unauthenticated_wallet_endpoints_are_rejected(): void
    {
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
        $this->getJson('/api/v1/wallet/transactions')->assertUnauthorized();
        $this->postJson('/api/v1/wallet/recharge', [])->assertUnauthorized();
        $this->postJson('/api/v1/wallet/withdraw', [])->assertUnauthorized();
    }

    private function liveRoom(User $host, string $status = 'live'): LiveRoom
    {
        $room = LiveRoom::factory()->create([
            'host_id' => $host->id,
            'status' => $status,
            'started_at' => $status === 'live' ? now() : null,
            'ended_at' => $status === 'ended' ? now() : null,
        ]);
        LiveSession::query()->create(['room_id' => $room->id]);
        LiveChatSetting::factory()->create(['room_id' => $room->id]);

        return $room->fresh();
    }

    private function joinViewer(LiveRoom $room, User $viewer): void
    {
        LiveViewer::factory()->create([
            'room_id' => $room->id,
            'user_id' => $viewer->id,
            'joined_at' => now(),
            'left_at' => null,
        ]);
    }
}
