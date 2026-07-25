<?php

namespace Tests\Feature\Security;

use App\Features\Security\DTO\DetectedSignal;
use App\Features\Security\Models\SecurityRule;
use App\Features\Security\Rules\RuleProvider;
use App\Features\Security\Rules\SecurityRuleEngine;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecuritySeverity;
use App\Features\Security\Support\SecuritySignal;
use App\Features\Security\Support\SecuritySurface;

final class SecurityRuleEngineTest extends SecurityTestCase
{
    public function test_default_rules_ship_with_the_platform(): void
    {
        $rules = app(RuleProvider::class)->all();

        $this->assertNotEmpty($rules);
        $this->assertNotNull(app(RuleProvider::class)->find('wallet.fraud'));
    }

    public function test_a_signal_below_the_threshold_does_not_match(): void
    {
        $matches = app(SecurityRuleEngine::class)->evaluate([
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 10.0),
        ]);

        $this->assertSame([], $matches);
        $this->assertSame(SecurityAction::ALLOW, app(SecurityRuleEngine::class)->resolveAction($matches));
    }

    public function test_the_strongest_action_among_matches_wins(): void
    {
        $engine = app(SecurityRuleEngine::class);

        $matches = $engine->evaluate([
            $this->signal(SecuritySignal::EMULATOR, SecuritySeverity::MEDIUM, 90.0),
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 90.0),
        ]);

        $this->assertSame(SecurityAction::SUSPEND, $engine->resolveAction($matches));
    }

    public function test_matches_are_ordered_by_priority(): void
    {
        $matches = app(SecurityRuleEngine::class)->evaluate([
            $this->signal(SecuritySignal::EMULATOR, SecuritySeverity::MEDIUM, 90.0),
            $this->signal(SecuritySignal::REPLAY_ATTACK, SecuritySeverity::CRITICAL, 90.0),
        ]);

        $priorities = array_map(static fn ($match): int => $match->priority, $matches);
        $sorted = $priorities;
        sort($sorted);

        $this->assertSame($sorted, $priorities);
        $this->assertSame('request.replay', $matches[0]->ruleKey);
    }

    public function test_a_database_row_overrides_the_configured_default(): void
    {
        SecurityRule::query()->create([
            'key' => 'wallet.fraud',
            'name' => 'Wallet fraud (relaxed)',
            'signal' => SecuritySignal::WALLET_FRAUD,
            'severity' => SecuritySeverity::HIGH,
            'action' => SecurityAction::WARN,
            'threshold' => 90.0,
            'priority' => 500,
            'is_enabled' => true,
        ]);

        $engine = app(SecurityRuleEngine::class);

        $matches = $engine->evaluate([
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 95.0),
        ]);

        $this->assertSame(SecurityAction::WARN, $engine->resolveAction($matches));
    }

    public function test_a_rule_can_be_disabled_without_touching_code(): void
    {
        SecurityRule::query()->create([
            'key' => 'wallet.fraud',
            'name' => 'Wallet fraud',
            'signal' => SecuritySignal::WALLET_FRAUD,
            'severity' => SecuritySeverity::CRITICAL,
            'action' => SecurityAction::SUSPEND,
            'threshold' => 65.0,
            'priority' => 15,
            'is_enabled' => false,
        ]);

        $matches = app(SecurityRuleEngine::class)->evaluate([
            $this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 99.0),
        ]);

        $this->assertSame([], $matches);
    }

    public function test_rule_changes_hot_reload_without_a_restart(): void
    {
        $engine = app(SecurityRuleEngine::class);
        $signals = [$this->signal(SecuritySignal::WALLET_FRAUD, SecuritySeverity::CRITICAL, 99.0)];

        $this->assertSame(SecurityAction::SUSPEND, $engine->resolveAction($engine->evaluate($signals)));

        $rule = SecurityRule::query()->create([
            'key' => 'wallet.fraud',
            'name' => 'Wallet fraud',
            'signal' => SecuritySignal::WALLET_FRAUD,
            'severity' => SecuritySeverity::CRITICAL,
            'action' => SecurityAction::BAN,
            'threshold' => 65.0,
            'priority' => 15,
            'is_enabled' => true,
        ]);

        $this->assertSame(SecurityAction::BAN, $engine->resolveAction($engine->evaluate($signals)));

        $rule->update(['is_enabled' => false]);

        $this->assertSame(SecurityAction::ALLOW, $engine->resolveAction($engine->evaluate($signals)));
    }

    public function test_conditions_scope_a_rule_to_part_of_the_platform(): void
    {
        SecurityRule::query()->create([
            'key' => 'tests.scoped',
            'name' => 'Wallet-only automation rule',
            'signal' => SecuritySignal::AUTO_CLICKER,
            'severity' => SecuritySeverity::HIGH,
            'action' => SecurityAction::BAN,
            'threshold' => 50.0,
            'priority' => 1,
            'is_enabled' => true,
            'conditions' => ['surface' => SecuritySurface::WALLET],
        ]);

        $engine = app(SecurityRuleEngine::class);

        $gameMatches = $engine->evaluate([
            $this->signal(SecuritySignal::AUTO_CLICKER, SecuritySeverity::HIGH, 90.0, SecuritySurface::GAMES),
        ]);
        $walletMatches = $engine->evaluate([
            $this->signal(SecuritySignal::AUTO_CLICKER, SecuritySeverity::HIGH, 90.0, SecuritySurface::WALLET),
        ]);

        $this->assertNotContains('tests.scoped', array_map(static fn ($m): string => $m->ruleKey, $gameMatches));
        $this->assertContains('tests.scoped', array_map(static fn ($m): string => $m->ruleKey, $walletMatches));
    }

    public function test_a_rule_may_raise_but_never_lower_observed_severity(): void
    {
        SecurityRule::query()->create([
            'key' => 'tests.severity',
            'name' => 'Low severity rule',
            'signal' => 'tests.signal',
            'severity' => SecuritySeverity::LOW,
            'action' => SecurityAction::WARN,
            'threshold' => 10.0,
            'priority' => 1,
            'is_enabled' => true,
        ]);

        $matches = app(SecurityRuleEngine::class)->evaluate([
            $this->signal('tests.signal', SecuritySeverity::CRITICAL, 80.0),
        ]);

        $this->assertSame(SecuritySeverity::CRITICAL, $matches[0]->severity);
    }

    public function test_a_new_rule_needs_no_change_to_the_rule_engine(): void
    {
        SecurityRule::query()->create([
            'key' => 'tests.future_detector',
            'name' => 'Future detector',
            'signal' => 'tests.future_signal',
            'severity' => SecuritySeverity::HIGH,
            'action' => SecurityAction::SUSPEND,
            'threshold' => 60.0,
            'priority' => 5,
            'is_enabled' => true,
        ]);

        $engine = app(SecurityRuleEngine::class);
        $matches = $engine->evaluate([
            $this->signal('tests.future_signal', SecuritySeverity::HIGH, 75.0),
        ]);

        $this->assertSame(SecurityAction::SUSPEND, $engine->resolveAction($matches));
    }

    private function signal(
        string $signal,
        string $severity,
        float $confidence,
        ?string $surface = null,
    ): DetectedSignal {
        return DetectedSignal::make(
            signal: $signal,
            severity: $severity,
            confidence: $confidence,
            title: 'test signal',
            surface: $surface,
        );
    }
}
