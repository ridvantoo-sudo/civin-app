<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Support;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsBracket;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use Illuminate\Validation\ValidationException;

/**
 * Deterministic bracket construction.
 *
 * Every node is a plain array keyed by `key` ("type:round:slot"); `next_key` and
 * `next_loser_key` reference other nodes by the same scheme so the caller can
 * persist rows first and resolve the links afterwards. Given the same seed and
 * participant list the output is byte-for-byte identical, which keeps replays,
 * audits, and tests stable.
 */
final class RpsBracketGenerator
{
    /**
     * @param  list<string>  $participantIds
     * @return list<array<string, mixed>>
     */
    public function generate(string $format, array $participantIds, string $seed): array
    {
        return match ($format) {
            RpsTournament::FORMAT_DOUBLE_ELIMINATION => $this->doubleElimination($participantIds, $seed),
            RpsTournament::FORMAT_ROUND_ROBIN => $this->roundRobin($participantIds, $seed),
            RpsTournament::FORMAT_SINGLE_ELIMINATION => $this->singleElimination($participantIds, $seed),
            default => throw ValidationException::withMessages([
                'format' => 'Unsupported tournament format.',
            ]),
        };
    }

    /**
     * Stable shuffle: order by a seeded digest of each participant id, so the
     * seeding is unpredictable to players yet reproducible server side.
     *
     * @param  list<string>  $participantIds
     * @return list<string>
     */
    public function seedOrder(array $participantIds, string $seed): array
    {
        $ids = array_values(array_unique(array_map('strval', $participantIds)));
        usort($ids, static function (string $a, string $b) use ($seed): int {
            $ha = hash('sha256', $seed.'|'.$a);
            $hb = hash('sha256', $seed.'|'.$b);

            return $ha === $hb ? strcmp($a, $b) : strcmp($ha, $hb);
        });

        return $ids;
    }

    /**
     * @param  list<string>  $participantIds
     * @return list<array<string, mixed>>
     */
    public function singleElimination(array $participantIds, string $seed): array
    {
        $ids = $this->seedOrder($participantIds, $seed);
        $this->assertMinimumPlayers($ids);

        $size = $this->bracketSize(count($ids));
        $rounds = (int) log($size, 2);
        $nodes = [];

        for ($round = 0; $round < $rounds; $round++) {
            $slots = intdiv($size, 2 ** ($round + 1));
            for ($slot = 0; $slot < $slots; $slot++) {
                $playerOne = $round === 0 ? ($ids[$slot] ?? null) : null;
                $playerTwo = $round === 0 ? ($ids[$size - 1 - $slot] ?? null) : null;

                $nodes[] = $this->node(
                    RpsBracket::TYPE_WINNERS,
                    $round,
                    $slot,
                    $playerOne,
                    $playerTwo,
                    $round + 1 < $rounds
                        ? $this->key(RpsBracket::TYPE_WINNERS, $round + 1, intdiv($slot, 2))
                        : null,
                );
            }
        }

        return $nodes;
    }

    /**
     * @param  list<string>  $participantIds
     * @return list<array<string, mixed>>
     */
    public function doubleElimination(array $participantIds, string $seed): array
    {
        $ids = $this->seedOrder($participantIds, $seed);
        $this->assertMinimumPlayers($ids);

        $size = $this->bracketSize(count($ids));
        $winnerRounds = (int) log($size, 2);
        $nodes = [];

        // Winners bracket, with each loser dropped into the losers bracket.
        for ($round = 0; $round < $winnerRounds; $round++) {
            $slots = intdiv($size, 2 ** ($round + 1));
            for ($slot = 0; $slot < $slots; $slot++) {
                $nodes[] = $this->node(
                    RpsBracket::TYPE_WINNERS,
                    $round,
                    $slot,
                    $round === 0 ? ($ids[$slot] ?? null) : null,
                    $round === 0 ? ($ids[$size - 1 - $slot] ?? null) : null,
                    $round + 1 < $winnerRounds
                        ? $this->key(RpsBracket::TYPE_WINNERS, $round + 1, intdiv($slot, 2))
                        : $this->key(RpsBracket::TYPE_FINAL, 0, 0),
                    $this->loserDropKey($round, $slot, $winnerRounds),
                );
            }
        }

        // Losers bracket: odd rounds pair losers-bracket survivors against each
        // other, even rounds absorb the next wave of winners-bracket losers.
        $loserRounds = max(0, 2 * ($winnerRounds - 1));
        for ($round = 0; $round < $loserRounds; $round++) {
            $slots = $this->loserRoundSlots($round, $size);
            for ($slot = 0; $slot < $slots; $slot++) {
                $nodes[] = $this->node(
                    RpsBracket::TYPE_LOSERS,
                    $round,
                    $slot,
                    null,
                    null,
                    $round + 1 < $loserRounds
                        ? $this->key(RpsBracket::TYPE_LOSERS, $round + 1, $round % 2 === 0 ? $slot : intdiv($slot, 2))
                        : $this->key(RpsBracket::TYPE_FINAL, 0, 0),
                );
            }
        }

        $nodes[] = $this->node(RpsBracket::TYPE_FINAL, 0, 0, null, null, null);

        return $nodes;
    }

    /**
     * Circle method: participant 0 stays fixed while the rest rotate, so every
     * player meets every other exactly once.
     *
     * @param  list<string>  $participantIds
     * @return list<array<string, mixed>>
     */
    public function roundRobin(array $participantIds, string $seed): array
    {
        $ids = $this->seedOrder($participantIds, $seed);
        $this->assertMinimumPlayers($ids);

        $wheel = $ids;
        if (count($wheel) % 2 === 1) {
            $wheel[] = null;
        }

        $count = count($wheel);
        $rounds = $count - 1;
        $half = intdiv($count, 2);
        $nodes = [];

        for ($round = 0; $round < $rounds; $round++) {
            for ($slot = 0; $slot < $half; $slot++) {
                $one = $wheel[$slot];
                $two = $wheel[$count - 1 - $slot];
                if ($one === null || $two === null) {
                    // Odd field: whoever draws the placeholder simply sits out.
                    continue;
                }

                $nodes[] = $this->node(RpsBracket::TYPE_ROUND_ROBIN, $round, $slot, $one, $two, null);
            }

            $rotating = array_slice($wheel, 1);
            $last = array_pop($rotating);
            array_unshift($rotating, $last);
            $wheel = array_merge([$wheel[0]], $rotating);
        }

        return $nodes;
    }

    public function bracketSize(int $players): int
    {
        $size = 2;
        while ($size < $players) {
            $size *= 2;
        }

        return $size;
    }

    public function key(string $type, int $round, int $slot): string
    {
        return sprintf('%s:%d:%d', $type, $round, $slot);
    }

    /** @param list<string> $ids */
    private function assertMinimumPlayers(array $ids): void
    {
        if (count($ids) < 2) {
            throw ValidationException::withMessages([
                'participants' => 'A tournament needs at least two participants.',
            ]);
        }
    }

    private function loserRoundSlots(int $round, int $size): int
    {
        // Rounds pair up as (2j-1, 2j); both hold size / 2^(j+1) nodes.
        $pair = intdiv($round, 2) + 1;

        return max(1, intdiv($size, 2 ** ($pair + 1)));
    }

    private function loserDropKey(int $winnerRound, int $slot, int $winnerRounds): ?string
    {
        if ($winnerRounds < 2) {
            // Two-player double elimination: the loser gets one reset shot.
            return $this->key(RpsBracket::TYPE_FINAL, 0, 0);
        }

        if ($winnerRound === 0) {
            // Two winners-bracket losers meet in each opening losers match.
            return $this->key(RpsBracket::TYPE_LOSERS, 0, intdiv($slot, 2));
        }

        // Later waves drop into the odd losers rounds, which exist to absorb
        // them and always hold exactly as many slots as the winners round did.
        return $this->key(RpsBracket::TYPE_LOSERS, 2 * $winnerRound - 1, $slot);
    }

    /** @return array<string, mixed> */
    private function node(
        string $type,
        int $round,
        int $slot,
        ?string $playerOne,
        ?string $playerTwo,
        ?string $nextKey,
        ?string $nextLoserKey = null,
    ): array {
        $isBye = ($playerOne === null) !== ($playerTwo === null);

        return [
            'key' => $this->key($type, $round, $slot),
            'bracket_type' => $type,
            'round_index' => $round,
            'slot_index' => $slot,
            'player_one_id' => $playerOne,
            'player_two_id' => $playerTwo,
            'winner_id' => $isBye ? ($playerOne ?? $playerTwo) : null,
            'is_bye' => $isBye,
            'status' => match (true) {
                $isBye => RpsBracket::STATUS_BYE,
                $playerOne !== null && $playerTwo !== null => RpsBracket::STATUS_READY,
                default => RpsBracket::STATUS_PENDING,
            },
            'next_key' => $nextKey,
            'next_loser_key' => $nextLoserKey,
        ];
    }
}
