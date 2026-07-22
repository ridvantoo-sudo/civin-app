<?php

namespace Tests\Feature;

use App\Features\Countries\Models\Country;
use App\Features\Settings\Models\Setting;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_authenticated_module_endpoints(): void
    {
        $country = Country::factory()->create(['name' => 'Türkiye', 'active' => true]);
        Country::factory()->create(['active' => false]);
        Setting::factory()->create(['key' => 'public_key', 'value' => 'visible', 'is_public' => true]);
        Setting::factory()->create(['key' => 'secret_key', 'value' => 'hidden', 'is_public' => false]);

        $this->getJson('/api/v1/countries')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/countries/'.$country->id)->assertOk();
        $this->getJson('/api/v1/settings')->assertOk()
            ->assertJsonPath('data.public_key', 'visible')
            ->assertJsonMissing(['secret_key' => 'hidden']);

        [$user, $token, $deviceId] = $this->authenticatedUser();
        $this->asToken($token)->patchJson('/api/v1/user', ['username' => 'updated_name'])
            ->assertOk()->assertJsonPath('data.username', 'updated_name');
        $this->asToken($token)->patchJson('/api/v1/profile', [
            'display_name' => 'Updated Person',
            'country_id' => $country->id,
        ])->assertOk()->assertJsonPath('data.display_name', 'Updated Person');
        $this->asToken($token)->putJson('/api/v1/user-settings', [
            'settings' => ['theme' => 'dark', 'push_enabled' => true],
        ])->assertOk()->assertJsonPath('data.theme', 'dark');

        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Hello'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->asToken($token)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data');
        $this->asToken($token)->patchJson("/api/v1/notifications/$notificationId/read")->assertOk();
        $this->asToken($token)->deleteJson("/api/v1/notifications/$notificationId")->assertNoContent();

        $this->asToken($token)->getJson('/api/v1/devices')->assertOk()->assertJsonCount(1, 'data');
        $this->asToken($token)->deleteJson('/api/v1/devices/'.$deviceId)->assertNoContent();
        $this->asToken($token)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_user_cannot_delete_another_users_device(): void
    {
        [, $firstToken] = $this->authenticatedUser('first@example.com', 'first');
        [, , $secondDevice] = $this->authenticatedUser('second@example.com', 'second');

        $this->asToken($firstToken)->deleteJson('/api/v1/devices/'.$secondDevice)->assertForbidden();
    }

    public function test_notification_ownership_and_read_all_are_enforced(): void
    {
        [$first, $firstToken] = $this->authenticatedUser('first@example.com', 'first');
        [$second] = $this->authenticatedUser('second@example.com', 'second');
        $firstUnread = $this->insertNotification($first);
        $firstOther = $this->insertNotification($first);
        $secondUnread = $this->insertNotification($second);

        $this->asToken($firstToken)->patchJson("/api/v1/notifications/$secondUnread/read")->assertNotFound();
        $this->asToken($firstToken)->deleteJson("/api/v1/notifications/$secondUnread")->assertNotFound();
        $this->asToken($firstToken)->patchJson('/api/v1/notifications/read-all')->assertNoContent();

        $this->assertDatabaseMissing('notifications', ['id' => $firstUnread, 'read_at' => null]);
        $this->assertDatabaseMissing('notifications', ['id' => $firstOther, 'read_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $secondUnread, 'read_at' => null]);
    }

    public function test_inactive_countries_and_invalid_module_updates_are_rejected(): void
    {
        $inactive = Country::factory()->create(['active' => false]);
        $this->getJson('/api/v1/countries/'.$inactive->id)->assertNotFound();

        [, $token] = $this->authenticatedUser();
        $this->asToken($token)->putJson('/api/v1/user-settings', [
            'settings' => ['theme' => 'ultraviolet', 'unknown' => true],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['settings', 'settings.theme']);
        $this->asToken($token)->patchJson('/api/v1/profile', [
            'birth_date' => now()->addDay()->toDateString(),
            'avatar_url' => 'not-a-url',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date', 'avatar_url']);
    }

    private function authenticatedUser(
        string $email = 'person@example.com',
        string $username = 'person',
    ): array {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'username' => $username,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'device' => [
                'device_uuid' => (string) Str::uuid(),
                'platform' => 'android',
                'name' => 'Test Device',
            ],
        ])->assertCreated();

        return [
            User::query()->where('email', $email)->firstOrFail(),
            $response->json('access_token'),
            $response->json('device.id'),
        ];
    }

    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function insertNotification(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Owned'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
