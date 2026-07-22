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
