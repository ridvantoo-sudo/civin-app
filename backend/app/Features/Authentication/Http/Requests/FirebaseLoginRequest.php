<?php

namespace App\Features\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FirebaseLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $device = is_array($this->input('device')) ? $this->input('device') : [];

        $this->merge([
            'device' => array_merge([
                'device_uuid' => $this->input('device_id'),
                'name' => $this->input('device_name'),
                'platform' => $this->input('platform'),
                'push_token' => $this->input('token'),
            ], $device),
        ]);
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'min:20'],
            'device.device_uuid' => ['required', 'uuid'],
            'device.platform' => ['required', 'in:ios,android,web'],
            'device.name' => ['required', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:4096'],
            'device.app_version' => ['nullable', 'string', 'max:40'],
            'device.os_version' => ['nullable', 'string', 'max:40'],
        ];
    }
}
