<?php

namespace App\Features\Games\Plugins\Ludo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RollDiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'match_id' => ['required', 'uuid'],
            'dice_value' => ['prohibited'],
        ];
    }
}
