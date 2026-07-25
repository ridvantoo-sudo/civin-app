<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Http\Requests;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use Illuminate\Foundation\Http\FormRequest;

final class RpsStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewStatistics', RpsMatch::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
