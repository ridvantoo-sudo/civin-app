<?php

namespace App\Features\Games\Plugins\Ludo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LudoLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:daily,weekly,monthly,all_time'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'mode' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
