<?php

namespace App\Features\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ModerateVoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'surface' => ['sometimes', 'string', 'in:voice,live'],
            'audio_url' => ['nullable', 'url', 'max:2048'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'room_id' => ['nullable', 'uuid'],
            'subject_type' => ['nullable', 'string', 'max:80'],
            'subject_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
