<?php

namespace App\Features\Games\Plugins\Quiz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionOption extends Model
{
    use HasUuids;

    protected $fillable = [
        'question_id', 'option_index', 'label', 'media_url', 'is_correct', 'translations', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'translations' => 'array',
            'metadata' => 'array',
            'option_index' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
