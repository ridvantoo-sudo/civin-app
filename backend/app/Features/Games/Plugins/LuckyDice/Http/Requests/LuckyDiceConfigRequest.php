<?php

namespace App\Features\Games\Plugins\LuckyDice\Http\Requests;

use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use Illuminate\Foundation\Http\FormRequest;

final class LuckyDiceConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', LuckyDiceBet::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
