<?php

namespace Tests\Feature;

use App\Features\Agency\Events\AgencyCommissionCreated;
use App\Features\Agency\Events\AgencyMemberJoined;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyCommission;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Agency\Services\AgencyService;
use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgencySystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_create_agency(): void
    {
        $owner = $this->userWithProfile();

        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/agencies/create', [
            'name' => 'Star Agency',
            'description' => 'Top hosts',
            'logo' => 'https://cdn.example.com/agency.png',
            'commission_rate' => 15,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Star Agency')
            ->assertJsonPath('data.commission_rate', 15)
            ->assertJsonPath('data.status', Agency::STATUS_ACTIVE)
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.hosts_count', 0)
            ->assertJsonPath('data.owner.id', $owner->id);

        $agencyId = $response->json('data.id');

        $this->assertDatabaseHas('agencies', [
            'id' => $agencyId,
            'owner_id' => $owner->id,
            'name' => 'Star Agency',
            'commission_rate' => 15,
        ]);

        $this->assertDatabaseHas('agency_members', [
            'agency_id' => $agencyId,
            'user_id' => $owner->id,
            'role' => AgencyMember::ROLE_OWNER,
            'status' => AgencyMember::STATUS_APPROVED,
        ]);
    }

    public function test_user_can_view_agency_profile(): void
    {
        $owner = $this->userWithProfile();
        $agency = $this->createAgencyFor($owner, ['name' => 'Nova Live']);

        Sanctum::actingAs($this->userWithProfile());
        $this->getJson("/api/v1/agencies/{$agency->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $agency->id)
            ->assertJsonPath('data.name', 'Nova Live')
            ->assertJsonPath('data.owner.id', $owner->id);
    }

    public function test_host_can_apply_and_owner_can_approve(): void
    {
        Event::fake([AgencyMemberJoined::class]);

        $owner = $this->userWithProfile();
        $host = $this->userWithProfile();
        $agency = $this->createAgencyFor($owner);

        Sanctum::actingAs($host);
        $this->postJson("/api/v1/agencies/{$agency->id}/apply", [
            'message' => 'I stream daily',
        ])->assertCreated()
            ->assertJsonPath('data.status', AgencyMember::STATUS_PENDING)
            ->assertJsonPath('data.role', AgencyMember::ROLE_HOST)
            ->assertJsonPath('data.user.id', $host->id);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/agencies/{$agency->id}/approve", [
            'user_id' => $host->id,
        ])->assertOk()
            ->assertJsonPath('data.status', AgencyMember::STATUS_APPROVED)
            ->assertJsonPath('data.user.id', $host->id);

        $this->assertDatabaseHas('agency_members', [
            'agency_id' => $agency->id,
            'user_id' => $host->id,
            'status' => AgencyMember::STATUS_APPROVED,
            'role' => AgencyMember::ROLE_HOST,
        ]);

        $this->assertSame(2, $agency->fresh()->members_count);
        $this->assertSame(1, $agency->fresh()->hosts_count);

        Event::assertDispatched(AgencyMemberJoined::class, function (AgencyMemberJoined $event) use ($host, $agency): bool {
            return $event->member->user_id === $host->id
                && $event->member->agency_id === $agency->id
                && $event->member->status === AgencyMember::STATUS_APPROVED;
        });
    }

    public function test_owner_can_reject_application(): void
    {
        $owner = $this->userWithProfile();
        $host = $this->userWithProfile();
        $agency = $this->createAgencyFor($owner);

        AgencyMember::factory()->pending()->create([
            'agency_id' => $agency->id,
            'user_id' => $host->id,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/agencies/{$agency->id}/reject", [
            'user_id' => $host->id,
        ])->assertOk()
            ->assertJsonPath('data.status', AgencyMember::STATUS_REJECTED);

        $this->assertDatabaseHas('agency_members', [
            'agency_id' => $agency->id,
            'user_id' => $host->id,
            'status' => AgencyMember::STATUS_REJECTED,
        ]);
    }

    public function test_owner_can_remove_host_member(): void
    {
        $owner = $this->userWithProfile();
        $host = $this->userWithProfile();
        $agency = $this->createAgencyFor($owner, [
            'members_count' => 2,
            'hosts_count' => 1,
        ]);

        AgencyMember::factory()->approved()->create([
            'agency_id' => $agency->id,
            'user_id' => $host->id,
            'role' => AgencyMember::ROLE_HOST,
        ]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/agencies/{$agency->id}/members/{$host->id}")
            ->assertOk()
            ->assertJsonPath('data.status', AgencyMember::STATUS_REMOVED);

        $this->assertSame(1, $agency->fresh()->members_count);
        $this->assertSame(0, $agency->fresh()->hosts_count);
    }

    public function test_owner_can_list_hosts_and_earnings(): void
    {
        $owner = $this->userWithProfile();
        $host = $this->userWithProfile();
        $agency = $this->createAgencyFor($owner, [
            'members_count' => 2,
            'hosts_count' => 1,
            'total_gross_earnings' => 1000,
            'total_commission' => 100,
        ]);

        $member = AgencyMember::factory()->approved()->create([
            'agency_id' => $agency->id,
            'user_id' => $host->id,
            'role' => AgencyMember::ROLE_HOST,
            'gross_earnings' => 1000,
            'commission_paid' => 100,
        ]);

        AgencyCommission::factory()->create([
            'agency_id' => $agency->id,
            'host_id' => $host->id,
            'agency_member_id' => $member->id,
            'gross_amount' => 1000,
            'commission_rate' => 10,
            'commission_amount' => 100,
            'host_net_amount' => 900,
            'source_type' => (new GiftTransaction)->getMorphClass(),
            'source_id' => GiftTransaction::factory()->create([
                'receiver_id' => $host->id,
            ])->id,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/agencies/{$agency->id}/hosts")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $host->id)
            ->assertJsonPath('data.0.gross_earnings', 1000)
            ->assertJsonPath('data.0.commission_paid', 100);

        $this->getJson("/api/v1/agencies/{$agency->id}/earnings")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.commission_amount', 100)
            ->assertJsonPath('data.0.host.id', $host->id);
    }

    public function test_commission_is_calculated_and_audited_from_host_gift_earnings(): void
    {
        Event::fake([AgencyCommissionCreated::class]);

        $owner = User::factory()->withDiamonds(0)->create();
        Profile::factory()->create(['user_id' => $owner->id, 'display_name' => $owner->username]);
        $host = User::factory()->withDiamonds(1000)->create();
        Profile::factory()->create(['user_id' => $host->id, 'display_name' => $host->username]);

        $agency = $this->createAgencyFor($owner, ['commission_rate' => 10]);
        $member = AgencyMember::factory()->approved()->create([
            'agency_id' => $agency->id,
            'user_id' => $host->id,
            'role' => AgencyMember::ROLE_HOST,
        ]);
        $agency->forceFill(['members_count' => 2, 'hosts_count' => 1])->save();

        $gift = Gift::factory()->create(['coin_price' => 100]);
        $room = LiveRoom::factory()->create(['host_id' => $host->id]);
        $sender = User::factory()->create();

        $transaction = GiftTransaction::factory()->create([
            'sender_id' => $sender->id,
            'receiver_id' => $host->id,
            'room_id' => $room->id,
            'gift_id' => $gift->id,
            'quantity' => 5,
            'coins' => 500,
        ]);

        $commission = app(AgencyService::class)->applyGiftCommission($transaction);

        $this->assertNotNull($commission);
        $this->assertSame(500, $commission->gross_amount);
        $this->assertSame(50, $commission->commission_amount);
        $this->assertSame(450, $commission->host_net_amount);
        $this->assertSame(10.0, (float) $commission->commission_rate);

        $this->assertSame(950, $host->fresh()->wallet->diamonds_balance);
        $this->assertSame(50, $owner->fresh()->wallet->diamonds_balance);

        $this->assertDatabaseHas('agency_commissions', [
            'agency_id' => $agency->id,
            'host_id' => $host->id,
            'agency_member_id' => $member->id,
            'gross_amount' => 500,
            'commission_amount' => 50,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $host->id,
            'type' => AgencyCommission::WALLET_TYPE_DEBIT,
            'amount' => -50,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $owner->id,
            'type' => AgencyCommission::WALLET_TYPE_CREDIT,
            'amount' => 50,
            'currency' => WalletTransaction::CURRENCY_DIAMONDS,
        ]);

        $this->assertSame(500, $agency->fresh()->total_gross_earnings);
        $this->assertSame(50, $agency->fresh()->total_commission);
        $this->assertSame(500, $member->fresh()->gross_earnings);
        $this->assertSame(50, $member->fresh()->commission_paid);

        Event::assertDispatched(AgencyCommissionCreated::class, function (AgencyCommissionCreated $event) use ($agency): bool {
            return $event->commission->agency_id === $agency->id
                && $event->commission->commission_amount === 50;
        });

        $again = app(AgencyService::class)->applyGiftCommission($transaction);
        $this->assertSame($commission->id, $again?->id);
        $this->assertSame(950, $host->fresh()->wallet->diamonds_balance);
        $this->assertDatabaseCount('agency_commissions', 1);
    }

    public function test_only_owner_can_manage_agency_and_permissions_are_enforced(): void
    {
        $owner = $this->userWithProfile();
        $host = $this->userWithProfile();
        $stranger = $this->userWithProfile();
        $agency = $this->createAgencyFor($owner);

        AgencyMember::factory()->pending()->create([
            'agency_id' => $agency->id,
            'user_id' => $host->id,
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/agencies/{$agency->id}/approve", [
            'user_id' => $host->id,
        ])->assertForbidden();

        $this->postJson("/api/v1/agencies/{$agency->id}/reject", [
            'user_id' => $host->id,
        ])->assertForbidden();

        $this->getJson("/api/v1/agencies/{$agency->id}/hosts")->assertForbidden();
        $this->getJson("/api/v1/agencies/{$agency->id}/earnings")->assertForbidden();

        Sanctum::actingAs($owner);
        $pending = AgencyMember::query()
            ->where('agency_id', $agency->id)
            ->where('user_id', $host->id)
            ->firstOrFail();
        $pending->forceFill([
            'status' => AgencyMember::STATUS_APPROVED,
            'role' => AgencyMember::ROLE_HOST,
            'reviewed_at' => now(),
            'reviewed_by' => $owner->id,
        ])->save();
        $agency->forceFill(['members_count' => 2, 'hosts_count' => 1])->save();

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/v1/agencies/{$agency->id}/members/{$host->id}")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/agencies/{$agency->id}/members/{$owner->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    public function test_guests_cannot_create_or_apply_to_agency(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        Profile::factory()->create(['user_id' => $guest->id]);
        $owner = $this->userWithProfile();
        $agency = $this->createAgencyFor($owner);

        Sanctum::actingAs($guest);
        $this->postJson('/api/v1/agencies/create', [
            'name' => 'Guest Agency',
        ])->assertForbidden();

        $this->postJson("/api/v1/agencies/{$agency->id}/apply")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $agency = Agency::factory()->create();

        $this->postJson('/api/v1/agencies/create')->assertUnauthorized();
        $this->getJson("/api/v1/agencies/{$agency->id}")->assertUnauthorized();
        $this->postJson("/api/v1/agencies/{$agency->id}/apply")->assertUnauthorized();
        $this->postJson("/api/v1/agencies/{$agency->id}/approve")->assertUnauthorized();
        $this->postJson("/api/v1/agencies/{$agency->id}/reject")->assertUnauthorized();
        $this->deleteJson("/api/v1/agencies/{$agency->id}/members/{$agency->owner_id}")->assertUnauthorized();
        $this->getJson("/api/v1/agencies/{$agency->id}/hosts")->assertUnauthorized();
        $this->getJson("/api/v1/agencies/{$agency->id}/earnings")->assertUnauthorized();
    }

    public function test_user_cannot_create_second_agency_or_apply_while_belonging_to_one(): void
    {
        $owner = $this->userWithProfile();
        $this->createAgencyFor($owner);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/agencies/create', [
            'name' => 'Second Agency',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $otherOwner = $this->userWithProfile();
        $otherAgency = $this->createAgencyFor($otherOwner);

        $this->postJson("/api/v1/agencies/{$otherAgency->id}/apply")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agency']);
    }

    private function createAgencyFor(User $owner, array $attributes = []): Agency
    {
        $agency = Agency::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'commission_rate' => 10,
            'members_count' => 1,
            'hosts_count' => 0,
        ], $attributes));

        AgencyMember::factory()->owner()->create([
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'reviewed_by' => $owner->id,
        ]);

        return $agency->fresh(['owner.profile']);
    }

    private function userWithProfile(array $profileAttributes = []): User
    {
        $user = User::factory()->create();
        Profile::factory()->create(array_merge([
            'user_id' => $user->id,
            'display_name' => $user->username,
        ], $profileAttributes));

        return $user->fresh(['profile', 'wallet']);
    }
}
