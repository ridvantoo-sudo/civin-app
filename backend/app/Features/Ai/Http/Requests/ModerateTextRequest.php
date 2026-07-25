<?php

namespace App\Features\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ModerateTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000'],
            'surface' => ['sometimes', 'string', 'in:chat,post,live,comment'],
            'subject_type' => ['nullable', 'string', 'max:80'],
            'subject_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
