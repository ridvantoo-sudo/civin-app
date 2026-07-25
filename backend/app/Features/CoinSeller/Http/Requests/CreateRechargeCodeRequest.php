<?php

namespace App\Features\CoinSeller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateRechargeCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'coins' => ['required', 'integer', 'min:1', 'max:100000000'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ];
    }
}
