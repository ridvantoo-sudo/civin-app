<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Services;

use App\Features\Games\Plugins\RockPaperScissors\DTOs\AcceptRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\CreateRpsMatchDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\JoinRpsTournamentDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\RematchRpsDTO;
use App\Features\Games\Plugins\RockPaperScissors\DTOs\SubmitRpsMoveDTO;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMove;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsRound;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use App\Features\Games\Plugins\RockPaperScissors\Repositories\Contracts\RpsRepository;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsRulesEngine;
use App\Features\Users\Models\User;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Wallet\Models\Wallet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Read-side queries plus the public entry points that delegate match and
 * tournament writes to their dedicated services.
 */
final readonly class RpsService
{
    public function __construct(
        private RpsRepository $matches,
        private RpsMatchService $matchService,
        private RpsTournamentService $tournamentService,
        private RpsRulesEngine $rules,
        private VipRepository $vips,
    ) {}

    /** @return array<string, mixed> */
    public function config(User $user): array
    {
        $this->ensureEligible($user);
        $config = $this->requireActiveConfig();
        $createdToday = $this->matches->countCreatedToday($user, (string) $config->getKey());
        $moveRules = $this->rules->normalize($config->moveRules());

        return [
            'id' => $config->id,
            'slug' => $config->slug,
            'name' => $config->name,
            'description' => $config->description,
            'version' => $config->version,
            'min_entry_fee_coins' => (int) $config->min_entry_fee_coins,
            'max_entry_fee_coins' => (int) $config->max_entry_fee_coins,
            'default_entry_fee_coins' => (int) $config->default_entry_fee_coins,
            'default_round_timeout_seconds' => (int) $config->default_round_timeout_seconds,
            'min_round_timeout_seconds' => (int) $config->min_round_timeout_seconds,
            'max_round_timeout_seconds' => (int) $config->max_round_timeout_seconds,
            'accept_timeout_seconds' => (int) $config->accept_timeout_seconds,
            'match_timeout_seconds' => (int) $config->match_timeout_seconds,
            'max_rounds' => (int) $config->max_rounds,
            'forfeit_after_missed_rounds' => (int) $config->forfeit_after_missed_rounds,
            'rematch_limit' => (int) $config->rematch_limit,
            'house_rake_bps' => (int) $config->house_rake_bps,
            'vip_reward_multiplier' => (float) $config->vip_reward_multiplier,
            'modes_enabled' => $config->modes_enabled ?? RpsConfig::MODES,
            'matchmaking_enabled' => $config->matchmaking_enabled ?? RpsConfig::MATCHMAKING,
            'reward_types_enabled' => $config->reward_types_enabled ?? RpsConfig::REWARD_TYPES,
            'visibilities_enabled' => $config->visibilities_enabled ?? RpsConfig::VISIBILITIES,
            'eligibilities_enabled' => $config->eligibilities_enabled ?? RpsConfig::ELIGIBILITIES,
            'tournament_formats_enabled' => $config->tournament_formats_enabled ?? RpsConfig::TOURNAMENT_FORMATS,
            'move_rules' => $moveRules,
            'moves' => $moveRules['moves'],
            'mode_rounds' => $config->mode_rounds ?? RpsConfig::DEFAULT_MODE_ROUNDS,
            'max_created_per_day' => $config->max_created_per_day,
            'created_today' => $createdToday,
            'remaining_today' => $config->max_created_per_day === null
                ? null
                : max(0, (int) $config->max_created_per_day - $createdToday),
            'is_vip' => $this->userIsVip($user),
            'status' => $config->status,
            'enabled' => (bool) $config->enabled,
            'metadata' => $config->metadata,
        ];
    }

    /** @return array{match: RpsMatch, wallet: Wallet, replay: bool, matched: bool} */
    public function create(User $user, CreateRpsMatchDTO $data): array
    {
        return $this->matchService->create($user, $data);
    }

    /** @return array{match: RpsMatch, wallet: Wallet, replay: bool} */
    public function accept(User $user, AcceptRpsMatchDTO $data): array
    {
        return $this->matchService->accept($user, $data);
    }

    /** @return array{match: RpsMatch, round: RpsRound, move: RpsMove, replay: bool, resolved: bool, finished: bool} */
    public function move(User $user, SubmitRpsMoveDTO $data): array
    {
        return $this->matchService->move($user, $data);
    }

    /** @return array{match: RpsMatch, wallet: Wallet, replay: bool} */
    public function rematch(User $user, RematchRpsDTO $data): array
    {
        return $this->matchService->rematch($user, $data);
    }

    /** @return array{tournament: RpsTournament, wallet: Wallet, replay: bool} */
    public function joinTournament(User $user, JoinRpsTournamentDTO $data): array
    {
        $this->ensureEligible($user);

        return $this->tournamentService->join($user, $data);
    }

    public function history(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->matches->history($user, $perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function leaderboard(string $period = 'daily', int $limit = 50, ?string $mode = null, ?string $roomId = null): Collection
    {
        return $this->matches->leaderboard($period, $limit, $mode, $roomId);
    }

    /** @return array<string, mixed> */
    public function statistics(User $user): array
    {
        $this->ensureEligible($user);

        return $this->matches->statistics($user);
    }

    public function tournaments(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureEligible($user);

        return $this->tournamentService->list($perPage);
    }

    /**
     * Fetch a match the caller is allowed to see. Public matches are visible to
     * anyone; everything else is restricted to its two players.
     */
    public function show(User $user, string $matchId): RpsMatch
    {
        $this->ensureEligible($user);

        $match = $this->matches->findMatch($matchId);
        if ($match === null) {
            throw ValidationException::withMessages(['match_id' => 'Match not found.']);
        }

        $isPlayer = $match->roleFor((string) $user->getKey()) !== null;
        if (! $isPlayer && $match->visibility !== 'public') {
            throw new AuthorizationException('You cannot view this match.');
        }

        return $match;
    }

    private function requireActiveConfig(): RpsConfig
    {
        $config = $this->matches->findDefaultConfig();
        if ($config === null || ! $config->isActive()) {
            throw ValidationException::withMessages(['config' => 'Rock Paper Scissors is unavailable.']);
        }

        return $config;
    }

    private function ensureEligible(User $user): void
    {
        if ($user->status !== User::STATUS_ACTIVE || $user->is_guest) {
            throw new AuthorizationException('Not eligible to play Rock Paper Scissors.');
        }
    }

    private function userIsVip(User $user): bool
    {
        $subscription = $this->vips->findForUser($user);

        return $subscription !== null && $subscription->isActive();
    }
}
