<?php

namespace Tests\Feature;

use App\Features\Users\Models\User;
use App\Support\SupportedLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_language_sets_app_locale_and_response_header(): void
    {
        $response = $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/settings');

        $response->assertOk();
        $response->assertHeader('Content-Language', 'ar');
        $this->assertSame('ar', app()->getLocale());
    }

    public function test_kurdish_badini_locale_is_supported(): void
    {
        $response = $this->withHeader('Accept-Language', 'kmr')
            ->getJson('/api/v1/settings');

        $response->assertOk();
        $response->assertHeader('Content-Language', 'kmr');
        $this->assertSame('kmr', app()->getLocale());
        $this->assertSame(
            'زانیاریێن چوونەژوورێ نە دروستن.',
            __('messages.invalid_credentials'),
        );
        $this->assertTrue(SupportedLocales::isRtl('kmr'));
    }

    public function test_user_settings_locale_is_validated_against_supported_list(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user-settings', [
            'settings' => ['locale' => 'xx'],
        ])->assertStatus(422);

        $this->putJson('/api/v1/user-settings', [
            'settings' => ['locale' => 'ckb'],
        ])->assertOk();

        $this->assertContains('ckb', SupportedLocales::CODES);
    }

    public function test_authenticated_user_locale_setting_wins_over_header(): void
    {
        $user = User::factory()->create();
        $user->settings()->create([
            'key' => 'locale',
            'value' => 'fa',
        ]);
        Sanctum::actingAs($user);

        $response = $this->withHeader('Accept-Language', 'de')
            ->getJson('/api/v1/user-settings');

        $response->assertOk();
        $response->assertHeader('Content-Language', 'fa');
        $this->assertSame('fa', app()->getLocale());
    }
}
