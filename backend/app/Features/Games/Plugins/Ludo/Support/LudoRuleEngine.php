<?php

namespace App\Features\Games\Plugins\Ludo\Support;

use InvalidArgumentException;

/**
 * Pure, persistence-free classic Ludo rules.
 *
 * Main-track positions are absolute cells (0..51). Home cells use
 * "home:{color}:{index}", yard is -1, and finished pieces have status
 * "finished" (position 100).
 */
final class LudoRuleEngine
{
    public const COLORS = ['red', 'green', 'yellow', 'blue'];

    public const STARTS = ['red' => 0, 'green' => 13, 'yellow' => 26, 'blue' => 39];

    public const DEFAULT_RULES = [
        'safe_zones' => [0, 8, 13, 21, 26, 34, 39, 47],
        'require_six_to_exit' => true,
        'capture_enabled' => true,
        'extra_turn_on_six' => true,
        'extra_turn_on_capture' => true,
        'extra_turn_on_finish' => true,
        'three_sixes_forfeit' => true,
        'home_stretch_length' => 6,
        'pieces_per_player' => 4,
        'tie_strategy' => 'first_to_finish',
    ];

    /** @var null|callable(): int */
    private $rng;

    public function __construct(?callable $rng = null)
    {
        $this->rng = $rng;
    }

    /** @param array<string, mixed> $rules */
    public function normalizeRules(array $rules): array
    {
        $normalized = array_replace(self::DEFAULT_RULES, $rules);
        $normalized['safe_zones'] = array_values(array_unique(array_filter(
            array_map('intval', (array) $normalized['safe_zones']),
            fn (int $cell): bool => $cell >= 0 && $cell < 52,
        )));
        foreach ([
            'require_six_to_exit', 'capture_enabled', 'extra_turn_on_six',
            'extra_turn_on_capture', 'extra_turn_on_finish', 'three_sixes_forfeit',
        ] as $key) {
            $normalized[$key] = (bool) $normalized[$key];
        }
        $normalized['home_stretch_length'] = max(1, min(6, (int) $normalized['home_stretch_length']));
        $normalized['pieces_per_player'] = max(1, min(4, (int) $normalized['pieces_per_player']));
        $normalized['tie_strategy'] = in_array($normalized['tie_strategy'], ['first_to_finish', 'shared_pot'], true)
            ? $normalized['tie_strategy']
            : 'first_to_finish';

        return $normalized;
    }

    public function rollDice(): int
    {
        $value = $this->rng === null ? random_int(1, 6) : ($this->rng)();
        if (! $this->validateDice($value)) {
            throw new InvalidArgumentException('Dice generator must return a value from 1 to 6.');
        }

        return $value;
    }

    public function validateDice(int $value): bool
    {
        return $value >= 1 && $value <= 6;
    }

    /**
     * @param  array<string, mixed>  $boardState
     * @return list<array<string, mixed>>
     */
    public function legalMoves(array $boardState, string $playerColor, int $dice): array
    {
        if (! in_array($playerColor, self::COLORS, true) || ! $this->validateDice($dice)) {
            return [];
        }

        $rules = $this->normalizeRules((array) ($boardState['rules'] ?? []));
        $pieces = (array) ($boardState['pieces'][$playerColor] ?? []);
        $moves = [];
        foreach ($pieces as $piece) {
            if (($piece['status'] ?? 'yard') === 'finished') {
                continue;
            }

            $destination = $this->destination($piece, $playerColor, $dice, $rules);
            if ($destination === null) {
                continue;
            }

            $captures = [];
            if (is_int($destination) && $rules['capture_enabled'] && ! $this->isSafe($destination, $rules)) {
                foreach ((array) ($boardState['pieces'] ?? []) as $color => $opponents) {
                    if ($color === $playerColor) {
                        continue;
                    }
                    foreach ((array) $opponents as $opponent) {
                        if (($opponent['status'] ?? null) === 'board' && ($opponent['position'] ?? null) === $destination) {
                            $captures[] = ['color' => $color, 'piece_index' => (int) $opponent['index']];
                        }
                    }
                }
            }

            $finishes = $destination === 100;
            $moves[] = [
                'piece_index' => (int) $piece['index'],
                'from' => $piece['position'] ?? -1,
                'to' => $destination,
                'captures' => $captures,
                'enters_home' => is_string($destination) && str_starts_with($destination, 'home:'),
                'finishes' => $finishes,
                'grants_extra_turn' => ($dice === 6 && $rules['extra_turn_on_six'])
                    || ($captures !== [] && $rules['extra_turn_on_capture'])
                    || ($finishes && $rules['extra_turn_on_finish']),
            ];
        }

        return $moves;
    }

    /**
     * @param  array<string, mixed>  $boardState
     * @return array{board: array<string, mixed>, events: list<array<string, mixed>>, extra_turn: bool, winner_color: ?string}
     */
    public function applyMove(array $boardState, string $playerColor, int $pieceIndex, int $dice): array
    {
        $moves = $this->legalMoves($boardState, $playerColor, $dice);
        $move = null;
        foreach ($moves as $candidate) {
            if ($candidate['piece_index'] === $pieceIndex) {
                $move = $candidate;
                break;
            }
        }
        if ($move === null) {
            throw new InvalidArgumentException('Illegal Ludo move.');
        }

        $board = $boardState;
        $events = [];
        $piece = &$board['pieces'][$playerColor][$pieceIndex];
        $piece['position'] = $move['to'];
        $piece['status'] = match (true) {
            $move['finishes'] => 'finished',
            is_string($move['to']) => 'home',
            default => 'board',
        };
        $events[] = ['type' => 'piece_moved', 'color' => $playerColor] + $move;

        foreach ($move['captures'] as $capture) {
            $captured = &$board['pieces'][$capture['color']][$capture['piece_index']];
            $captured['position'] = -1;
            $captured['status'] = 'yard';
            $events[] = ['type' => 'player_captured', 'by_color' => $playerColor] + $capture;
        }

        $winner = $this->detectWinner($board);
        if ($winner !== null) {
            $events[] = ['type' => 'winner', 'color' => $winner];
        }

        return [
            'board' => $board,
            'events' => $events,
            'extra_turn' => $winner === null && (bool) $move['grants_extra_turn'],
            'winner_color' => $winner,
        ];
    }

    /** @param array<string, mixed> $rules */
    public function isSafe(int $absoluteCell, array $rules): bool
    {
        return in_array($absoluteCell, $this->normalizeRules($rules)['safe_zones'], true);
    }

    /** @param array<string, mixed> $boardState */
    public function detectWinner(array $boardState): ?string
    {
        foreach (self::COLORS as $color) {
            $pieces = (array) ($boardState['pieces'][$color] ?? []);
            if ($pieces !== [] && count(array_filter(
                $pieces,
                fn (array $piece): bool => ($piece['status'] ?? null) === 'finished',
            )) === count($pieces)) {
                return $color;
            }
        }

        return null;
    }

    /** @param list<string> $activeColors */
    public function nextColor(string $current, array $activeColors): string
    {
        $active = array_values(array_filter(self::COLORS, fn (string $color): bool => in_array($color, $activeColors, true)));
        if ($active === []) {
            throw new InvalidArgumentException('At least one active color is required.');
        }
        $index = array_search($current, $active, true);

        return $active[$index === false ? 0 : (($index + 1) % count($active))];
    }

    /**
     * Convert the absolute representation into a color-relative travelled
     * distance, apply the dice, then convert back.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, mixed>  $rules
     */
    private function destination(array $piece, string $color, int $dice, array $rules): int|string|null
    {
        $status = (string) ($piece['status'] ?? 'yard');
        $position = $piece['position'] ?? -1;
        if ($status === 'yard' || $position === -1) {
            if ($rules['require_six_to_exit'] && $dice !== 6) {
                return null;
            }

            return self::STARTS[$color];
        }

        $homeLength = (int) $rules['home_stretch_length'];
        if ($status === 'home' || is_string($position)) {
            $parts = explode(':', (string) $position);
            $homeIndex = (int) ($parts[2] ?? -1);
            $target = $homeIndex + $dice;
            if ($target > $homeLength) {
                return null;
            }

            return $target === $homeLength ? 100 : "home:{$color}:{$target}";
        }

        $start = self::STARTS[$color];
        $travelled = ((int) $position - $start + 52) % 52;
        $target = $travelled + $dice;
        if ($target < 52) {
            return ($start + $target) % 52;
        }
        $homeIndex = $target - 52;
        if ($homeIndex > $homeLength) {
            return null;
        }

        return $homeIndex === $homeLength ? 100 : "home:{$color}:{$homeIndex}";
    }
}
