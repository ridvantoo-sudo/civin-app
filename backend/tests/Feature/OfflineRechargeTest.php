<?php

namespace Tests\Feature;

use App\Features\Users\Models\User;
use App\Features\Wallet\Events\WalletUpdated;
use App\Features\Wallet\Models\OfflineRechargeAuditLog;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Models\WalletTransaction;
use App\Features\Wallet\Notifications\OfflineRechargeReviewedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflineRechargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_submit_offline_recharge_with_receipt(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/wallet/offline-recharges', [
            'coins' => 500,
            'amount' => 449,
            'currency' => 'USD',
            'reference_number' => 'BANK-123456',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJsonPath('data.status', OfflineRechargeRequest::STATUS_PENDING)
            ->assertJsonPath('data.coins', 500)
            ->assertJsonPath('data.reference_number', 'BANK-123456');

        $requestId = $response->json('data.id');
        $path = OfflineRechargeRequest::query()->findOrFail($requestId)->receipt_path;

        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('offline_recharge_audit_logs', [
            'offline_recharge_request_id' => $requestId,
            'actor_id' => $user->id,
            'action' => OfflineRechargeAuditLog::ACTION_SUBMITTED,
        ]);
        $this->assertSame(0, $user->fresh()->wallet->coins_balance);
    }

    public function test_admin_approval_credits_wallet_and_notifies_user(): void
    {
        Event::fake([WalletUpdated::class]);
        Notification::fake();

        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Sanctum::actingAs($user);
        $requestId = $this->post('/api/v1/wallet/offline-recharges', [
            'coins' => 1200,
            'amount' => 999,
            'currency' => 'USD',
            'reference_number' => 'WIRE-999',
            'receipt' => UploadedFile::fake()->image('receipt.png'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/offline-recharges')
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId);

        $this->patchJson("/api/v1/admin/offline-recharges/{$requestId}", [
            'status' => OfflineRechargeRequest::STATUS_APPROVED,
            'notes' => 'Verified bank transfer',
        ])->assertOk()
            ->assertJsonPath('data.status', OfflineRechargeRequest::STATUS_APPROVED)
            ->assertJsonPath('data.approved_by', $admin->id);

        $this->assertSame(1200, $user->fresh()->wallet->coins_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_OFFLINE_RECHARGE,
            'amount' => 1200,
            'currency' => WalletTransaction::CURRENCY_COINS,
            'reference_id' => $requestId,
        ]);
        $this->assertDatabaseHas('offline_recharge_audit_logs', [
            'offline_recharge_request_id' => $requestId,
            'actor_id' => $admin->id,
            'action' => OfflineRechargeAuditLog::ACTION_APPROVED,
        ]);

        Notification::assertSentTo($user, OfflineRechargeReviewedNotification::class);
        Event::assertDispatched(WalletUpdated::class);
    }

    public function test_admin_rejection_does_not_credit_wallet(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Sanctum::actingAs($user);
        $requestId = $this->post('/api/v1/wallet/offline-recharges', [
            'coins' => 100,
            'amount' => 99,
            'currency' => 'USD',
            'reference_number' => 'BAD-REF',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/offline-recharges/{$requestId}", [
            'status' => OfflineRechargeRequest::STATUS_REJECTED,
            'notes' => 'Unreadable receipt',
        ])->assertOk()
            ->assertJsonPath('data.status', OfflineRechargeRequest::STATUS_REJECTED);

        $this->assertSame(0, $user->fresh()->wallet->coins_balance);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_OFFLINE_RECHARGE,
        ]);
        $this->assertDatabaseHas('offline_recharge_audit_logs', [
            'offline_recharge_request_id' => $requestId,
            'action' => OfflineRechargeAuditLog::ACTION_REJECTED,
        ]);
        Notification::assertSentTo($user, OfflineRechargeReviewedNotification::class);
    }

    public function test_user_can_track_offline_recharge_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/wallet/offline-recharges', [
            'coins' => 100,
            'amount' => 99,
            'currency' => 'USD',
            'reference_number' => 'TRACK-1',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->getJson('/api/v1/wallet/offline-recharges')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', OfflineRechargeRequest::STATUS_PENDING)
            ->assertJsonPath('data.0.reference_number', 'TRACK-1');
    }

    public function test_duplicate_reference_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'coins' => 100,
            'amount' => 99,
            'currency' => 'USD',
            'reference_number' => 'DUP-REF',
        ];

        $this->post('/api/v1/wallet/offline-recharges', [
            ...$payload,
            'receipt' => UploadedFile::fake()->image('a.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->post('/api/v1/wallet/offline-recharges', [
            ...$payload,
            'receipt' => UploadedFile::fake()->image('b.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reference_number']);
    }

    public function test_guests_and_non_admins_are_blocked(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        $other = User::factory()->create();

        Sanctum::actingAs($guest);
        $this->post('/api/v1/wallet/offline-recharges', [
            'coins' => 10,
            'amount' => 10,
            'currency' => 'USD',
            'reference_number' => 'GUEST',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertForbidden();

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/admin/offline-recharges')->assertForbidden();
    }
}
