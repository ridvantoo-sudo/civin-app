<?php

namespace App\Features\Games\Plugins\LuckyWheel\Http\Requests;

use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use Illuminate\Foundation\Http\FormRequest;

final class LuckyWheelHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewHistory', LuckyWheelSpin::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
