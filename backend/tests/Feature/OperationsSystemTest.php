<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OperationsSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_reports_database_and_cache_health(): void
    {
        $this->getJson('/ops/ready')
            ->assertOk()
            ->assertJson([
                'status' => 'ready',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                ],
            ]);
    }

    public function test_metrics_require_a_configured_bearer_token(): void
    {
        config()->set('operations.metrics_token', 'metrics-secret');

        $this->get('/ops/metrics')->assertForbidden();

        $this->withToken('wrong')->get('/ops/metrics')->assertForbidden();

        $this->withToken('metrics-secret')
            ->get('/ops/metrics')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; version=0.0.4; charset=utf-8')
            ->assertSee('civin_up 1', escape: false)
            ->assertSee('civin_database_up 1', escape: false);
    }

    public function test_storage_check_verifies_the_configured_disk(): void
    {
        Storage::fake('ops-test');
        config()->set('operations.storage_disk', 'ops-test');

        $this->artisan('ops:storage-check')
            ->expectsOutputToContain('Storage check passed')
            ->assertSuccessful();

        Storage::disk('ops-test')->assertDirectoryEmpty('ops/probes');
    }
}
