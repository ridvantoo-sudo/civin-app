<?php

namespace App\Features\Games\Plugins\RockPaperScissors\Support;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use Illuminate\Validation\ValidationException;

/**
 * Config-driven move resolution.
 *
 * A rule set is `['moves' => list<string>, 'beats' => array<string, list<string>>]`.
 * The default set is rock/paper/scissors, but any balanced variant (lizard/spock,
 * five-move wheels, themed reskins) works without code changes as long as every
 * move appears in `moves` and every `beats` target is itself a known move.
 */
final class RpsRulesEngine
{
    public const CHALLENGER_WINS = 1;

    public const OPPONENT_WINS = -1;

    public const DRAW = 0;

    /**
     * @param  array<string, mixed>  $rules
     * @return array{moves: list<string>, beats: array<string, list<string>>}
     */
    public function normalize(array $rules): array
    {
        $moves = $rules['moves'] ?? null;
        $beats = $rules['beats'] ?? null;

        if (! is_array($moves) || $moves === [] || ! is_array($beats)) {
            return RpsConfig::DEFAULT_MOVE_RULES;
        }

        $normalizedMoves = [];
        foreach ($moves as $move) {
            if (! is_string($move) && ! is_int($move)) {
                continue;
            }
            $slug = $this->slug((string) $move);
            if ($slug !== '' && ! in_array($slug, $normalizedMoves, true)) {
                $normalizedMoves[] = $slug;
            }
        }

        if ($normalizedMoves === []) {
            return RpsConfig::DEFAULT_MOVE_RULES;
        }

        $normalizedBeats = [];
        foreach ($normalizedMoves as $move) {
            $targets = $beats[$move] ?? [];
            $targets = is_array($targets) ? $targets : [$targets];
            $clean = [];
            foreach ($targets as $target) {
                if (! is_string($target) && ! is_int($target)) {
                    continue;
                }
                $slug = $this->slug((string) $target);
                if ($slug !== '' && $slug !== $move && in_array($slug, $normalizedMoves, true) && ! in_array($slug, $clean, true)) {
                    $clean[] = $slug;
                }
            }
            $normalizedBeats[$move] = $clean;
        }

        return ['moves' => $normalizedMoves, 'beats' => $normalizedBeats];
    }

    /**
     * @param  array<string, mixed>  $rules
     *
     * @throws ValidationException when the rule set can never produce a winner
     */
    public function assertValidRuleSet(array $rules): void
    {
        $normalized = $this->normalize($rules);

        if (count($normalized['moves']) < 2) {
            throw ValidationException::withMessages([
                'move_rules' => 'A rule set needs at least two moves.',
            ]);
        }

        $hasOutcome = false;
        foreach ($normalized['beats'] as $targets) {
            if ($targets !== []) {
                $hasOutcome = true;
                break;
            }
        }

        if (! $hasOutcome) {
            throw ValidationException::withMessages([
                'move_rules' => 'A rule set needs at least one winning relationship.',
            ]);
        }

        // A move that both beats X and loses to X makes resolution ambiguous.
        foreach ($normalized['beats'] as $move => $targets) {
            foreach ($targets as $target) {
                if (in_array($move, $normalized['beats'][$target] ?? [], true)) {
                    throw ValidationException::withMessages([
                        'move_rules' => sprintf('Moves "%s" and "%s" beat each other.', $move, $target),
                    ]);
                }
            }
        }
    }

    /** @param array<string, mixed> $rules */
    public function isValidMove(array $rules, ?string $move): bool
    {
        if ($move === null) {
            return false;
        }

        return in_array($this->slug($move), $this->normalize($rules)['moves'], true);
    }

    /** @return list<string> */
    public function moves(array $rules): array
    {
        return $this->normalize($rules)['moves'];
    }

    /**
     * Compare two submitted moves. A null move means the player did not submit
     * in time and automatically loses unless both sides missed.
     *
     * @param  array<string, mixed>  $rules
     * @return int one of CHALLENGER_WINS, OPPONENT_WINS, DRAW
     */
    public function compare(array $rules, ?string $challengerMove, ?string $opponentMove): int
    {
        $normalized = $this->normalize($rules);
        $challenger = $challengerMove === null ? null : $this->slug($challengerMove);
        $opponent = $opponentMove === null ? null : $this->slug($opponentMove);

        $challengerValid = $challenger !== null && in_array($challenger, $normalized['moves'], true);
        $opponentValid = $opponent !== null && in_array($opponent, $normalized['moves'], true);

        if (! $challengerValid && ! $opponentValid) {
            return self::DRAW;
        }
        if (! $challengerValid) {
            return self::OPPONENT_WINS;
        }
        if (! $opponentValid) {
            return self::CHALLENGER_WINS;
        }
        if ($challenger === $opponent) {
            return self::DRAW;
        }

        if (in_array($opponent, $normalized['beats'][$challenger] ?? [], true)) {
            return self::CHALLENGER_WINS;
        }
        if (in_array($challenger, $normalized['beats'][$opponent] ?? [], true)) {
            return self::OPPONENT_WINS;
        }

        // Unrelated moves in an asymmetric rule set score as a draw.
        return self::DRAW;
    }

    /**
     * Tamper-evident fingerprint stored alongside every hidden move so a
     * revealed value can be verified against what was originally submitted.
     */
    public function moveHash(string $move, string $serverSeed, string $roundId, string $userId): string
    {
        return hash('sha256', implode('|', [$this->slug($move), $serverSeed, $roundId, $userId]));
    }

    public function verifyMoveHash(string $move, string $serverSeed, string $roundId, string $userId, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }

        return hash_equals($this->moveHash($move, $serverSeed, $roundId, $userId), $hash);
    }

    private function slug(string $value): string
    {
        return strtolower(trim($value));
    }
}
