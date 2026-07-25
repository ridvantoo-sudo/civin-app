<?php

namespace App\Features\Games\Plugins\Ludo\Http\Requests;

use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use Illuminate\Foundation\Http\FormRequest;

final class LudoConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewConfig', LudoMatch::class) ?? false;
    }

    public function rules(): array
    {
        return [

        ];
    }
}
