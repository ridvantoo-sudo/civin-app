<?php

namespace App\Features\Games\Plugins\SlotMachine\Http\Requests;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlaySlotMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('play', SlotMachineSpin::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'game_mode' => ['required', 'string', Rule::in(SlotMachineConfig::MODES)],
            'bet_amount' => ['required', 'integer', 'min:1'],
            'spin_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'auto_spin' => ['sometimes', 'boolean'],
            'client_session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Client must never supply winning outcomes.
            'grid' => ['prohibited'],
            'reels' => ['prohibited'],
            'outcome' => ['prohibited'],
            'result' => ['prohibited'],
            'reward_coins' => ['prohibited'],
            'reward_diamonds' => ['prohibited'],
            'is_win' => ['prohibited'],
            'is_jackpot' => ['prohibited'],
            'is_bonus' => ['prohibited'],
            'rolls' => ['prohibited'],
            'server_seed' => ['prohibited'],
        ];
    }
}
