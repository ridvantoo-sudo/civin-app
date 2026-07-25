<?php

namespace Tests\Unit\Ludo;

use App\Features\Games\Plugins\Ludo\Support\LudoRuleEngine;
use PHPUnit\Framework\TestCase;

class LudoRuleEngineTest extends TestCase
{
    private function board(array $overrides = []): array
    {
        $pieces = [];
        foreach (LudoRuleEngine::COLORS as $color) {
            $pieces[$color] = array_map(
                fn (int $i): array => ['index' => $i, 'position' => -1, 'status' => 'yard'],
                range(0, 3),
            );
        }

        return array_replace_recursive([
            'players' => ['red' => 'u1', 'green' => 'u2'],
            'pieces' => $pieces,
            'consecutive_sixes' => ['red' => 0, 'green' => 0],
            'rules' => LudoRuleEngine::DEFAULT_RULES,
        ], $overrides);
    }

    public function test_normalize_rules_fills_defaults_and_clamps(): void
    {
        $engine = new LudoRuleEngine;
        $rules = $engine->normalizeRules([
            'safe_zones' => [0, 99, 8],
            'home_stretch_length' => 99,
            'pieces_per_player' => 99,
            'tie_strategy' => 'invalid',
            'require_six_to_exit' => 0,
        ]);

        $this->assertSame([0, 8], $rules['safe_zones']);
        $this->assertSame(6, $rules['home_stretch_length']);
        $this->assertSame(4, $rules['pieces_per_player']);
        $this->assertSame('first_to_finish', $rules['tie_strategy']);
        $this->assertFalse($rules['require_six_to_exit']);
        $this->assertTrue($rules['capture_enabled']);
    }

    public function test_roll_dice_uses_injected_rng_and_validates(): void
    {
        $engine = new LudoRuleEngine(fn (): int => 4);
        $this->assertSame(4, $engine->rollDice());
        $this->assertTrue($engine->validateDice(1));
        $this->assertTrue($engine->validateDice(6));
        $this->assertFalse($engine->validateDice(0));
        $this->assertFalse($engine->validateDice(7));

        $this->expectException(\InvalidArgumentException::class);
        (new LudoRuleEngine(fn (): int => 9))->rollDice();
    }

    public function test_require_six_to_exit_yard(): void
    {
        $engine = new LudoRuleEngine;
        $board = $this->board();

        $this->assertSame([], $engine->legalMoves($board, 'red', 5));
        $moves = $engine->legalMoves($board, 'red', 6);
        $this->assertCount(4, $moves);
        $this->assertSame(0, $moves[0]['to']);
        $this->assertTrue($moves[0]['grants_extra_turn']);
    }

    public function test_can_exit_without_six_when_configured(): void
    {
        $engine = new LudoRuleEngine;
        $board = $this->board(['rules' => ['require_six_to_exit' => false]]);
        $moves = $engine->legalMoves($board, 'green', 3);
        $this->assertNotEmpty($moves);
        $this->assertSame(13, $moves[0]['to']);
    }

    public function test_capture_on_non_safe_cell_and_extra_turn(): void
    {
        $engine = new LudoRuleEngine;
        $board = $this->board();
        $board['pieces']['red'][0] = ['index' => 0, 'position' => 1, 'status' => 'board'];
        $board['pieces']['green'][0] = ['index' => 0, 'position' => 4, 'status' => 'board'];

        $moves = $engine->legalMoves($board, 'red', 3);
        $capture = $this->firstMove($moves, 0);
        $this->assertNotNull($capture);
        $this->assertSame([['color' => 'green', 'piece_index' => 0]], $capture['captures']);
        $this->assertTrue($capture['grants_extra_turn']);

        $applied = $engine->applyMove($board, 'red', 0, 3);
        $this->assertSame(-1, $applied['board']['pieces']['green'][0]['position']);
        $this->assertSame('yard', $applied['board']['pieces']['green'][0]['status']);
        $this->assertTrue($applied['extra_turn']);
        $this->assertContains('player_captured', array_column($applied['events'], 'type'));
    }

    public function test_safe_zones_block_capture(): void
    {
        $engine = new LudoRuleEngine;
        $board = $this->board();
        $board['pieces']['red'][0] = ['index' => 0, 'position' => 5, 'status' => 'board'];
        $board['pieces']['green'][0] = ['index' => 0, 'position' => 8, 'status' => 'board'];

        $moves = $engine->legalMoves($board, 'red', 3);
        $move = $this->firstMove($moves, 0);
        $this->assertSame(8, $move['to']);
        $this->assertSame([], $move['captures']);
        $this->assertTrue($engine->isSafe(8, $board['rules']));
        $this->assertFalse($engine->isSafe(4, $board['rules']));
    }

    /** @param list<array<string, mixed>> $moves */
    private function firstMove(array $moves, int $pieceIndex): ?array
    {
        foreach ($moves as $move) {
            if ($move['piece_index'] === $pieceIndex) {
                return $move;
            }
        }

        return null;
    }

    public function test_home_entry_requires_exact_remaining_steps(): void
    {
        $engine = new LudoRuleEngine;
        $board = $this->board();
        // red start=0; travelled 51 => absolute cell 51
        $board['pieces']['red'][0] = ['index' => 0, 'position' => 51, 'status' => 'board'];

        $tooFar = $engine->legalMoves($board, 'red', 7);
        $this->assertSame([], $tooFar);

        $enter = $engine->legalMoves($board, 'red', 1);
        $this->assertSame('home:red:0', $enter[0]['to']);
        $this->assertTrue($enter[0]['enters_home']);

        $board['pieces']['red'][0] = ['index' => 0, 'position' => 'home:red:5', 'status' => 'home'];
        $finish = $engine->applyMove($board, 'red', 0, 1);
        $this->assertSame(100, $finish['board']['pieces']['red'][0]['position']);
        $this->assertSame('finished', $finish['board']['pieces']['red'][0]['status']);
        $this->assertTrue($finish['extra_turn']);
    }

    public function test_detect_winner_when_all_pieces_finished(): void
    {
        $engine = new LudoRuleEngine;
        $board = $this->board();
        foreach (range(0, 3) as $i) {
            $board['pieces']['red'][$i] = ['index' => $i, 'position' => 100, 'status' => 'finished'];
        }

        $this->assertSame('red', $engine->detectWinner($board));
        $this->assertNull($engine->detectWinner($this->board()));
    }

    public function test_next_color_cycles_active_players_only(): void
    {
        $engine = new LudoRuleEngine;
        $this->assertSame('yellow', $engine->nextColor('red', ['red', 'yellow']));
        $this->assertSame('red', $engine->nextColor('yellow', ['red', 'yellow']));
        $this->assertSame('blue', $engine->nextColor('green', ['green', 'blue']));
    }

    public function test_custom_rules_disable_capture_and_extra_turn_on_six(): void
    {
        $engine = new LudoRuleEngine;
        $board = $this->board(['rules' => [
            'capture_enabled' => false,
            'extra_turn_on_six' => false,
            'require_six_to_exit' => false,
        ]]);
        $board['pieces']['red'][0] = ['index' => 0, 'position' => 1, 'status' => 'board'];
        $board['pieces']['green'][0] = ['index' => 0, 'position' => 4, 'status' => 'board'];

        $moves = $engine->legalMoves($board, 'red', 3);
        $this->assertSame([], $moves[0]['captures']);

        $exit = $engine->legalMoves($this->board(['rules' => [
            'require_six_to_exit' => false,
            'extra_turn_on_six' => false,
        ]]), 'red', 6);
        $this->assertFalse($exit[0]['grants_extra_turn']);
    }

    public function test_illegal_move_throws(): void
    {
        $engine = new LudoRuleEngine;
        $this->expectException(\InvalidArgumentException::class);
        $engine->applyMove($this->board(), 'red', 0, 2);
    }
}
