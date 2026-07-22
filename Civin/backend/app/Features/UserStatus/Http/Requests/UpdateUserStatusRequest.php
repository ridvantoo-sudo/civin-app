<?php

namespace App\Features\UserStatus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_online' => ['required_without:is_live', 'boolean'],
            'is_live' => ['required_without:is_online', 'boolean'],
        ];
    }
}
