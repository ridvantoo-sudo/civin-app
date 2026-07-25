<?php

namespace App\Features\Games\Plugins\Quiz\Repositories\Contracts;

use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface QuizQuestionRepository
{
    public function findPlayable(string $questionId): ?QuizQuestion;

    /**
     * @param  list<string>|null  $types
     * @return Collection<int, QuizQuestion>
     */
    public function pickQuestions(
        int $count,
        ?string $categoryId = null,
        ?string $difficulty = null,
        ?string $language = null,
        ?array $types = null,
    ): Collection;

    public function paginate(?string $categoryId = null, ?string $difficulty = null, ?string $language = null, int $perPage = 30): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): QuizQuestion;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return int number imported
     */
    public function bulkImport(array $rows): int;
}
