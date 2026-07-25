<?php

namespace Tests\Feature\Security;

use App\Features\Security\Models\SecurityDevice;
use App\Features\Security\Models\SecurityEvent;
use App\Features\Security\Services\SecurityCollector;
use App\Features\Security\Support\Fingerprint;
use App\Features\Users\Models\User;

final class SecurityPrivacyTest extends SecurityTestCase
{
    public function test_raw_ip_addresses_are_never_persisted(): void
    {
        $user = User::factory()->create();

        app(SecurityCollector::class)->collectFromDomainEvent(
            'App\\Features\\Wallet\\Events\\TransactionCompleted',
            [[
                'user_id' => (string) $user->id,
                'ip_address' => '203.0.113.44',
                'amount' => 10,
            ]],
        );

        $event = SecurityEvent::query()->firstOrFail();

        $this->assertSame(Fingerprint::ip('203.0.113.44'), $event->ip_hash);
        $this->assertStringNotContainsString('203.0.113.44', json_encode($event->getAttributes()));
    }

    public function test_raw_device_identifiers_are_never_persisted(): void
    {
        $user = User::factory()->create();

        app(SecurityCollector::class)->collectFromDomainEvent(
            'App\\Features\\Games\\Events\\GamePlayed',
            [[
                'user_id' => (string) $user->id,
                'device_id' => 'IMEI-998877',
            ]],
        );

        $event = SecurityEvent::query()->firstOrFail();

        $this->assertSame(Fingerprint::device('IMEI-998877'), $event->device_hash);
        $this->assertStringNotContainsString('IMEI-998877', json_encode($event->getAttributes()));
    }

    public function test_credentials_and_contact_details_are_redacted(): void
    {
        $user = User::factory()->create();

        app(SecurityCollector::class)->collectFromDomainEvent(
            'App\\Features\\Authentication\\Events\\LoginSucceeded',
            [[
                'user_id' => (string) $user->id,
                'email' => 'player@example.com',
                'phone' => '+15551212',
                'password' => 'hunter2',
                'access_token' => 'tok_abc',
                'firebase_uid' => 'fb_123',
                'latitude' => 41.0,
                'longitude' => 29.0,
            ]],
        );

        $payload = SecurityEvent::query()->firstOrFail()->payload;

        foreach (['email', 'phone', 'password', 'access_token', 'firebase_uid', 'latitude', 'longitude'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }

    public function test_fingerprints_are_salted_so_they_are_not_rainbow_tableable(): void
    {
        config(['security_platform.privacy.hash_salt' => 'salt-a']);
        $a = Fingerprint::ip('203.0.113.1');

        config(['security_platform.privacy.hash_salt' => 'salt-b']);
        $b = Fingerprint::ip('203.0.113.1');

        $this->assertNotSame($a, $b);
    }

    public function test_device_verify_does_not_accept_personal_information(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/security/device/verify', [
            'device_id' => 'device-1',
            'email' => 'player@example.com',
            'phone' => '+15551212',
            'password' => 'hunter2',
        ])
            ->assertOk();

        $device = SecurityDevice::query()->firstOrFail();
        $encoded = json_encode($device->getAttributes());

        $this->assertStringNotContainsString('player@example.com', $encoded);
        $this->assertStringNotContainsString('+15551212', $encoded);
        $this->assertStringNotContainsString('hunter2', $encoded);
    }
}
