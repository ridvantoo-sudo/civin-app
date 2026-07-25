<?php

namespace App\Features\Games\Plugins\Ludo\Http\Requests;

use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use Illuminate\Foundation\Http\FormRequest;

final class CreateLudoMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LudoMatch::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'mode' => ['sometimes', 'string'],
            'matchmaking' => ['sometimes', 'string'],
            'entry_fee_coins' => ['sometimes', 'integer', 'min:0'],
            'max_players' => ['sometimes', 'integer', 'between:2,4'],
            'room_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'room_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'tournament_id' => ['sometimes', 'nullable', 'uuid'],
            'metadata' => ['sometimes', 'array'],
            'winner_id' => ['prohibited'],
            'dice_value' => ['prohibited'],
            'board_state' => ['prohibited'],
        ];
    }
}
