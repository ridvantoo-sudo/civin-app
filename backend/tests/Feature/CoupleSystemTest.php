<?php

namespace Tests\Feature;

use App\Features\Couple\Models\Couple;
use App\Features\Couple\Models\CoupleProposal;
use App\Features\Couple\Models\CoupleTask;
use App\Features\Gifts\Models\Gift;
use App\Features\Profiles\Models\Profile;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoupleSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_send_and_accept_proposal(): void
    {
        $a = $this->userWithProfile();
        $b = $this->userWithProfile();

        Sanctum::actingAs($a);
        $proposalResponse = $this->postJson('/api/v1/couples/propose', [
            'user_id' => $b->id,
            'message' => 'Be my partner',
        ])->assertCreated()
            ->assertJsonPath('data.status', CoupleProposal::STATUS_PENDING)
            ->assertJsonPath('data.from_user.id', $a->id)
            ->assertJsonPath('data.to_user.id', $b->id);

        $proposalId = $proposalResponse->json('data.id');

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/couples/proposals/{$proposalId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', Couple::STATUS_ACTIVE)
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.badge_key', Couple::BADGE_BRONZE);

        Sanctum::actingAs($a);
        $this->getJson('/api/v1/couples/me')
            ->assertOk()
            ->assertJsonPath('data.status', Couple::STATUS_ACTIVE)
            ->assertJsonPath('data.partner.id', $b->id);
    }

    public function test_recipient_can_reject_and_sender_can_cancel(): void
    {
        $a = $this->userWithProfile();
        $b = $this->userWithProfile();
        $c = $this->userWithProfile();

        Sanctum::actingAs($a);
        $proposalId = $this->postJson('/api/v1/couples/propose', [
            'user_id' => $b->id,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/couples/proposals/{$proposalId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', CoupleProposal::STATUS_REJECTED);

        Sanctum::actingAs($a);
        $cancelId = $this->postJson('/api/v1/couples/propose', [
            'user_id' => $c->id,
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/couples/proposals/{$cancelId}")
            ->assertOk()
            ->assertJsonPath('data.status', CoupleProposal::STATUS_CANCELLED);
    }

    public function test_couple_can_gift_check_in_claim_task_and_view_profile(): void
    {
        $a = $this->userWithProfile(coins: 1000);
        $b = $this->userWithProfile();
        $couple = $this->createCoupleFor($a, $b);
        $gift = $this->createGift(coinPrice: 20);

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/couples/{$couple->id}/gifts", [
            'gift_id' => $gift->id,
            'quantity' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.coins', 40)
            ->assertJsonPath('data.receiver.id', $b->id);

        $this->postJson("/api/v1/couples/{$couple->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.id', $couple->id);

        $missions = $this->getJson("/api/v1/couples/{$couple->id}/tasks")
            ->assertOk()
            ->json('data');

        $giftTask = collect($missions)->firstWhere('key', 'daily_gift');
        $this->assertTrue($giftTask['is_claimable']);

        $task = CoupleTask::query()->where('key', 'daily_gift')->firstOrFail();
        $this->postJson("/api/v1/couples/{$couple->id}/tasks/{$task->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.is_claimable', false);

        $this->patchJson("/api/v1/couples/{$couple->id}", [
            'display_name' => 'Star Pair',
        ])->assertOk()
            ->assertJsonPath('data.display_name', 'Star Pair');

        $this->getJson("/api/v1/couples/{$couple->id}/badge")
            ->assertOk()
            ->assertJsonPath('data.badge_key', Couple::BADGE_BRONZE);

        $this->getJson("/api/v1/couples/{$couple->id}/level")
            ->assertOk()
            ->assertJsonPath('data.couple_id', $couple->id);

        $this->getJson("/api/v1/couples/{$couple->id}/anniversary")
            ->assertOk()
            ->assertJsonPath('data.couple_id', $couple->id)
            ->assertJsonPath('data.days_together', 0);

        $this->getJson('/api/v1/couples/rankings')
            ->assertOk()
            ->assertJsonPath('data.0.id', $couple->id);
    }

    public function test_partner_can_end_relationship(): void
    {
        $a = $this->userWithProfile();
        $b = $this->userWithProfile();
        $couple = $this->createCoupleFor($a, $b);

        Sanctum::actingAs($a);
        $this->deleteJson("/api/v1/couples/{$couple->id}")
            ->assertOk()
            ->assertJsonPath('data.status', Couple::STATUS_ENDED);

        $this->getJson('/api/v1/couples/me')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    private function createCoupleFor(User $a, User $b, array $attributes = []): Couple
    {
        [$one, $two] = Couple::orderedPair($a->id, $b->id);

        return Couple::query()->create(array_merge([
            'user_one_id' => $one,
            'user_two_id' => $two,
            'proposer_id' => $a->id,
            'display_name' => null,
            'cover_url' => null,
            'badge_key' => Couple::BADGE_BRONZE,
            'badge_url' => null,
            'level' => 1,
            'xp' => 0,
            'xp_to_next_level' => Couple::xpRequiredForLevel(1),
            'status' => Couple::STATUS_ACTIVE,
            'anniversary_at' => now(),
            'last_anniversary_bonus_year' => null,
            'ranking_score' => 0,
            'total_gifts_sent' => 0,
        ], $attributes));
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
            'name' => 'Heart',
            'icon' => 'https://cdn.example.com/heart.png',
            'coin_price' => $coinPrice,
            'status' => Gift::STATUS_ACTIVE,
        ]);
    }
}
