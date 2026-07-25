<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Requests;

use App\Features\Games\Models\TreasureBox;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTreasureBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TreasureBox::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'type' => ['required', 'string', Rule::in(TreasureBox::TYPES)],
            'reward_type' => ['required', 'string', Rule::in(TreasureBox::REWARD_TYPES)],
            'distribution_type' => ['sometimes', 'string', Rule::in(TreasureBox::DISTRIBUTION_TYPES)],
            'visibility' => ['required', 'string', Rule::in(TreasureBox::VISIBILITIES)],
            'eligibility_scope' => ['required', 'string', Rule::in(TreasureBox::ELIGIBILITY_SCOPES)],
            'total_amount' => ['required', 'integer', 'min:1'],
            'winner_count' => ['required', 'integer', 'min:1'],
            'countdown_seconds' => ['required', 'integer', 'min:1'],
            'claim_duration_seconds' => ['required', 'integer', 'min:1'],
            'auto_start' => ['sometimes', 'boolean'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'room_type' => ['sometimes', 'nullable', 'string', Rule::in(TreasureBox::ROOM_TYPES)],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
            'whitelist_user_ids' => ['sometimes', 'nullable', 'array', 'max:200'],
            'whitelist_user_ids.*' => ['uuid'],
            'min_watch_time_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_user_level' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_gift_value' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'message' => ['sometimes', 'nullable', 'string', 'max:200'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'remaining_amount' => ['prohibited'],
            'participant_count' => ['prohibited'],
            'status' => ['prohibited'],
            'expires_at' => ['prohibited'],
            'draw_at' => ['prohibited'],
            'opens_at' => ['prohibited'],
            'countdown_started_at' => ['prohibited'],
            'creator_id' => ['prohibited'],
        ];
    }
}
