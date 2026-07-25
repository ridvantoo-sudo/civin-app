<?php

namespace App\Features\Games\Plugins\LuckyWheel\Http\Requests;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use Illuminate\Foundation\Http\FormRequest;

final class SpinLuckyWheelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spin', LuckyWheelSpin::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'client_session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'cost_coins' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Client must never supply winning outcome.
            'sector_id' => ['prohibited'],
            'sector_index' => ['prohibited'],
            'reward_type' => ['prohibited'],
            'reward_amount' => ['prohibited'],
            'result' => ['prohibited'],
            'outcome' => ['prohibited'],
        ];
    }
}
