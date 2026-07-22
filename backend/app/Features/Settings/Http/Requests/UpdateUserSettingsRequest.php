<?php

namespace App\Features\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:theme,push_enabled,locale,notification_preferences'],
            'settings.theme' => ['sometimes', 'in:light,dark,system'],
            'settings.push_enabled' => ['sometimes', 'boolean'],
            'settings.locale' => ['sometimes', 'string', 'max:10'],
            'settings.notification_preferences' => ['sometimes', 'array'],
            'settings.notification_preferences.*' => ['boolean'],
        ];
    }
}
