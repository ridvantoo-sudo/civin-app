<?php

namespace App\Features\Games\Plugins\LuckyNumber\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LuckyNumberLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:daily,weekly,monthly,all,jackpot,highest_win,most_tickets,win_rate'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
