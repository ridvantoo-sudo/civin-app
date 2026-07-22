<?php

namespace App\Features\Profiles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'max:100'],
            'nickname' => ['sometimes', 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'cover_image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'birthday' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'in:male,female,non_binary,prefer_not_to_say'],
            'country_id' => ['sometimes', 'nullable', 'uuid', 'exists:countries,id,deleted_at,NULL'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
