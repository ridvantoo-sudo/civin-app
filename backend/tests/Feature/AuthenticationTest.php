<?php

namespace Tests\Feature;

use App\Features\Authentication\DTOs\FirebaseIdentity;
use App\Features\Authentication\Models\RefreshToken;
use App\Features\Authentication\Services\FirebaseTokenVerifier;
use App\Features\Authentication\Services\InvalidFirebaseToken;
use App\Features\Devices\Models\Device;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private array $device;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->device = [
            'device_uuid' => (string) Str::uuid(),
            'platform' => 'ios',
            'name' => 'Test iPhone',
            'app_version' => '1.0.0',
            'os_version' => '18.0',
        ];
    }

    public function test_register_login_logout_and_auth_guard(): void
    {
        $registered = $this->postJson('/api/v1/auth/register', [
            'email' => 'person@example.com',
            'username' => 'person',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'device' => $this->device,
        ])->assertCreated()->assertJsonPath('token_type', 'Bearer');

        $token = $registered->json('access_token');
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->asToken($token)->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.username', 'person');
        $this->postJson('/api/v1/auth/login', [
            'login' => 'person@example.com',
            'password' => 'wrong',
            'device' => $this->device,
        ])->assertUnprocessable();
        $this->asToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->asToken($token)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_refresh_rotates_and_replay_revokes_family(): void
    {
        $tokens = $this->register();
        $rotated = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk();

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertUnprocessable()->assertJsonValidationErrors('refresh_token');

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $rotated->json('refresh_token'),
        ])->assertUnprocessable();
    }

    public function test_login_succeeds_on_multiple_devices(): void
    {
        $this->register();
        $this->device['device_uuid'] = (string) Str::uuid();
        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'person',
            'password' => 'Password1',
            'device' => $this->device + ['name' => 'Second iPhone'],
        ])->assertOk()->assertJsonPath('token_type', 'Bearer');

        $this->assertNotEmpty($login->json('access_token'));
        $this->assertSame(2, Device::query()->count());
        $this->assertSame(2, RefreshToken::query()->count());
    }

    public function test_guest_and_password_confirmed_account_deletion(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest', ['device' => $this->device])
            ->assertCreated()
            ->assertJsonPath('user.is_guest', true);
        $this->asToken($guest->json('access_token'))->deleteJson('/api/v1/auth/account')->assertNoContent();

        $tokens = $this->register();
        $this->asToken($tokens['access_token'])->deleteJson('/api/v1/auth/account', [
            'password' => 'wrong',
        ])->assertUnprocessable();
        $this->asToken($tokens['access_token'])->deleteJson('/api/v1/auth/account', [
            'password' => 'Password1',
        ])->assertNoContent();
    }

    public function test_password_reset_and_email_verification(): void
    {
        $this->register();
        $user = User::query()->where('email', 'person@example.com')->firstOrFail();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $resetToken = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();
        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));

        $url = URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addMinutes(10),
            ['user' => $user->id, 'hash' => sha1($user->email)],
        );
        $this->getJson($url)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_forgot_password_is_enumeration_safe_and_reset_revokes_refresh_tokens(): void
    {
        $tokens = $this->register();
        $user = User::query()->where('email', 'person@example.com')->firstOrFail();
        $existing = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->json('message');
        $missing = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->json('message');
        $this->assertSame($existing, $missing);

        $resetToken = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'ChangedPassword1',
            'password_confirmation' => 'ChangedPassword1',
        ])->assertOk();
        $this->assertNotNull(RefreshToken::query()->firstOrFail()->revoked_at);
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertUnprocessable();
    }

    public function test_firebase_link_uses_verified_subject_and_enforces_uniqueness(): void
    {
        $this->app->instance(FirebaseTokenVerifier::class, new class implements FirebaseTokenVerifier
        {
            public function verify(string $idToken): FirebaseIdentity
            {
                return new FirebaseIdentity('verified-firebase-uid');
            }
        });

        $first = $this->register();
        $this->asToken($first['access_token'])->postJson('/api/v1/auth/firebase/link', [
            'id_token' => str_repeat('x', 20),
        ])->assertOk();

        $this->device['device_uuid'] = (string) Str::uuid();
        $second = $this->register('second@example.com', 'second');
        $this->asToken($second['access_token'])->postJson('/api/v1/auth/firebase/link', [
            'id_token' => str_repeat('y', 20),
        ])->assertUnprocessable()->assertJsonValidationErrors('id_token');
    }

    public function test_invalid_firebase_token_is_a_safe_validation_error(): void
    {
        $this->app->instance(FirebaseTokenVerifier::class, new class implements FirebaseTokenVerifier
        {
            public function verify(string $idToken): FirebaseIdentity
            {
                throw new InvalidFirebaseToken('Invalid signature');
            }
        });

        $tokens = $this->register();
        $this->asToken($tokens['access_token'])->postJson('/api/v1/auth/firebase/link', [
            'id_token' => str_repeat('x', 20),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('id_token')
            ->assertJsonMissing(['message' => 'Invalid signature']);
    }

    public function test_login_endpoint_is_throttled(): void
    {
        User::factory()->create(['email' => 'rate@example.com', 'username' => 'rate']);
        $payload = [
            'login' => 'rate@example.com',
            'password' => 'wrong-password',
            'device' => $this->device,
        ];

        foreach (range(1, 10) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->postJson('/api/v1/auth/login', $payload)
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/v1/auth/login', $payload)
            ->assertTooManyRequests();
    }

    private function register(string $email = 'person@example.com', string $username = 'person'): array
    {
        return $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'username' => $username,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'device' => $this->device,
        ])->assertCreated()->json();
    }

    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
