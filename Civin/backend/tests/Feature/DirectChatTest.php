<?php

namespace Tests\Feature;

use App\Features\Blocking\Models\Block;
use App\Features\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DirectChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_exchange_text_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);
        $conversationId = $this->postJson("/api/v1/users/{$bob->id}/conversations")
            ->assertOk()
            ->json('data.id');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Hello',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Hello')
            ->assertJsonPath('data.sender_id', $alice->id);

        Sanctum::actingAs($bob);
        $this->getJson("/api/v1/conversations/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Hello');

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversationId);
    }

    public function test_blocked_users_cannot_open_a_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Block::query()->create([
            'blocker_id' => $alice->id,
            'blocked_id' => $bob->id,
        ]);

        Sanctum::actingAs($bob);
        $this->postJson("/api/v1/users/{$alice->id}/conversations")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }
}
