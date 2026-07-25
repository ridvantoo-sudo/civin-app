<?php

namespace App\Features\Growth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RedeemPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:32'],
        ];
    }
}
