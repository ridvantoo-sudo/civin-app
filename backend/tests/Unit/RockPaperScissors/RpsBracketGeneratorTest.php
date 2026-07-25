<?php

namespace Tests\Unit\RockPaperScissors;

use App\Features\Games\Plugins\RockPaperScissors\Models\RpsBracket;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsTournament;
use App\Features\Games\Plugins\RockPaperScissors\Support\RpsBracketGenerator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RpsBracketGeneratorTest extends TestCase
{
    private RpsBracketGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new RpsBracketGenerator;
    }

    /** @return list<string> */
    private function players(int $count): array
    {
        return array_map(static fn (int $i): string => sprintf('player-%02d', $i), range(1, $count));
    }

    public function test_single_elimination_builds_a_full_tree(): void
    {
        $nodes = $this->generator->generate(RpsTournament::FORMAT_SINGLE_ELIMINATION, $this->players(8), 'seed');

        // 4 + 2 + 1 nodes for an eight-player bracket.
        $this->assertCount(7, $nodes);

        $firstRound = array_values(array_filter($nodes, static fn (array $n): bool => $n['round_index'] === 0));
        $this->assertCount(4, $firstRound);
        foreach ($firstRound as $node) {
            $this->assertNotNull($node['player_one_id']);
            $this->assertNotNull($node['player_two_id']);
            $this->assertSame(RpsBracket::STATUS_READY, $node['status']);
        }

        $final = array_values(array_filter($nodes, static fn (array $n): bool => $n['next_key'] === null));
        $this->assertCount(1, $final);
        $this->assertSame(RpsBracket::STATUS_PENDING, $final[0]['status']);
    }

    public function test_single_elimination_gives_byes_when_the_field_is_not_a_power_of_two(): void
    {
        $nodes = $this->generator->generate(RpsTournament::FORMAT_SINGLE_ELIMINATION, $this->players(5), 'seed');

        $byes = array_values(array_filter($nodes, static fn (array $n): bool => $n['is_bye'] === true));

        // Five players in an eight-slot bracket: three walk over, two play.
        $this->assertCount(3, $byes);
        foreach ($byes as $bye) {
            $this->assertSame(RpsBracket::STATUS_BYE, $bye['status']);
            $this->assertNotNull($bye['winner_id']);
        }

        $empty = array_filter(
            $nodes,
            static fn (array $n): bool => $n['round_index'] === 0
                && $n['player_one_id'] === null
                && $n['player_two_id'] === null,
        );
        $this->assertSame([], $empty, 'Seeding must never leave an unplayable opening node.');
    }

    public function test_generation_is_deterministic_for_a_given_seed(): void
    {
        $players = $this->players(6);

        $this->assertSame(
            $this->generator->generate(RpsTournament::FORMAT_SINGLE_ELIMINATION, $players, 'seed-a'),
            $this->generator->generate(RpsTournament::FORMAT_SINGLE_ELIMINATION, array_reverse($players), 'seed-a'),
        );

        $this->assertNotSame(
            $this->generator->seedOrder($players, 'seed-a'),
            $this->generator->seedOrder($players, 'seed-b'),
        );
    }

    public function test_double_elimination_adds_a_losers_bracket_and_a_grand_final(): void
    {
        $nodes = $this->generator->generate(RpsTournament::FORMAT_DOUBLE_ELIMINATION, $this->players(8), 'seed');

        $types = array_count_values(array_column($nodes, 'bracket_type'));

        $this->assertSame(7, $types[RpsBracket::TYPE_WINNERS]);
        $this->assertArrayHasKey(RpsBracket::TYPE_LOSERS, $types);
        $this->assertSame(1, $types[RpsBracket::TYPE_FINAL]);

        $firstRoundWinners = array_values(array_filter(
            $nodes,
            static fn (array $n): bool => $n['bracket_type'] === RpsBracket::TYPE_WINNERS && $n['round_index'] === 0,
        ));
        foreach ($firstRoundWinners as $node) {
            $this->assertNotNull($node['next_loser_key'], 'Winners-bracket losers must drop somewhere.');
        }

        $keys = array_column($nodes, 'key');
        foreach ($nodes as $node) {
            foreach (['next_key', 'next_loser_key'] as $link) {
                if ($node[$link] !== null) {
                    $this->assertContains($node[$link], $keys, sprintf('Dangling %s on %s', $link, $node['key']));
                }
            }
        }
    }

    public function test_round_robin_pairs_every_player_exactly_once(): void
    {
        $players = $this->players(5);
        $nodes = $this->generator->generate(RpsTournament::FORMAT_ROUND_ROBIN, $players, 'seed');

        // Five players => C(5,2) = 10 unique pairings.
        $this->assertCount(10, $nodes);

        $pairs = [];
        foreach ($nodes as $node) {
            $this->assertNotNull($node['player_one_id']);
            $this->assertNotNull($node['player_two_id']);
            $pair = [$node['player_one_id'], $node['player_two_id']];
            sort($pair);
            $pairs[] = implode('|', $pair);
        }

        $this->assertSame(count($pairs), count(array_unique($pairs)));
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->generator->generate('battle_royale', $this->players(4), 'seed');
    }

    public function test_a_tournament_needs_at_least_two_players(): void
    {
        $this->expectException(ValidationException::class);

        $this->generator->generate(RpsTournament::FORMAT_SINGLE_ELIMINATION, ['solo'], 'seed');
    }
}
