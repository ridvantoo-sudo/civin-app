<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Requests;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Foundation\Http\FormRequest;

final class RematchRpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $match = RpsMatch::query()->find($this->input('match_id'));
        if ($match === null) {
            return $this->user()?->can('create', RpsMatch::class) ?? false;
        }

        return $this->user()?->can('rematch', $match) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'match_id' => ['required', 'uuid'],
            'entry_fee_coins' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'status' => ['prohibited'],
            'opponent_id' => ['prohibited'],
        ];
    }
}
