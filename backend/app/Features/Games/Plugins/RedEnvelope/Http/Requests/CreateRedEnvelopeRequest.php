<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Requests;

use App\Features\Games\Models\RedEnvelope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRedEnvelopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RedEnvelope::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'reward_type' => ['required', 'string', Rule::in(RedEnvelope::REWARD_TYPES)],
            'distribution_type' => ['required', 'string', Rule::in(RedEnvelope::DISTRIBUTION_TYPES)],
            'visibility' => ['required', 'string', Rule::in(RedEnvelope::VISIBILITIES)],
            'claim_scope' => ['required', 'string', Rule::in(RedEnvelope::CLAIM_SCOPES)],
            'total_amount' => ['required', 'integer', 'min:1'],
            'winner_count' => ['required', 'integer', 'min:1'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
            'room_type' => ['sometimes', 'nullable', 'string', Rule::in(RedEnvelope::ROOM_TYPES)],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
            'whitelist_user_ids' => ['sometimes', 'nullable', 'array', 'max:200'],
            'whitelist_user_ids.*' => ['uuid'],
            'message' => ['sometimes', 'nullable', 'string', 'max:200'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Server-owned state: a client may never seed allocations or balances.
            'remaining_amount' => ['prohibited'],
            'remaining_count' => ['prohibited'],
            'status' => ['prohibited'],
            'expires_at' => ['prohibited'],
            'creator_id' => ['prohibited'],
            'sender_id' => ['prohibited'],
            'total_coins' => ['prohibited'],
            'remaining_coins' => ['prohibited'],
        ];
    }
}
