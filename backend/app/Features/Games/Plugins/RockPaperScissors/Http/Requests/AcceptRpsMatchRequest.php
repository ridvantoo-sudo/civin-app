<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Requests;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Foundation\Http\FormRequest;

final class AcceptRpsMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = RpsMatch::query()->find($this->input('match_id'));
        if ($match === null) {
            // Let validation surface the missing match instead of a 403.
            return $this->user()?->can('create', RpsMatch::class) ?? false;
        }

        return $this->user()?->can('accept', $match) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'match_id' => ['required', 'uuid'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'status' => ['prohibited'],
            'entry_fee_coins' => ['prohibited'],
            'pot_coins' => ['prohibited'],
        ];
    }
}
