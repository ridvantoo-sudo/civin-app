<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Requests;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitRpsMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = RpsMatch::query()->find($this->input('match_id'));
        if ($match === null) {
            return $this->user()?->can('create', RpsMatch::class) ?? false;
        }

        return $this->user()?->can('move', $match) ?? false;
    }

    /**
     * The move itself is only checked for shape here; whether it is legal is
     * decided server side against the match's frozen rule set.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'match_id' => ['required', 'uuid'],
            'round_id' => ['sometimes', 'nullable', 'uuid'],
            'move' => ['required', 'string', 'max:32'],
            'client_response_ms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:600000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'result' => ['prohibited'],
            'winner_id' => ['prohibited'],
            'is_winner' => ['prohibited'],
            'opponent_move' => ['prohibited'],
            'score' => ['prohibited'],
        ];
    }
}
