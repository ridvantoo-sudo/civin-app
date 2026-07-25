<?php

namespace App\Features\Games\Services;

final class SecureGameRng implements GameRng
{
    public function int(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    public function sampleUnique(int $min, int $max, int $count): array
    {
        $pool = range($min, $max);
        if ($count > count($pool)) {
            $count = count($pool);
        }

        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $index = random_int(0, count($pool) - 1);
            $picked[] = $pool[$index];
            array_splice($pool, $index, 1);
        }

        sort($picked);

        return $picked;
    }
}
