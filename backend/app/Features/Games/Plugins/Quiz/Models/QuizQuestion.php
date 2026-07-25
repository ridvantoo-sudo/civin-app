<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plugin question model — shares the platform quiz_questions table.
 * Keeps compatibility with Engines\QuizEngine via shared columns.
 */
class QuizQuestion extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_REJECTED = 'rejected';

    protected $table = 'quiz_questions';

    protected $fillable = [
        'category_id', 'config_id', 'question', 'type', 'language', 'version',
        'parent_question_id', 'prompt', 'media_url', 'media_type', 'translations',
        'tags', 'options', 'correct_index', 'difficulty', 'reward_coins', 'bet_coins',
        'status', 'approval_status', 'created_by', 'approved_by', 'approved_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'translations' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'correct_index' => 'integer',
            'reward_coins' => 'integer',
            'bet_coins' => 'integer',
            'version' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuizCategory::class, 'category_id');
    }

    public function optionRows(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class, 'question_id')->orderBy('option_index');
    }

    public function isPlayable(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->approval_status === self::APPROVAL_APPROVED;
    }

    /** Public payload without revealing the correct answer. */
    public function publicPayload(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->prompt ?: $this->question,
            'type' => $this->type ?? 'multiple_choice',
            'language' => $this->language ?? 'en',
            'difficulty' => $this->difficulty,
            'category_id' => $this->category_id,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'options' => $this->options ?? [],
            'translations' => $this->translations,
            'time_hint' => null,
        ];
    }
}
