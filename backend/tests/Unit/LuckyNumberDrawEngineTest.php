<?php

namespace Tests\Unit;

use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberConfig;
use App\Features\Games\Plugins\LuckyNumber\Support\LuckyNumberDrawEngine;
use App\Features\Games\Services\GameRng;
use PHPUnit\Framework\TestCase;

class LuckyNumberDrawEngineTest extends TestCase
{
    private function engine(?GameRng $rng = null): LuckyNumberDrawEngine
    {
        return new LuckyNumberDrawEngine($rng ?? new class implements GameRng
        {
            public function int(int $min, int $max): int
            {
                return $min;
            }

            public function sampleUnique(int $min, int $max, int $count): array
            {
                return range($min, min($max, $min + $count - 1));
            }
        });
    }

    public function test_settle_exact_single_number_match_applies_multiplier(): void
    {
        $result = $this->engine()->settle(
            picked: [5],
            winning: [5],
            betAmount: 50,
            multipliers: ['exact' => 9, 'single' => 9],
            gameMode: LuckyNumberConfig::MODE_SINGLE_NUMBER,
        );

        $this->assertSame(1, $result['match_count']);
        $this->assertSame([5], $result['matched']);
        $this->assertSame(9.0, $result['multiplier']);
        $this->assertSame(450, $result['reward_coins']);
        $this->assertTrue($result['is_win']);
        $this->assertFalse($result['is_jackpot']);
    }

    public function test_settle_no_match_yields_zero_reward(): void
    {
        $result = $this->engine()->settle(
            picked: [3],
            winning: [7],
            betAmount: 50,
            multipliers: ['exact' => 9],
            gameMode: LuckyNumberConfig::MODE_SINGLE_NUMBER,
        );

        $this->assertSame(0, $result['match_count']);
        $this->assertSame([], $result['matched']);
        $this->assertSame(0.0, $result['multiplier']);
        $this->assertSame(0, $result['reward_coins']);
        $this->assertFalse($result['is_win']);
        $this->assertFalse($result['is_jackpot']);
    }

    public function test_settle_partial_multi_match_uses_match_key(): void
    {
        $result = $this->engine()->settle(
            picked: [1, 2, 3],
            winning: [1, 2, 9],
            betAmount: 100,
            multipliers: [
                'match_1' => 1.5,
                'match_2' => 3,
                'match_3' => 8,
                'exact' => 50,
            ],
            gameMode: LuckyNumberConfig::MODE_MULTIPLE_NUMBERS,
        );

        $this->assertSame(2, $result['match_count']);
        $this->assertSame([1, 2], $result['matched']);
        $this->assertSame(3.0, $result['multiplier']);
        $this->assertSame(300, $result['reward_coins']);
        $this->assertTrue($result['is_win']);
        $this->assertFalse($result['is_jackpot']);
    }

    public function test_settle_progressive_full_match_flags_jackpot_without_coin_payout(): void
    {
        $result = $this->engine()->settle(
            picked: [1, 2, 3],
            winning: [1, 2, 3],
            betAmount: 100,
            multipliers: ['exact' => 50, 'match_3' => 8],
            gameMode: LuckyNumberConfig::MODE_PROGRESSIVE_JACKPOT,
        );

        $this->assertSame(3, $result['match_count']);
        $this->assertTrue($result['is_jackpot']);
        $this->assertTrue($result['is_win']);
        // Progressive jackpot coins come from the pool in the service layer.
        $this->assertSame(0, $result['reward_coins']);
    }

    public function test_settle_applies_vip_multiplier_when_requested(): void
    {
        $result = $this->engine()->settle(
            picked: [5],
            winning: [5],
            betAmount: 40,
            multipliers: ['exact' => 5],
            vipMultiplier: 1.5,
            applyVip: true,
            gameMode: LuckyNumberConfig::MODE_VIP_LUCKY_NUMBERS,
        );

        $this->assertSame(7.5, $result['multiplier']);
        $this->assertSame(300, $result['reward_coins']);
        $this->assertTrue($result['is_win']);
        $this->assertFalse($result['is_jackpot']);
    }

    public function test_draw_numbers_uses_rng_index_into_pool(): void
    {
        $rng = new class implements GameRng
        {
            public function int(int $min, int $max): int
            {
                return 4;
            }

            public function sampleUnique(int $min, int $max, int $count): array
            {
                return [5];
            }
        };

        $drawn = $this->engine($rng)->drawNumbers(1, 10, 1, 'seed-test');

        $this->assertSame([5], $drawn['winning_numbers']);
        $this->assertSame([4], $drawn['rolls']);
        $this->assertArrayHasKey('server_seed_hash', $drawn['audit']);
    }

    public function test_pick_numbers_delegates_to_sample_unique(): void
    {
        $picked = $this->engine()->pickNumbers(1, 10, 3);

        $this->assertSame([1, 2, 3], $picked);
    }
}
