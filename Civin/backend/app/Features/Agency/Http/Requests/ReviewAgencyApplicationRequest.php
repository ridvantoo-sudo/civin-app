<?php

namespace App\Features\Agency\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewAgencyApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ];
    }
}
