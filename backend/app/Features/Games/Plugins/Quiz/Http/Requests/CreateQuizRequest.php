<?php

namespace App\Features\Games\Plugins\Quiz\Http\Requests;

use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QuizSession::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'mode' => ['sometimes', 'string', Rule::in(QuizConfig::MODES)],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'difficulty' => ['sometimes', 'string', Rule::in(QuizConfig::DIFFICULTIES)],
            'question_ids' => ['sometimes', 'nullable', 'array', 'max:50'],
            'question_ids.*' => ['uuid'],
            'question_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'time_per_question' => ['sometimes', 'integer', 'min:1', 'max:300'],
            'reward_type' => ['sometimes', 'string', Rule::in(QuizConfig::REWARD_TYPES)],
            'reward_pool' => ['sometimes', 'integer', 'min:0'],
            'bet_coins' => ['sometimes', 'integer', 'min:0'],
            'winner_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'visibility' => ['sometimes', 'string', Rule::in(QuizConfig::VISIBILITIES)],
            'eligibility' => ['sometimes', 'string', Rule::in(QuizConfig::ELIGIBILITIES)],
            'whitelist_user_ids' => ['sometimes', 'nullable', 'array', 'max:200'],
            'whitelist_user_ids.*' => ['uuid'],
            'room_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'room_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'tournament_id' => ['sometimes', 'nullable', 'uuid'],
            'language' => ['sometimes', 'string', 'max:16'],
            'team_settings' => ['sometimes', 'nullable', 'array'],
            'client_session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'status' => ['prohibited'],
            'host_id' => ['prohibited'],
            'player_count' => ['prohibited'],
            'current_question_id' => ['prohibited'],
            'scoring_rules' => ['prohibited'],
        ];
    }
}
