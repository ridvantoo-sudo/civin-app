<?php

namespace App\Features\Games\Plugins\Ludo\Support;

final class LudoBracketGenerator
{
    /** @param list<string> $playerIds */
    public function singleElimination(array $playerIds): array
    {
        shuffle($playerIds);
        $size = 1;
        while ($size < count($playerIds)) {
            $size *= 2;
        }
        $players = array_pad($playerIds, $size, null);
        $brackets = [];
        foreach (array_chunk($players, 4) as $position => $group) {
            $brackets[] = ['round' => 1, 'position' => $position + 1, 'player_ids' => array_values(array_filter($group))];
        }

        return $brackets;
    }
}
