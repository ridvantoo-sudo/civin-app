<?php

namespace App\Features\Games\Plugins\RedEnvelope\Http\Requests;

use App\Features\Games\Models\RedEnvelope;
use Illuminate\Foundation\Http\FormRequest;

final class ClaimRedEnvelopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('claim', RedEnvelope::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'envelope_id' => ['required', 'uuid'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // The share is computed server side and can never be requested.
            'reward_amount' => ['prohibited'],
            'coins' => ['prohibited'],
            'reward_type' => ['prohibited'],
        ];
    }
}
