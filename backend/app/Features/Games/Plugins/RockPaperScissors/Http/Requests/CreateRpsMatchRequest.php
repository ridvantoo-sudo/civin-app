<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Requests;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRpsMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RpsMatch::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'mode' => ['sometimes', 'string', Rule::in(RpsConfig::MODES)],
            'matchmaking' => ['sometimes', 'string', Rule::in(RpsConfig::MATCHMAKING)],
            'opponent_id' => ['sometimes', 'nullable', 'uuid'],
            'entry_fee_coins' => ['sometimes', 'integer', 'min:0'],
            'reward_type' => ['sometimes', 'string', Rule::in(RpsConfig::REWARD_TYPES)],
            'visibility' => ['sometimes', 'string', Rule::in(RpsConfig::VISIBILITIES)],
            'eligibility' => ['sometimes', 'string', Rule::in(RpsConfig::ELIGIBILITIES)],
            'whitelist_user_ids' => ['sometimes', 'nullable', 'array', 'max:200'],
            'whitelist_user_ids.*' => ['uuid'],
            'round_timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:300'],
            'room_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'room_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'tournament_id' => ['sometimes', 'nullable', 'uuid'],
            'client_match_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Outcome fields are server-owned and must never arrive from a client.
            'status' => ['prohibited'],
            'result' => ['prohibited'],
            'winner_id' => ['prohibited'],
            'challenger_id' => ['prohibited'],
            'challenger_score' => ['prohibited'],
            'opponent_score' => ['prohibited'],
            'pot_coins' => ['prohibited'],
            'move_rules' => ['prohibited'],
        ];
    }
}
