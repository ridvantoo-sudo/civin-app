<?php

namespace App\Features\Ranking\Services;

use App\Features\Ranking\DTOs\RankingQueryData;
use App\Features\Ranking\DTOs\RankingScoreData;
use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Repositories\Contracts\RankingRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final readonly class RankingCalculatorService
{
    private const CALCULATE_LIMIT = 500;

    public function __construct(private RankingRepository $rankings) {}

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function calculate(RankingQueryData $query): Collection
    {
        [$from, $to] = $this->periodBounds($query->period);

        return match ($query->type) {
            Ranking::TYPE_HOST_DIAMONDS => $this->rankings->aggregateHostDiamonds($from, $to, $query->country, $query->limit),
            Ranking::TYPE_TOP_GIFTER => $this->rankings->aggregateTopGifters($from, $to, $query->country, $query->limit),
            Ranking::TYPE_PK_WINNER => $this->rankings->aggregatePkWinners($from, $to, $query->country, $query->limit),
            Ranking::TYPE_VOICE_HOST => $this->rankings->aggregateVoiceHosts($from, $to, $query->country, $query->limit),
            Ranking::TYPE_POPULAR_USER => $this->rankings->aggregatePopularUsers($from, $to, $query->country, $query->limit),
            default => throw new InvalidArgumentException("Unsupported ranking type [{$query->type}]."),
        };
    }

    /**
     * @return Collection<int, RankingScoreData>
     */
    public function calculateAndPersist(string $type, string $period, ?CarbonInterface $asOf = null): Collection
    {
        if (! in_array($type, Ranking::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported ranking type [{$type}].");
        }

        if (! in_array($period, Ranking::PERIODS, true)) {
            throw new InvalidArgumentException("Unsupported ranking period [{$period}].");
        }

        $asOf ??= now();
        $date = $this->periodDate($period, $asOf);
        $scores = $this->calculate(new RankingQueryData(
            type: $type,
            period: $period,
            country: null,
            limit: self::CALCULATE_LIMIT,
        ));

        $this->rankings->replacePeriodRankings($type, $period, $date, $scores);
        $this->rankings->createSnapshot($type, $period, [
            'date' => $date->toDateString(),
            'generated_at' => now()->toISOString(),
            'entries' => $scores->map(fn (RankingScoreData $score): array => $score->toArray())->all(),
        ]);

        return $scores;
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function periodBounds(string $period, ?CarbonInterface $asOf = null): array
    {
        $asOf = CarbonImmutable::instance($asOf ?? now());

        return match ($period) {
            Ranking::PERIOD_DAILY => [$asOf->startOfDay(), $asOf->endOfDay()],
            Ranking::PERIOD_WEEKLY => [$asOf->startOfWeek(), $asOf->endOfWeek()],
            Ranking::PERIOD_MONTHLY => [$asOf->startOfMonth(), $asOf->endOfMonth()],
            Ranking::PERIOD_ALL_TIME => [null, null],
            default => throw new InvalidArgumentException("Unsupported ranking period [{$period}]."),
        };
    }

    public function periodDate(string $period, ?CarbonInterface $asOf = null): CarbonImmutable
    {
        $asOf = CarbonImmutable::instance($asOf ?? now());

        return match ($period) {
            Ranking::PERIOD_DAILY => $asOf->startOfDay(),
            Ranking::PERIOD_WEEKLY => $asOf->startOfWeek(),
            Ranking::PERIOD_MONTHLY => $asOf->startOfMonth(),
            Ranking::PERIOD_ALL_TIME => $asOf->startOfDay(),
            default => throw new InvalidArgumentException("Unsupported ranking period [{$period}]."),
        };
    }
}
