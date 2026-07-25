<?php

namespace App\Features\Games\Plugins\LuckyNumber\Http\Requests;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuickPickLuckyNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('play', LuckyNumberTicket::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_pick' => true,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'game_mode' => ['required', 'string', Rule::in(LuckyNumberConfig::MODES)],
            'bet_amount' => ['required', 'integer', 'min:1'],
            'picked_numbers' => ['sometimes', 'array', 'min:1', 'max:20'],
            'picked_numbers.*' => ['integer', 'min:1'],
            'auto_pick' => ['sometimes', 'boolean'],
            'repeat_ticket' => ['sometimes', 'boolean'],
            'client_session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'winning_numbers' => ['prohibited'],
            'outcome' => ['prohibited'],
            'reward_coins' => ['prohibited'],
            'reward_diamonds' => ['prohibited'],
            'is_win' => ['prohibited'],
            'is_jackpot' => ['prohibited'],
            'server_seed' => ['prohibited'],
            'drawn' => ['prohibited'],
            'result' => ['prohibited'],
            'multiplier' => ['prohibited'],
        ];
    }
}
