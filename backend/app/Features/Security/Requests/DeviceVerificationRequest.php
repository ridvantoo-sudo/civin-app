<?php

namespace App\Features\Security\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Only attestation metadata is accepted — never anything that identifies
     * the human behind the device.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['nullable', 'string', 'max:32'],
            'model' => ['nullable', 'string', 'max:96'],
            'manufacturer' => ['nullable', 'string', 'max:96'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'build_number' => ['nullable', 'string', 'max:32'],
            'package_name' => ['nullable', 'string', 'max:128'],
            'build_signature' => ['nullable', 'string', 'max:191'],
            'cpu_cores' => ['nullable', 'integer', 'min:1', 'max:512'],
            'memory_mb' => ['nullable', 'integer', 'min:1', 'max:4194304'],
            'screen' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:32'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_emulator' => ['nullable', 'boolean'],
            'is_rooted' => ['nullable', 'boolean'],
            'is_jailbroken' => ['nullable', 'boolean'],
            'is_debug' => ['nullable', 'boolean'],
            'is_tampered' => ['nullable', 'boolean'],
            'is_vpn' => ['nullable', 'boolean'],
            'is_proxy' => ['nullable', 'boolean'],
            'is_tor' => ['nullable', 'boolean'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'region' => ['nullable', 'string', 'max:96'],
            'latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ];
    }
}
