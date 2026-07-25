<?php

namespace App\Features\Games\Plugins\SlotMachine\Support;

use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineConfig;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachinePayline;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSymbol;
use App\Features\Games\Services\GameRng;
use Illuminate\Support\Collection;

/**
 * Server-side weighted RNG grid generation and payline settlement.
 * Clients never compute outcomes — they only animate this result.
 */
final class SlotSpinEvaluator
{
    public function __construct(private readonly GameRng $rng) {}

    /**
     * @param  Collection<int, SlotMachineSymbol>  $symbols
     * @param  Collection<int, SlotMachinePayline>  $paylines
     * @return array{
     *   grid: list<list<string>>,
     *   rolls: list<int>,
     *   payline_wins: list<array<string, mixed>>,
     *   reward_coins: int,
     *   free_spins_awarded: int,
     *   bonus_triggered: bool,
     *   jackpot_triggered: bool,
     *   total_multiplier: int,
     *   scatter_count: int,
     *   bonus_count: int,
     *   jackpot_symbol_count: int
     * }
     */
    public function evaluate(
        SlotMachineConfig $config,
        Collection $symbols,
        Collection $paylines,
        string $gameMode,
        int $betAmount,
    ): array {
        $activeSymbols = $symbols
            ->filter(fn (SlotMachineSymbol $s) => $s->isActive() && $s->weight > 0)
            ->values();

        if ($activeSymbols->isEmpty()) {
            throw new \InvalidArgumentException('Slot Machine has no active symbols.');
        }

        [$columns, $rows, $layout] = $this->layoutForMode($config, $gameMode);

        $byCode = $activeSymbols->keyBy('code');
        $totalWeight = (int) $activeSymbols->sum('weight');
        $grid = [];
        $rolls = [];

        for ($col = 0; $col < $columns; $col++) {
            $column = [];
            for ($row = 0; $row < $rows; $row++) {
                $roll = $this->rng->int(1, $totalWeight);
                $rolls[] = $roll;
                $column[] = $this->pickSymbolCode($activeSymbols, $roll);
            }
            $grid[] = $column;
        }

        $layoutPaylines = $paylines
            ->filter(fn (SlotMachinePayline $p) => $p->isActive() && $p->layout === $layout)
            ->values();

        $paylineWins = [];
        $rewardCoins = 0;

        foreach ($layoutPaylines as $payline) {
            $win = $this->evaluatePayline($grid, $payline, $byCode, $betAmount, $layoutPaylines->count());
            if ($win !== null) {
                $paylineWins[] = $win;
                $rewardCoins += (int) $win['coins'];
            }
        }

        $scatterCount = $this->countSymbolFlag($grid, $byCode, 'is_scatter');
        $bonusCount = $this->countSymbolFlag($grid, $byCode, 'is_bonus');
        $jackpotSymbolCount = $this->countSymbolFlag($grid, $byCode, 'is_jackpot');
        $multiplier = max(1, $this->gridMultiplier($grid, $byCode));

        if ($gameMode === SlotMachineConfig::MODE_VIP) {
            $multiplier = max(1, (int) round($multiplier * (float) $config->vip_reward_multiplier));
        }

        $rewardCoins = (int) floor($rewardCoins * $multiplier);

        $freeSpinsAwarded = 0;
        if ($scatterCount >= (int) $config->free_spin_award_on_scatters) {
            $freeSpinsAwarded = (int) $config->free_spins_granted;
        }

        $bonusTriggered = $config->bonus_enabled
            && $bonusCount >= (int) $config->bonus_trigger_symbols;

        if ($bonusTriggered) {
            $probs = $config->bonus_probabilities ?? [];
            $mysteryChance = (int) ($probs['mystery_reward'] ?? 5);
            if ($mysteryChance > 0 && $this->rng->int(1, 100) <= $mysteryChance) {
                $rewardCoins += (int) floor($betAmount * ($probs['mystery_multiplier'] ?? 5));
            }
            $comboChance = (int) ($probs['combo_reward'] ?? 10);
            if ($comboChance > 0 && $this->rng->int(1, 100) <= $comboChance) {
                $rewardCoins += (int) floor($betAmount * ($probs['combo_multiplier'] ?? 3));
            }
        }

        $jackpotTriggered = $config->jackpot_enabled && $jackpotSymbolCount >= 3;

        // Soft RTP clamp: if configured RTP is below 100 and win is extreme, rarely trim.
        // Settlement remains server-authoritative; weights remain the primary control.
        $rtp = (float) $config->rtp_percent;
        if ($rtp > 0 && $rtp < 100 && $rewardCoins > $betAmount * 50) {
            $trimRoll = $this->rng->int(1, 1000);
            if ($trimRoll > (int) round($rtp * 10)) {
                $rewardCoins = (int) floor($rewardCoins * ($rtp / 100));
            }
        }

        return [
            'grid' => $grid,
            'rolls' => $rolls,
            'payline_wins' => $paylineWins,
            'reward_coins' => $rewardCoins,
            'free_spins_awarded' => $freeSpinsAwarded,
            'bonus_triggered' => $bonusTriggered,
            'jackpot_triggered' => $jackpotTriggered,
            'total_multiplier' => $multiplier,
            'scatter_count' => $scatterCount,
            'bonus_count' => $bonusCount,
            'jackpot_symbol_count' => $jackpotSymbolCount,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    public function layoutForMode(SlotMachineConfig $config, string $gameMode): array
    {
        return match ($gameMode) {
            SlotMachineConfig::MODE_PREMIUM,
            SlotMachineConfig::MODE_VIP,
            SlotMachineConfig::MODE_PROGRESSIVE_JACKPOT,
            SlotMachineConfig::MODE_DAILY_JACKPOT => [
                (int) $config->premium_columns,
                (int) $config->premium_rows,
                SlotMachinePayline::LAYOUT_PREMIUM,
            ],
            default => [
                (int) $config->classic_columns,
                (int) $config->classic_rows,
                SlotMachinePayline::LAYOUT_CLASSIC,
            ],
        };
    }

    /**
     * @param  Collection<int, SlotMachineSymbol>  $symbols
     */
    private function pickSymbolCode(Collection $symbols, int $roll): string
    {
        $cursor = 0;
        $selected = $symbols->first();
        foreach ($symbols as $symbol) {
            $cursor += (int) $symbol->weight;
            if ($roll <= $cursor) {
                $selected = $symbol;
                break;
            }
        }

        return (string) $selected->code;
    }

    /**
     * @param  list<list<string>>  $grid
     * @param  Collection<string, SlotMachineSymbol>  $byCode
     * @return ?array<string, mixed>
     */
    private function evaluatePayline(
        array $grid,
        SlotMachinePayline $payline,
        Collection $byCode,
        int $betAmount,
        int $paylineCount,
    ): ?array {
        $positions = $payline->positions ?? [];
        if ($positions === []) {
            return null;
        }

        $codes = [];
        foreach ($positions as $pos) {
            $col = (int) ($pos[0] ?? -1);
            $row = (int) ($pos[1] ?? -1);
            if (! isset($grid[$col][$row])) {
                return null;
            }
            $codes[] = $grid[$col][$row];
        }

        $target = null;
        $matchCount = 0;
        foreach ($codes as $code) {
            $symbol = $byCode->get($code);
            if ($symbol === null) {
                break;
            }
            if ($symbol->is_scatter || $symbol->is_bonus) {
                break;
            }
            if ($symbol->is_wild) {
                $matchCount++;

                continue;
            }
            if ($target === null) {
                $target = $code;
                $matchCount++;

                continue;
            }
            if ($code === $target) {
                $matchCount++;

                continue;
            }
            break;
        }

        if ($target === null || $matchCount < 3) {
            return null;
        }

        $symbol = $byCode->get($target);
        if ($symbol === null) {
            return null;
        }

        $payoutMult = $symbol->payoutForCount($matchCount);
        if ($payoutMult <= 0) {
            return null;
        }

        $lineBet = max(1, (int) floor($betAmount / max(1, $paylineCount)));
        $coins = $lineBet * $payoutMult;

        return [
            'payline_code' => $payline->code,
            'payline_label' => $payline->label,
            'symbol' => $target,
            'match_count' => $matchCount,
            'positions' => $positions,
            'multiplier' => $payoutMult,
            'coins' => $coins,
        ];
    }

    /**
     * @param  list<list<string>>  $grid
     * @param  Collection<string, SlotMachineSymbol>  $byCode
     */
    private function countSymbolFlag(array $grid, Collection $byCode, string $flag): int
    {
        $count = 0;
        foreach ($grid as $column) {
            foreach ($column as $code) {
                $symbol = $byCode->get($code);
                if ($symbol !== null && (bool) $symbol->{$flag}) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  list<list<string>>  $grid
     * @param  Collection<string, SlotMachineSymbol>  $byCode
     */
    private function gridMultiplier(array $grid, Collection $byCode): int
    {
        $mult = 1;
        foreach ($grid as $column) {
            foreach ($column as $code) {
                $symbol = $byCode->get($code);
                if ($symbol !== null && $symbol->is_multiplier && $symbol->multiplier_value > 1) {
                    $mult *= (int) $symbol->multiplier_value;
                }
            }
        }

        return max(1, $mult);
    }
}
