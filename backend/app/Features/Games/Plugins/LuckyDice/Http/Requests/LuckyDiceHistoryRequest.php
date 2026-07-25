<?php

namespace App\Features\Games\Plugins\LuckyDice\Http\Requests;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Foundation\Http\FormRequest;

final class LuckyDiceHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewHistory', LuckyDiceBet::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
