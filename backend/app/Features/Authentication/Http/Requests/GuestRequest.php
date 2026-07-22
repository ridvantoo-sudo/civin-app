<?php

namespace App\Features\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device.device_uuid' => ['required', 'uuid'],
            'device.platform' => ['required', 'in:ios,android,web'],
            'device.name' => ['required', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:4096'],
            'device.app_version' => ['nullable', 'string', 'max:40'],
            'device.os_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
