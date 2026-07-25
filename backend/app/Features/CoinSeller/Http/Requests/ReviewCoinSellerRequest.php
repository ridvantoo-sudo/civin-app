<?php

namespace App\Features\CoinSeller\Http\Requests;

use App\Features\CoinSeller\Models\CoinSeller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewCoinSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    CoinSeller::STATUS_ACTIVE,
                    CoinSeller::STATUS_REJECTED,
                    CoinSeller::STATUS_SUSPENDED,
                ]),
            ],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
