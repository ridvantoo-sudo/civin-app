<?php

namespace App\Features\Levels\Support;

final class XpCurve
{
    public static function maxLevel(): int
    {
        return (int) config('levels.max_level', 100);
    }

    public static function minLevel(): int
    {
        return (int) config('levels.min_level', 1);
    }

    public static function xpRequiredForLevel(int $level): int
    {
        $level = max(self::minLevel(), min($level, self::maxLevel()));

        if ($level >= self::maxLevel()) {
            return 0;
        }

        $base = (int) config('levels.xp_base', 100);
        $step = (int) config('levels.xp_step', 50);

        return $base + (($level - 1) * $step);
    }

    public static function totalXpForLevel(int $level): int
    {
        $level = max(self::minLevel(), min($level, self::maxLevel()));
        $total = 0;

        for ($current = self::minLevel(); $current < $level; $current++) {
            $total += self::xpRequiredForLevel($current);
        }

        return $total;
    }

    /**
     * @return array{level: int, xp_into_level: int, xp_to_next: int, progress_percent: float}
     */
    public static function resolveFromTotalXp(int $xpTotal): array
    {
        $xpTotal = max(0, $xpTotal);
        $level = self::minLevel();
        $remaining = $xpTotal;
        $max = self::maxLevel();

        while ($level < $max) {
            $needed = self::xpRequiredForLevel($level);
            if ($remaining < $needed) {
                break;
            }
            $remaining -= $needed;
            $level++;
        }

        $xpToNext = $level >= $max ? 0 : self::xpRequiredForLevel($level);
        $progress = $xpToNext === 0
            ? 100.0
            : round(($remaining / $xpToNext) * 100, 2);

        return [
            'level' => $level,
            'xp_into_level' => $remaining,
            'xp_to_next' => $xpToNext,
            'progress_percent' => min(100.0, $progress),
        ];
    }
}
