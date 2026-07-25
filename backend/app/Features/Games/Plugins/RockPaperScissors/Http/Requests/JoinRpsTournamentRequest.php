<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Requests;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Foundation\Http\FormRequest;

final class JoinRpsTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('joinTournament', RpsMatch::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'tournament_id' => ['required', 'uuid'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'status' => ['prohibited'],
            'reward_pool' => ['prohibited'],
            'participant_ids' => ['prohibited'],
        ];
    }
}
