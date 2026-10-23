<?php

namespace App\Features\DirectChat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
