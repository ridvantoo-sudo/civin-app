<?php

namespace App\Features\Growth\Http\Requests;

use App\Features\Growth\Support\PromoRewardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:32', 'alpha_dash'],
            'reward_type' => ['required', 'string', Rule::in(PromoRewardType::all())],
            'reward_value' => ['required', 'integer', 'min:1'],
            'max_redemptions' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_user_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
