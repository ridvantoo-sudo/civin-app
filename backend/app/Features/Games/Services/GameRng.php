<?php

namespace App\Features\Games\Services;

interface GameRng
{
    public function int(int $min, int $max): int;

    /** @return list<int> */
    public function sampleUnique(int $min, int $max, int $count): array;
}
