<?php

namespace App\Features\Settings\Http\Requests;

use App\Support\SupportedLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array:theme,push_enabled,locale,notification_preferences,location_sharing_enabled,show_distance,nearby_visibility',
            ],
            'settings.theme' => ['sometimes', 'in:light,dark,system'],
            'settings.push_enabled' => ['sometimes', 'boolean'],
            'settings.locale' => ['sometimes', 'string', Rule::in(SupportedLocales::CODES)],
            'settings.notification_preferences' => ['sometimes', 'array'],
            'settings.notification_preferences.*' => ['boolean'],
            'settings.location_sharing_enabled' => ['sometimes', 'boolean'],
            'settings.show_distance' => ['sometimes', 'boolean'],
            'settings.nearby_visibility' => ['sometimes', 'string', 'in:everyone,followers,nobody'],
        ];
    }
}
