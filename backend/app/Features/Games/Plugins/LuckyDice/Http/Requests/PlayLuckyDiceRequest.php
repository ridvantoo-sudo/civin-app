<?php

namespace App\Features\Games\Plugins\LuckyDice\Http\Requests;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlayLuckyDiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('play', LuckyDiceBet::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'bet_mode' => ['required', 'string', Rule::in(LuckyDiceBet::MODES)],
            'prediction' => ['required', 'string', 'max:32'],
            'bet_amount' => ['required', 'integer', 'min:1'],
            'client_session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Client must never supply dice/outcome fields.
            'dice_1' => ['prohibited'],
            'dice_2' => ['prohibited'],
            'dice_3' => ['prohibited'],
            'result' => ['prohibited'],
            'reward_type' => ['prohibited'],
            'reward_amount' => ['prohibited'],
            'outcome' => ['prohibited'],
            'total' => ['prohibited'],
        ];
    }
}
