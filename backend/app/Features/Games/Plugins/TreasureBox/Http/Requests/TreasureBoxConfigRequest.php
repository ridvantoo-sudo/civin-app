<?php

namespace App\Features\Games\Plugins\TreasureBox\Http\Requests;

use App\Features\Games\Models\TreasureBox;
use Illuminate\Foundation\Http\FormRequest;

final class TreasureBoxConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', TreasureBox::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
