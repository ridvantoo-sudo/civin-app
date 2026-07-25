<?php

namespace Tests\Feature;

use App\Features\Ai\Models\AiJob;
use App\Features\Ai\Models\AiModerationLog;
use App\Features\Ai\Support\AiJobStatus;
use App\Features\Ai\Support\AiJobType;
use App\Features\Ai\Support\AiModerationVerdict;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        config()->set('ai.driver', 'fake');
        config()->set('ai.fake.flag_words', ['spam', 'scam']);
        config()->set('ai.fake.block_words', ['terror', 'bomb']);
    }

    public function test_ai_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/ai/moderate/text', ['text' => 'hi'])->assertUnauthorized();
        $this->postJson('/api/v1/ai/translate', ['text' => 'hi', 'target_locale' => 'en'])->assertUnauthorized();
        $this->postJson('/api/v1/ai/recommend')->assertUnauthorized();
    }

    public function test_text_moderation_allows_flags_and_blocks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ai/moderate/text', [
            'text' => 'Hello friends',
            'surface' => 'chat',
        ])->assertCreated()
            ->assertJsonPath('data.type', AiJobType::MODERATE_TEXT)
            ->assertJsonPath('data.status', AiJobStatus::COMPLETED)
            ->assertJsonPath('data.result.verdict', AiModerationVerdict::ALLOW)
            ->assertJsonPath('data.moderation.verdict', AiModerationVerdict::ALLOW);

        $this->postJson('/api/v1/ai/moderate/text', [
            'text' => 'This is spam offer',
        ])->assertCreated()
            ->assertJsonPath('data.result.verdict', AiModerationVerdict::FLAG);

        $this->postJson('/api/v1/ai/moderate/text', [
            'text' => 'contains bomb threat',
        ])->assertCreated()
            ->assertJsonPath('data.result.verdict', AiModerationVerdict::BLOCK);

        $this->assertDatabaseCount('ai_moderation_logs', 3);
        $this->assertSame(3, AiJob::query()->where('type', AiJobType::MODERATE_TEXT)->count());
    }

    public function test_voice_moderation_and_job_lookup(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $jobId = $this->postJson('/api/v1/ai/moderate/voice', [
            'duration_ms' => 5000,
            'surface' => 'voice',
        ])->assertCreated()
            ->assertJsonPath('data.type', AiJobType::MODERATE_VOICE)
            ->assertJsonPath('data.result.verdict', AiModerationVerdict::ALLOW)
            ->json('data.id');

        $this->getJson("/api/v1/ai/jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.id', $jobId)
            ->assertJsonPath('data.status', AiJobStatus::COMPLETED);

        $this->assertDatabaseHas('ai_moderation_logs', [
            'job_id' => $jobId,
            'verdict' => AiModerationVerdict::ALLOW,
        ]);
    }

    public function test_translate_recommend_and_highlight_stubs(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/ai/translate', [
            'text' => 'سڵاو',
            'target_locale' => 'en',
            'source_locale' => 'kmr',
        ])->assertCreated()
            ->assertJsonPath('data.type', AiJobType::TRANSLATE)
            ->assertJsonPath('data.result.target_locale', 'en')
            ->assertJsonPath('data.result.translated_text', '[translated] سڵاو');

        $items = $this->postJson('/api/v1/ai/recommend', [
            'context' => 'discovery',
            'limit' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.type', AiJobType::RECOMMEND)
            ->json('data.result.items');

        $this->assertCount(3, $items);
        $this->assertArrayHasKey('score', $items[0]);

        $roomId = (string) Str::uuid();
        $suggestions = $this->postJson('/api/v1/ai/highlights/suggest', [
            'room_id' => $roomId,
        ])->assertCreated()
            ->assertJsonPath('data.type', AiJobType::HIGHLIGHT_SUGGEST)
            ->json('data.result.suggestions');

        $this->assertNotEmpty($suggestions);
        $this->assertArrayHasKey('start_ms', $suggestions[0]);
    }

    public function test_cannot_view_another_users_job(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($owner);
        $jobId = $this->postJson('/api/v1/ai/translate', [
            'text' => 'hello',
            'target_locale' => 'ar',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/ai/jobs/{$jobId}")->assertForbidden();
    }
}
