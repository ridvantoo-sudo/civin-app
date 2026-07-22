<?php

namespace App\Features\Agency\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApplyAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
