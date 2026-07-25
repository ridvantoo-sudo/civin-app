<?php

namespace App\Features\Games\Plugins\Quiz\Repositories\Eloquent;

use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestionOption;
use App\Features\Games\Plugins\Quiz\Repositories\Contracts\QuizQuestionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentQuizQuestionRepository implements QuizQuestionRepository
{
    public function findPlayable(string $questionId): ?QuizQuestion
    {
        return QuizQuestion::query()
            ->with('optionRows')
            ->whereKey($questionId)
            ->where('status', QuizQuestion::STATUS_ACTIVE)
            ->where('approval_status', QuizQuestion::APPROVAL_APPROVED)
            ->first();
    }

    public function pickQuestions(
        int $count,
        ?string $categoryId = null,
        ?string $difficulty = null,
        ?string $language = null,
        ?array $types = null,
    ): Collection {
        $query = QuizQuestion::query()
            ->where('status', QuizQuestion::STATUS_ACTIVE)
            ->where('approval_status', QuizQuestion::APPROVAL_APPROVED);

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if ($difficulty !== null && $difficulty !== 'custom') {
            $query->where('difficulty', $difficulty);
        }
        if ($language !== null) {
            $query->where('language', $language);
        }
        if ($types !== null && $types !== []) {
            $query->whereIn('type', $types);
        }

        return $query->inRandomOrder()->limit($count)->get();
    }

    public function paginate(?string $categoryId = null, ?string $difficulty = null, ?string $language = null, int $perPage = 30): LengthAwarePaginator
    {
        return QuizQuestion::query()
            ->where('status', QuizQuestion::STATUS_ACTIVE)
            ->where('approval_status', QuizQuestion::APPROVAL_APPROVED)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($difficulty, fn ($q) => $q->where('difficulty', $difficulty))
            ->when($language, fn ($q) => $q->where('language', $language))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $attributes): QuizQuestion
    {
        return DB::transaction(function () use ($attributes): QuizQuestion {
            $options = $attributes['option_rows'] ?? null;
            unset($attributes['option_rows']);
            $question = QuizQuestion::query()->create($attributes);
            if (is_array($options)) {
                foreach ($options as $row) {
                    QuizQuestionOption::query()->create([
                        'question_id' => $question->getKey(),
                        'option_index' => (int) $row['option_index'],
                        'label' => (string) $row['label'],
                        'media_url' => $row['media_url'] ?? null,
                        'is_correct' => (bool) ($row['is_correct'] ?? false),
                        'translations' => $row['translations'] ?? null,
                        'metadata' => $row['metadata'] ?? null,
                    ]);
                }
            }

            return $question->load('optionRows');
        });
    }

    public function bulkImport(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $this->create($row);
            $count++;
        }

        return $count;
    }
}
