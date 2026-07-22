<?php

namespace App\Features\VoiceRoom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SeatActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'seat_index' => ['required', 'integer', 'min:0', 'max:19'],
            'muted' => ['sometimes', 'boolean'],
        ];
    }
}
