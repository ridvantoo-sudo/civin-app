<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Requests;

use App\Features\Games\Models\TreasureBox;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TreasureBoxHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewHistory', TreasureBox::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['created', 'joined', 'won', 'all'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
