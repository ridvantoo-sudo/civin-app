<?php

namespace App\Features\Games\Plugins\SlotMachine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SlotMachineLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:daily,weekly,monthly,all,jackpot,highest_win,most_spins'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
