<?php

namespace App\Features\CoinSeller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RedeemRechargeCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:8', 'max:32'],
        ];
    }
}
