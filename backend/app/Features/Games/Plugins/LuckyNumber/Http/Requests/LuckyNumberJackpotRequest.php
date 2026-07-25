<?php

namespace App\Features\Games\Plugins\LuckyNumber\Http\Requests;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use Illuminate\Foundation\Http\FormRequest;

final class LuckyNumberJackpotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewJackpot', LuckyNumberTicket::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
