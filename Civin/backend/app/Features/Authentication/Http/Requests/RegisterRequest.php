<?php

namespace App\Features\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'display_name' => ['nullable', 'string', 'max:100'],
            'device' => ['required', 'array'],
            'device.device_uuid' => ['required', 'uuid'],
            'device.platform' => ['required', 'in:ios,android,web'],
            'device.name' => ['required', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:4096'],
            'device.app_version' => ['nullable', 'string', 'max:40'],
            'device.os_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
