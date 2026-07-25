<?php

namespace App\Features\Couple\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCoupleProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:150'],
            'cover_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
