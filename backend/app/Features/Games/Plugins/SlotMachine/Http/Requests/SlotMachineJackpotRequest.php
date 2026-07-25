<?php

namespace App\Features\Games\Plugins\SlotMachine\Http\Requests;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use Illuminate\Foundation\Http\FormRequest;

final class SlotMachineJackpotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewJackpot', SlotMachineSpin::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
