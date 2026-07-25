<?php

namespace Tests\Feature;

use App\Features\Growth\Jobs\DispatchCampaignJob;
use App\Features\Growth\Models\GrowthCampaign;
use App\Features\Growth\Models\Invite;
use App\Features\Growth\Models\PromoCode;
use App\Features\Growth\Models\ReferralAttribution;
use App\Features\Growth\Support\CampaignStatus;
use App\Features\Growth\Support\PromoRewardType;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        config()->set('growth.referral.referrer_coins', 100);
        config()->set('growth.referral.invitee_coins', 50);
        config()->set('growth.invite.inviter_coins', 150);
        config()->set('growth.invite.invitee_coins', 75);
        config()->set('growth.campaigns.fcm.enabled', false);
    }

    public function test_growth_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/growth/referrals/me')->assertUnauthorized();
        $this->postJson('/api/v1/growth/promo-codes/redeem', ['code' => 'X'])->assertUnauthorized();
        $this->getJson('/api/v1/growth/campaigns')->assertUnauthorized();
    }

    public function test_referral_code_created_and_attach_rewards_both_sides(): void
    {
        $referrer = User::factory()->create();
        Sanctum::actingAs($referrer);
        $code = $this->getJson('/api/v1/growth/referrals/me')
            ->assertOk()
            ->json('data.code');
        $this->assertNotEmpty($code);

        $invitee = User::factory()->create();
        Sanctum::actingAs($invitee);

        $this->postJson('/api/v1/growth/referrals/attach', ['code' => $code])
            ->assertCreated()
            ->assertJsonPath('data.status', ReferralAttribution::STATUS_REWARDED);

        $this->assertSame(100, (int) Wallet::query()->where('user_id', $referrer->id)->value('coins_balance'));
        $this->assertSame(50, (int) Wallet::query()->where('user_id', $invitee->id)->value('coins_balance'));
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $referrer->id,
            'type' => WalletTransaction::TYPE_GROWTH_REWARD,
            'amount' => 100,
        ]);

        // Second attach rejected.
        $this->postJson('/api/v1/growth/referrals/attach', ['code' => $code])->assertStatus(422);
    }

    public function test_cannot_use_own_referral_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $code = $this->getJson('/api/v1/growth/referrals/me')->json('data.code');
        $this->postJson('/api/v1/growth/referrals/attach', ['code' => $code])->assertStatus(422);
    }

    public function test_invite_create_and_accept_grants_rewards(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();

        Sanctum::actingAs($inviter);
        $token = $this->postJson('/api/v1/growth/invites', ['channel' => 'link'])
            ->assertCreated()
            ->json('data.token');

        Sanctum::actingAs($invitee);
        $this->postJson("/api/v1/growth/invites/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', Invite::STATUS_ACCEPTED);

        $this->assertSame(150, (int) Wallet::query()->where('user_id', $inviter->id)->value('coins_balance'));
        $this->assertSame(75, (int) Wallet::query()->where('user_id', $invitee->id)->value('coins_balance'));
        $this->assertDatabaseCount('invite_rewards', 2);
    }

    public function test_promo_code_redeem_credits_coins(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();

        Sanctum::actingAs($creator);
        $this->postJson('/api/v1/growth/promo-codes', [
            'code' => 'WELCOME50',
            'reward_type' => PromoRewardType::COINS,
            'reward_value' => 50,
            'max_redemptions' => 100,
        ])->assertCreated();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/growth/promo-codes/validate?code=WELCOME50')->assertOk();
        $this->postJson('/api/v1/growth/promo-codes/redeem', ['code' => 'welcome50'])
            ->assertCreated()
            ->assertJsonPath('data.coins_awarded', 50);

        $this->assertSame(50, (int) Wallet::query()->where('user_id', $user->id)->value('coins_balance'));
        $this->assertSame(1, (int) PromoCode::query()->where('code', 'WELCOME50')->value('redemptions_count'));

        // Per-user limit.
        $this->postJson('/api/v1/growth/promo-codes/redeem', ['code' => 'WELCOME50'])->assertStatus(422);
    }

    public function test_percent_off_promo_records_without_wallet_credit(): void
    {
        $creator = User::factory()->create();
        $user = User::factory()->withCoins(10)->create();

        Sanctum::actingAs($creator);
        $this->postJson('/api/v1/growth/promo-codes', [
            'code' => 'SAVE10',
            'reward_type' => PromoRewardType::PERCENT_OFF,
            'reward_value' => 10,
        ])->assertCreated();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/growth/promo-codes/redeem', ['code' => 'SAVE10'])->assertCreated();
        $this->assertSame(10, (int) Wallet::query()->where('user_id', $user->id)->value('coins_balance'));
    }

    public function test_campaign_create_and_dispatch_queues_job(): void
    {
        Bus::fake();
        Mail::fake();

        $user = User::factory()->create(['email' => 'host@civin.test']);
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/growth/campaigns', [
            'name' => 'Launch',
            'channel' => 'email',
            'subject' => 'Hello',
            'body' => 'Welcome to Civin',
            'audience' => ['type' => 'all'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/growth/campaigns/{$id}/dispatch")
            ->assertOk()
            ->assertJsonPath('data.status', CampaignStatus::SENDING);

        Bus::assertDispatched(DispatchCampaignJob::class);
        $this->assertDatabaseHas('growth_campaigns', [
            'id' => $id,
            'status' => CampaignStatus::SENDING,
        ]);
    }

    public function test_campaign_process_sends_email_deliveries(): void
    {
        Mail::fake();

        $creator = User::factory()->create(['email' => 'creator@civin.test']);
        $target = User::factory()->create(['email' => 'target@civin.test']);

        $campaign = GrowthCampaign::query()->create([
            'name' => 'Blast',
            'channel' => 'email',
            'status' => CampaignStatus::SENDING,
            'subject' => 'Hi',
            'body' => 'Body',
            'audience' => ['type' => 'user_ids', 'user_ids' => [$target->id]],
            'created_by' => $creator->id,
            'started_at' => now(),
        ]);

        $this->app->make(\App\Features\Growth\Services\CampaignService::class)->process($campaign);

        $campaign->refresh();
        $this->assertSame(CampaignStatus::SENT, $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertDatabaseHas('campaign_deliveries', [
            'campaign_id' => $campaign->id,
            'user_id' => $target->id,
            'channel' => 'email',
            'status' => 'sent',
        ]);
        Mail::assertSent(\App\Features\Growth\Mail\CampaignMail::class);
    }
}
