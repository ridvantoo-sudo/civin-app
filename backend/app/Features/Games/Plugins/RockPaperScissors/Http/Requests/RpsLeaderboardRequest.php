<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Requests;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RpsLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', RpsMatch::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly', 'all_time', 'all', 'tournament'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'mode' => ['sometimes', 'nullable', 'string', Rule::in(RpsConfig::MODES)],
            'room_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
