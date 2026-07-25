<?php

namespace App\Features\Games\Plugins\Quiz\Actions;

use App\Features\Games\Plugins\Quiz\DTOs\JoinQuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\QuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\StartQuizDTO;
use App\Features\Games\Plugins\Quiz\DTOs\SubmitAnswerDTO;
use App\Features\Games\Plugins\Quiz\Models\QuizAnswer;
use App\Features\Games\Plugins\Quiz\Models\QuizPlayer;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Services\QuizService;
use App\Features\Users\Models\User;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class QuizAction
{
    public function __construct(private QuizService $service) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        return $this->service->config($user);
    }

    public function categories(User $user): Collection
    {
        return $this->service->categories($user);
    }

    public function questions(
        User $user,
        ?string $categoryId = null,
        ?string $difficulty = null,
        ?string $language = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        return $this->service->questions($user, $categoryId, $difficulty, $language, $perPage);
    }

    /** @return array{session: QuizSession, wallet: Wallet, replay: bool} */
    public function create(User $user, QuizDTO $data): array
    {
        return $this->service->create($user, $data);
    }

    /** @return array{session: QuizSession, player: QuizPlayer, wallet: Wallet, replay: bool} */
    public function join(User $user, JoinQuizDTO $data): array
    {
        return $this->service->join($user, $data);
    }

    /** @return array{session: QuizSession, question: array<string, mixed>|null, replay: bool} */
    public function start(User $user, StartQuizDTO $data): array
    {
        return $this->service->start($user, $data);
    }

    /** @return array{session: QuizSession, answer: QuizAnswer, player: QuizPlayer, score: array<string, mixed>, replay: bool} */
    public function answer(User $user, SubmitAnswerDTO $data): array
    {
        return $this->service->answer($user, $data);
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->history($user, $perPage);
    }

    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $categoryId = null, ?string $roomId = null): Collection
    {
        return $this->service->leaderboard($period, $limit, $categoryId, $roomId);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        return $this->service->statistics($user);
    }

    public function tournaments(User $user, int $perPage = 30): LengthAwarePaginator
    {
        return $this->service->tournaments($user, $perPage);
    }
}
