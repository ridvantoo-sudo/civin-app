<?php

namespace Tests\Unit\RockPaperScissors;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsConfig;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsRulesEngine;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RpsRulesEngineTest extends TestCase
{
    private RpsRulesEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RpsRulesEngine;
    }

    public function test_default_rule_set_resolves_the_classic_wheel(): void
    {
        $rules = RpsConfig::DEFAULT_MOVE_RULES;

        $this->assertSame(RpsRulesEngine::CHALLENGER_WINS, $this->engine->compare($rules, 'rock', 'scissors'));
        $this->assertSame(RpsRulesEngine::CHALLENGER_WINS, $this->engine->compare($rules, 'paper', 'rock'));
        $this->assertSame(RpsRulesEngine::CHALLENGER_WINS, $this->engine->compare($rules, 'scissors', 'paper'));

        $this->assertSame(RpsRulesEngine::OPPONENT_WINS, $this->engine->compare($rules, 'scissors', 'rock'));
        $this->assertSame(RpsRulesEngine::OPPONENT_WINS, $this->engine->compare($rules, 'rock', 'paper'));
        $this->assertSame(RpsRulesEngine::OPPONENT_WINS, $this->engine->compare($rules, 'paper', 'scissors'));

        foreach (['rock', 'paper', 'scissors'] as $move) {
            $this->assertSame(RpsRulesEngine::DRAW, $this->engine->compare($rules, $move, $move));
        }
    }

    public function test_moves_are_case_and_whitespace_insensitive(): void
    {
        $rules = RpsConfig::DEFAULT_MOVE_RULES;

        $this->assertTrue($this->engine->isValidMove($rules, '  ROCK '));
        $this->assertSame(RpsRulesEngine::CHALLENGER_WINS, $this->engine->compare($rules, ' Rock', 'SCISSORS '));
    }

    public function test_unknown_moves_are_rejected(): void
    {
        $rules = RpsConfig::DEFAULT_MOVE_RULES;

        $this->assertFalse($this->engine->isValidMove($rules, 'lizard'));
        $this->assertFalse($this->engine->isValidMove($rules, null));
        $this->assertFalse($this->engine->isValidMove($rules, ''));
    }

    public function test_missing_move_loses_and_two_missing_moves_draw(): void
    {
        $rules = RpsConfig::DEFAULT_MOVE_RULES;

        $this->assertSame(RpsRulesEngine::OPPONENT_WINS, $this->engine->compare($rules, null, 'rock'));
        $this->assertSame(RpsRulesEngine::CHALLENGER_WINS, $this->engine->compare($rules, 'rock', null));
        $this->assertSame(RpsRulesEngine::DRAW, $this->engine->compare($rules, null, null));
    }

    public function test_extended_rule_sets_work_without_code_changes(): void
    {
        $rules = [
            'moves' => ['rock', 'paper', 'scissors', 'lizard', 'spock'],
            'beats' => [
                'rock' => ['scissors', 'lizard'],
                'paper' => ['rock', 'spock'],
                'scissors' => ['paper', 'lizard'],
                'lizard' => ['paper', 'spock'],
                'spock' => ['rock', 'scissors'],
            ],
        ];

        $this->assertTrue($this->engine->isValidMove($rules, 'spock'));
        $this->assertSame(RpsRulesEngine::CHALLENGER_WINS, $this->engine->compare($rules, 'spock', 'scissors'));
        $this->assertSame(RpsRulesEngine::OPPONENT_WINS, $this->engine->compare($rules, 'spock', 'lizard'));
        $this->assertSame(RpsRulesEngine::DRAW, $this->engine->compare($rules, 'spock', 'spock'));
        $this->assertCount(5, $this->engine->moves($rules));
    }

    public function test_normalize_falls_back_to_the_default_wheel_for_garbage_input(): void
    {
        $this->assertSame(RpsConfig::DEFAULT_MOVE_RULES, $this->engine->normalize([]));
        $this->assertSame(RpsConfig::DEFAULT_MOVE_RULES, $this->engine->normalize(['moves' => 'nope']));
        $this->assertSame(RpsConfig::DEFAULT_MOVE_RULES, $this->engine->normalize(['moves' => ['rock'], 'beats' => 'nope']));
    }

    public function test_normalize_drops_self_references_and_unknown_targets(): void
    {
        $normalized = $this->engine->normalize([
            'moves' => ['Rock', 'rock', 'Paper'],
            'beats' => ['rock' => ['rock', 'dragon', 'paper'], 'paper' => []],
        ]);

        $this->assertSame(['rock', 'paper'], $normalized['moves']);
        $this->assertSame(['paper'], $normalized['beats']['rock']);
        $this->assertSame([], $normalized['beats']['paper']);
    }

    public function test_assert_valid_rule_set_rejects_degenerate_configurations(): void
    {
        $this->engine->assertValidRuleSet(RpsConfig::DEFAULT_MOVE_RULES);

        $this->expectException(ValidationException::class);
        $this->engine->assertValidRuleSet([
            'moves' => ['rock', 'paper'],
            'beats' => ['rock' => ['paper'], 'paper' => ['rock']],
        ]);
    }

    public function test_assert_valid_rule_set_requires_a_winning_relationship(): void
    {
        $this->expectException(ValidationException::class);

        $this->engine->assertValidRuleSet([
            'moves' => ['rock', 'paper'],
            'beats' => ['rock' => [], 'paper' => []],
        ]);
    }

    public function test_move_hash_is_deterministic_and_tamper_evident(): void
    {
        $hash = $this->engine->moveHash('rock', 'seed-value', 'round-1', 'user-1');

        $this->assertSame($hash, $this->engine->moveHash('ROCK', 'seed-value', 'round-1', 'user-1'));
        $this->assertTrue($this->engine->verifyMoveHash('rock', 'seed-value', 'round-1', 'user-1', $hash));
        $this->assertFalse($this->engine->verifyMoveHash('paper', 'seed-value', 'round-1', 'user-1', $hash));
        $this->assertFalse($this->engine->verifyMoveHash('rock', 'other-seed', 'round-1', 'user-1', $hash));
        $this->assertFalse($this->engine->verifyMoveHash('rock', 'seed-value', 'round-1', 'user-1', null));
    }
}
