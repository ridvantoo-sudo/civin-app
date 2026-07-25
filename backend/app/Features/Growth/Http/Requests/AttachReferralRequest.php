<?php

namespace App\Features\Growth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AttachReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:4', 'max:32'],
        ];
    }
}
