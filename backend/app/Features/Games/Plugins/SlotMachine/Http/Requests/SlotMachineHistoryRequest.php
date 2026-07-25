<?php

namespace App\Features\Games\Plugins\SlotMachine\Http\Requests;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use Illuminate\Foundation\Http\FormRequest;

final class SlotMachineHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewHistory', SlotMachineSpin::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
