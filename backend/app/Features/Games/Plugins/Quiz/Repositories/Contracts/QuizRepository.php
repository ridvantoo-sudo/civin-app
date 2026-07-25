<?php

namespace App\Features\Games\Plugins\Quiz\Repositories\Contracts;

use App\Features\Games\Plugins\Quiz\Models\QuizAnswer;
use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface QuizRepository
{
    public function findDefaultConfig(): ?QuizConfig;

    public function findSession(string $sessionId): ?QuizSession;

    public function findSessionByIdempotency(User $user, string $key): ?QuizSession;

    public function findPlayerByIdempotency(User $user, string $key): ?QuizPlayer;

    public function findAnswerByIdempotency(User $user, string $key): ?QuizAnswer;

    /** @param array<string, mixed> $attributes */
    public function createSession(array $attributes): QuizSession;

    /** @param array<string, mixed> $attributes */
    public function createPlayer(array $attributes): QuizPlayer;

    /** @param array<string, mixed> $attributes */
    public function createAnswer(array $attributes): QuizAnswer;

    public function countCreatedToday(User $user, string $configId): int;

    public function history(User $user, int $perPage = 30): LengthAwarePaginator;

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $categoryId = null, ?string $roomId = null): Collection;

    /** @return array<string, mixed> */
    public function statistics(User $user, ?string $configId = null): array;
}
