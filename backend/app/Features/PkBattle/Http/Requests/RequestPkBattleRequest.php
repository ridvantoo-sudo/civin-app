<?php

namespace App\Features\PkBattle\Http\Requests;

use App\Features\PkBattle\Models\PkBattle;
use Illuminate\Foundation\Http\FormRequest;

final class RequestPkBattleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'opponent_room_id' => ['required', 'uuid', 'exists:live_rooms,id'],
            'duration_seconds' => [
                'sometimes',
                'integer',
                'min:30',
                'max:1800',
            ],
        ];
    }

    public function durationSeconds(): int
    {
        return (int) ($this->validated('duration_seconds') ?? PkBattle::DEFAULT_DURATION_SECONDS);
    }
}
