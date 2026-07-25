<?php

namespace Tests\Feature;

use App\Features\Games\Core\GameManager;
use App\Features\Games\Core\GameRegistry;
use App\Features\Games\Models\Game;
use App\Features\Games\Models\GamePlugin as GamePluginModel;
use App\Features\Users\Models\User;
use Database\Seeders\GamePlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GamePluginPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(GamePlatformSeeder::class);
    }

    public function test_registry_contains_all_ten_plugins(): void
    {
        $registry = app(GameRegistry::class);

        $this->assertCount(10, $registry->slugs());
        foreach (Game::slugs() as $slug) {
            $this->assertTrue($registry->has($slug), "Missing plugin: {$slug}");
        }
    }

    public function test_game_manager_resolves_lucky_wheel_without_engine_registry(): void
    {
        $user = User::factory()->withCoins(1000)->create();
        $game = Game::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->firstOrFail();
        $manager = app(GameManager::class);

        $outcome = $manager->resolve($user, $game, 100, []);

        $this->assertSame(100, $outcome->betCoins);
        $this->assertGreaterThanOrEqual(0, $outcome->rewardCoins);
        $this->assertArrayHasKey('segment_index', $outcome->outcome);
    }

    public function test_plugin_records_are_seeded_and_can_be_disabled(): void
    {
        $plugin = GamePluginModel::query()->where('slug', Game::SLUG_LUCKY_WHEEL)->firstOrFail();
        $this->assertTrue($plugin->enabled);

        $plugin->forceFill(['enabled' => false])->save();

        $this->assertDatabaseHas('game_plugins', [
            'slug' => Game::SLUG_LUCKY_WHEEL,
            'enabled' => false,
        ]);
    }

    public function test_wallet_verification_still_blocks_overbet(): void
    {
        $user = User::factory()->withCoins(5)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/games/play', [
            'game_slug' => Game::SLUG_SLOT_MACHINE,
            'bet_coins' => 100,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['coins']);
    }
}
