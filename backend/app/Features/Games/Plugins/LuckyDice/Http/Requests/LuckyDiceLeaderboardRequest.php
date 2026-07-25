<?php

namespace App\Features\Games\Plugins\LuckyDice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LuckyDiceLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly', 'all'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'type' => ['sometimes', 'string', Rule::in(['winners', 'profit', 'plays'])],
        ];
    }
}
