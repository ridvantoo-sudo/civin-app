<?php

namespace Tests\Feature;

use App\Features\FamilyClub\Models\Family;
use App\Features\FamilyClub\Models\FamilyInvite;
use App\Features\FamilyClub\Models\FamilyMember;
use App\Features\FamilyClub\Models\FamilyMission;
use App\Features\Gifts\Models\Gift;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FamilyClubSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_create_family(): void
    {
        $owner = $this->userWithProfile();

        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/families/create', [
            'name' => 'Nova Clan',
            'description' => 'We play together',
            'logo' => 'https://cdn.example.com/family.png',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Nova Clan')
            ->assertJsonPath('data.status', Family::STATUS_ACTIVE)
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.badge_key', Family::BADGE_BRONZE)
            ->assertJsonPath('data.owner.id', $owner->id);

        $familyId = $response->json('data.id');

        $this->assertDatabaseHas('families', [
            'id' => $familyId,
            'owner_id' => $owner->id,
            'name' => 'Nova Clan',
        ]);

        $this->assertDatabaseHas('family_members', [
            'family_id' => $familyId,
            'user_id' => $owner->id,
            'role' => FamilyMember::ROLE_OWNER,
            'status' => FamilyMember::STATUS_APPROVED,
        ]);
    }

    public function test_owner_can_invite_and_member_can_join_by_code(): void
    {
        $owner = $this->userWithProfile();
        $member = $this->userWithProfile();
        $family = $this->createFamilyFor($owner);

        Sanctum::actingAs($owner);
        $inviteResponse = $this->postJson("/api/v1/families/{$family->id}/invites", [
            'role' => FamilyMember::ROLE_MEMBER,
        ])->assertCreated();

        $code = $inviteResponse->json('data.code');
        $this->assertNotEmpty($code);

        Sanctum::actingAs($member);
        $this->postJson('/api/v1/families/join', [
            'code' => $code,
        ])->assertCreated()
            ->assertJsonPath('data.status', FamilyMember::STATUS_APPROVED)
            ->assertJsonPath('data.role', FamilyMember::ROLE_MEMBER)
            ->assertJsonPath('data.user.id', $member->id);

        $this->assertSame(2, $family->fresh()->members_count);
        $this->assertDatabaseHas('family_invites', [
            'code' => $code,
            'status' => FamilyInvite::STATUS_ACCEPTED,
        ]);
    }

    public function test_owner_can_update_member_role(): void
    {
        $owner = $this->userWithProfile();
        $member = $this->userWithProfile();
        $family = $this->createFamilyFor($owner);

        FamilyMember::query()->create([
            'family_id' => $family->id,
            'user_id' => $member->id,
            'role' => FamilyMember::ROLE_MEMBER,
            'status' => FamilyMember::STATUS_APPROVED,
            'joined_at' => now(),
            'contribution_xp' => 0,
            'contribution_gifts' => 0,
        ]);
        $family->increment('members_count');

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/families/{$family->id}/roles", [
            'user_id' => $member->id,
            'role' => FamilyMember::ROLE_ADMIN,
        ])->assertOk()
            ->assertJsonPath('data.role', FamilyMember::ROLE_ADMIN);
    }

    public function test_member_can_chat_contribute_and_claim_mission(): void
    {
        $owner = $this->userWithProfile(coins: 500);
        $family = $this->createFamilyFor($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/families/{$family->id}/messages", [
            'body' => 'Welcome home',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Welcome home');

        $this->postJson("/api/v1/families/{$family->id}/wallet/contribute", [
            'amount' => 50,
            'currency' => 'coins',
        ])->assertCreated()
            ->assertJsonPath('data.amount', 50);

        $this->assertSame(50, $family->fresh()->wallet_coins);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson("/api/v1/families/{$family->id}/messages", [
                'body' => "Chat {$i}",
            ])->assertCreated();
        }

        $mission = FamilyMission::query()->where('key', 'daily_chat')->firstOrFail();
        $missions = $this->getJson("/api/v1/families/{$family->id}/missions")
            ->assertOk()
            ->json('data');

        $chatMission = collect($missions)->firstWhere('key', 'daily_chat');
        $this->assertTrue($chatMission['is_claimable']);

        $this->postJson("/api/v1/families/{$family->id}/missions/{$mission->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.is_claimable', false);
    }

    public function test_member_can_send_family_gift_and_view_rankings(): void
    {
        $owner = $this->userWithProfile(coins: 1000);
        $family = $this->createFamilyFor($owner);
        $gift = $this->createGift(coinPrice: 20);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/families/{$family->id}/gifts", [
            'gift_id' => $gift->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.coins', 40);

        $this->getJson("/api/v1/families/{$family->id}/badge")
            ->assertOk()
            ->assertJsonPath('data.badge_key', Family::BADGE_BRONZE);

        $this->getJson("/api/v1/families/{$family->id}/level")
            ->assertOk()
            ->assertJsonPath('data.level', $family->fresh()->level);

        $this->getJson('/api/v1/families/rankings')
            ->assertOk()
            ->assertJsonPath('data.0.id', $family->id);
    }

    public function test_me_returns_current_family(): void
    {
        $owner = $this->userWithProfile();
        $family = $this->createFamilyFor($owner, ['name' => 'My Family']);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/families/me')
            ->assertOk()
            ->assertJsonPath('data.id', $family->id)
            ->assertJsonPath('data.name', 'My Family');
    }

    private function createFamilyFor(User $owner, array $attributes = []): Family
    {
        $family = Family::query()->create(array_merge([
            'owner_id' => $owner->id,
            'name' => 'Test Family',
            'slug' => 'test-family-'.substr($owner->id, 0, 8),
            'description' => null,
            'logo' => null,
            'badge_key' => Family::BADGE_BRONZE,
            'badge_url' => null,
            'level' => 1,
            'xp' => 0,
            'xp_to_next_level' => Family::xpRequiredForLevel(1),
            'status' => Family::STATUS_ACTIVE,
            'members_count' => 1,
            'max_members' => 50,
            'ranking_score' => 0,
            'total_gifts_received' => 0,
            'total_gifts_sent' => 0,
            'wallet_coins' => 0,
            'wallet_diamonds' => 0,
        ], $attributes));

        FamilyMember::query()->create([
            'family_id' => $family->id,
            'user_id' => $owner->id,
            'role' => FamilyMember::ROLE_OWNER,
            'status' => FamilyMember::STATUS_APPROVED,
            'joined_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $owner->id,
            'contribution_xp' => 0,
            'contribution_gifts' => 0,
        ]);

        return $family;
    }

    private function userWithProfile(array $profileAttributes = [], int $coins = 0): User
    {
        $factory = User::factory();
        if ($coins > 0) {
            $factory = $factory->withCoins($coins);
        }

        $user = $factory->create([
            'status' => 'active',
            'is_guest' => false,
        ]);

        Profile::factory()->create(array_merge([
            'user_id' => $user->id,
            'display_name' => $user->username,
        ], $profileAttributes));

        return $user->fresh(['profile', 'wallet']);
    }

    private function createGift(int $coinPrice = 10): Gift
    {
        return Gift::factory()->create([
            'name' => 'Rose',
            'icon' => 'https://cdn.example.com/rose.png',
            'coin_price' => $coinPrice,
            'status' => Gift::STATUS_ACTIVE,
        ]);
    }
}
