<?php

namespace Tests\Feature;

use App\Features\Kyc\Models\KycAuditLog;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Notifications\KycReviewedNotification;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_submit_passport_kyc(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/kyc/submit', [
            'document_type' => KycSubmission::DOCUMENT_PASSPORT,
            'document_number' => 'P1234567',
            'full_legal_name' => 'Ada Lovelace',
            'nationality' => 'GB',
            'document_front' => UploadedFile::fake()->image('passport.jpg', 800, 600),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 600),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJsonPath('data.status', KycSubmission::STATUS_PENDING)
            ->assertJsonPath('data.document_type', KycSubmission::DOCUMENT_PASSPORT)
            ->assertJsonPath('data.full_legal_name', 'Ada Lovelace')
            ->assertJsonPath('data.withdrawal_verified', false);

        $submission = KycSubmission::query()->where('user_id', $user->id)->firstOrFail();
        Storage::disk('public')->assertExists($submission->document_front_path);
        Storage::disk('public')->assertExists($submission->selfie_path);
        $this->assertDatabaseHas('kyc_audit_logs', [
            'kyc_submission_id' => $submission->id,
            'actor_id' => $user->id,
            'action' => KycAuditLog::ACTION_SUBMITTED,
        ]);
    }

    public function test_national_id_requires_back_photo(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/kyc/submit', [
            'document_type' => KycSubmission::DOCUMENT_NATIONAL_ID,
            'document_number' => 'N998877',
            'full_legal_name' => 'Grace Hopper',
            'document_front' => UploadedFile::fake()->image('front.jpg', 800, 600),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 600),
        ], [
            'Accept' => 'application/json',
        ])->assertUnprocessable();
    }

    public function test_admin_can_approve_kyc_and_enable_withdrawals(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Sanctum::actingAs($user);
        $kycId = $this->post('/api/v1/kyc/submit', [
            'document_type' => KycSubmission::DOCUMENT_PASSPORT,
            'document_number' => 'X555',
            'full_legal_name' => 'Alan Turing',
            'document_front' => UploadedFile::fake()->image('passport.jpg', 800, 600),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 600),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->patch("/api/v1/admin/kyc/{$kycId}", [
            'status' => KycSubmission::STATUS_APPROVED,
            'face_match_status' => KycSubmission::FACE_MATCH_MATCHED,
            'face_match_score' => 95,
            'withdrawal_verified' => true,
            'notes' => 'Looks good',
        ], [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('data.status', KycSubmission::STATUS_APPROVED)
            ->assertJsonPath('data.withdrawal_verified', true)
            ->assertJsonPath('data.face_match_status', KycSubmission::FACE_MATCH_MATCHED);

        Notification::assertSentTo($user, KycReviewedNotification::class);

        Sanctum::actingAs($user);
        $this->get('/api/v1/kyc/me', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', KycSubmission::STATUS_APPROVED)
            ->assertJsonPath('data.withdrawal_verified', true);
    }

    public function test_withdraw_requires_verified_kyc(): void
    {
        $user = User::factory()->withDiamonds(5000)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/withdraw', [
            'diamonds' => 100,
            'amount' => 100,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['kyc']);
    }
}
