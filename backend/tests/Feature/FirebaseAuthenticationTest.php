<?php

namespace Tests\Feature;

use App\Features\Authentication\DTOs\FirebaseIdentity;
use App\Features\Authentication\Models\RefreshToken;
use App\Features\Authentication\Services\FirebaseTokenVerifier;
use App\Features\Authentication\Services\InvalidFirebaseToken;
use App\Features\Devices\Models\Device;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FirebaseAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payload = [
            'id_token' => str_repeat('valid-token', 3),
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Test iPhone',
            'platform' => 'ios',
            'token' => 'push-token',
        ];

        $this->fakeFirebase(new FirebaseIdentity(
            uid: 'firebase-uid-123',
            email: 'firebase@example.com',
            name: 'Firebase Person',
            avatar: 'https://example.com/avatar.jpg',
            emailVerified: true,
            expiresAt: now()->addHour(),
        ));
    }

    public function test_valid_firebase_token_returns_sanctum_session_and_me(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->postJson('/api/v1/auth/firebase/login', $this->payload)
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'firebase@example.com')
            ->assertJsonPath('profile.display_name', 'Firebase Person')
            ->assertJsonStructure(['access_token', 'user', 'profile', 'expires_at']);

        $this->withToken($response->json('access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('profile.avatar_url', 'https://example.com/avatar.jpg');

        $device = Device::query()->firstOrFail();
        $this->assertSame($this->payload['device_id'], $device->device_uuid);
        $this->assertSame('203.0.113.8', $device->ip_address);
        $this->assertSame('push-token', $device->push_token);
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_invalid_firebase_token_is_rejected(): void
    {
        $this->app->instance(FirebaseTokenVerifier::class, new class implements FirebaseTokenVerifier
        {
            public function verify(string $idToken): FirebaseIdentity
            {
                throw new InvalidFirebaseToken('Expired token details');
            }
        });

        $this->postJson('/api/v1/auth/firebase/login', $this->payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_token')
            ->assertJsonMissing(['message' => 'Expired token details']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_firebase_user_and_profile_are_created(): void
    {
        $this->postJson('/api/v1/auth/firebase/login', $this->payload)->assertOk();

        $user = User::query()->where('firebase_uid', 'firebase-uid-123')->firstOrFail();
        $this->assertSame('firebase@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Firebase Person', $user->profile()->firstOrFail()->display_name);
    }

    public function test_existing_firebase_user_logs_in_without_duplication(): void
    {
        $user = User::factory()->create(['firebase_uid' => 'firebase-uid-123']);
        $user->profile()->create(['display_name' => 'Existing Person']);

        $this->postJson('/api/v1/auth/firebase/login', $this->payload)
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('profile.display_name', 'Existing Person');

        $this->assertDatabaseCount('users', 1);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_apple_style_firebase_identity_issues_sanctum_session(): void
    {
        $this->fakeFirebase(new FirebaseIdentity(
            uid: 'apple-firebase-uid-456',
            email: 'hid.user@privaterelay.appleid.com',
            name: 'Ada Lovelace',
            avatar: null,
            emailVerified: true,
            expiresAt: now()->addHour(),
        ));

        $this->postJson('/api/v1/auth/firebase/login', [
            ...$this->payload,
            'id_token' => str_repeat('apple-token', 3),
            'device_name' => 'iPhone 16 Pro',
            'platform' => 'ios',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'hid.user@privaterelay.appleid.com')
            ->assertJsonPath('profile.display_name', 'Ada Lovelace')
            ->assertJsonStructure(['access_token', 'refresh_token', 'user', 'profile']);

        $user = User::query()->where('firebase_uid', 'apple-firebase-uid-456')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->is_guest);
    }

    public function test_apple_hide_my_email_without_name_still_creates_profile(): void
    {
        $this->fakeFirebase(new FirebaseIdentity(
            uid: 'apple-firebase-uid-789',
            email: 'relay@privaterelay.appleid.com',
            name: null,
            avatar: null,
            emailVerified: true,
            expiresAt: now()->addHour(),
        ));

        $this->postJson('/api/v1/auth/firebase/login', [
            ...$this->payload,
            'id_token' => str_repeat('apple-token-b', 2),
            'platform' => 'ios',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'relay@privaterelay.appleid.com')
            ->assertJsonPath('profile.display_name', 'relay');

        $this->assertDatabaseHas('users', [
            'firebase_uid' => 'apple-firebase-uid-789',
            'email' => 'relay@privaterelay.appleid.com',
        ]);
    }

    public function test_new_firebase_login_revokes_old_tokens(): void
    {
        $first = $this->postJson('/api/v1/auth/firebase/login', $this->payload)->assertOk();
        $oldAccessToken = $first->json('access_token');
        $oldRefreshToken = $first->json('refresh_token');

        $second = $this->postJson('/api/v1/auth/firebase/login', $this->payload)->assertOk();

        $this->withToken($oldAccessToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $oldRefreshToken])
            ->assertUnprocessable();
        $this->withToken($second->json('access_token'))->getJson('/api/v1/auth/me')->assertOk();
        $this->assertSame(1, RefreshToken::query()->whereNull('revoked_at')->count());
    }

    public function test_soft_deleted_firebase_user_is_restored_on_login(): void
    {
        $user = User::factory()->create([
            'firebase_uid' => 'firebase-uid-123',
            'email' => 'firebase@example.com',
            'status' => User::STATUS_DELETED,
        ]);
        $user->profile()->create(['display_name' => 'Restored Person']);
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->postJson('/api/v1/auth/firebase/login', $this->payload)
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('profile.display_name', 'Restored Person');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firebase_uid' => 'firebase-uid-123',
            'status' => User::STATUS_ACTIVE,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_soft_deleted_email_holder_is_restored_and_linked(): void
    {
        $stale = User::factory()->create([
            'email' => 'firebase@example.com',
            'firebase_uid' => null,
            'status' => User::STATUS_DELETED,
        ]);
        $stale->profile()->create(['display_name' => 'Stale Person']);
        // Simulate an incomplete delete that left the unique email occupied.
        $stale->delete();

        $this->postJson('/api/v1/auth/firebase/login', $this->payload)
            ->assertOk()
            ->assertJsonPath('user.id', $stale->id)
            ->assertJsonPath('user.email', 'firebase@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $stale->id,
            'firebase_uid' => 'firebase-uid-123',
            'email' => 'firebase@example.com',
            'status' => User::STATUS_ACTIVE,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_email_account_is_linked_to_firebase_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'firebase@example.com',
            'firebase_uid' => null,
        ]);
        $user->profile()->create(['display_name' => 'Email Person']);

        $this->postJson('/api/v1/auth/firebase/login', $this->payload)
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('profile.display_name', 'Email Person');

        $this->assertSame('firebase-uid-123', $user->fresh()->firebase_uid);
        $this->assertDatabaseCount('users', 1);
    }

    private function fakeFirebase(FirebaseIdentity $identity): void
    {
        $this->app->instance(FirebaseTokenVerifier::class, new class($identity) implements FirebaseTokenVerifier
        {
            public function __construct(private readonly FirebaseIdentity $identity) {}

            public function verify(string $idToken): FirebaseIdentity
            {
                return $this->identity;
            }
        });
    }
}
