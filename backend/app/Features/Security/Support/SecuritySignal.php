<?php

namespace App\Features\Security\Support;

/**
 * Canonical signal identifiers emitted by scanners and matched by rules.
 *
 * Scanners are free to emit signals that are not listed here; the rule engine
 * matches on the raw string. These constants exist so first-party detectors,
 * default rules and dashboards agree on spelling.
 */
final class SecuritySignal
{
    // Automation / behaviour
    public const AUTO_CLICKER = 'automation.auto_clicker';

    public const MACRO_BEHAVIOR = 'automation.macro';

    public const PERFECT_TIMING = 'automation.perfect_timing';

    public const IMPOSSIBLE_REACTION = 'automation.impossible_reaction';

    public const SPEED_HACK = 'automation.speed_hack';

    public const BOT_PLAY = 'bot.play_pattern';

    // Sessions / accounts
    public const MULTIPLE_SESSIONS = 'session.multiple_concurrent';

    public const ACCOUNT_SHARING = 'account.sharing';

    public const MASS_ACCOUNT_CREATION = 'account.mass_creation';

    public const SMURF_ACCOUNT = 'account.smurf';

    // Device
    public const EMULATOR = 'device.emulator';

    public const DEVICE_INTEGRITY = 'device.integrity_compromised';

    public const DEVICE_CLONED = 'device.cloned';

    // Network
    public const ANONYMIZER = 'network.anonymizer';

    public const IMPOSSIBLE_TRAVEL = 'network.impossible_travel';

    public const NETWORK_ANOMALY = 'network.anomaly';

    // Requests
    public const REPLAY_ATTACK = 'request.replay';

    public const TAMPERED_REQUEST = 'request.tampered';

    public const DUPLICATE_SETTLEMENT = 'request.duplicate_settlement';

    public const API_ABUSE = 'api.abuse';

    // Games
    public const IMPOSSIBLE_OUTCOME = 'game.impossible_outcome';

    public const RNG_MANIPULATION = 'game.rng_manipulation';

    public const IMPOSSIBLE_STREAK = 'game.impossible_streak';

    public const IMPOSSIBLE_RTP = 'game.impossible_rtp';

    public const IMPOSSIBLE_ACCURACY = 'game.impossible_accuracy';

    // Wallet
    public const WALLET_FRAUD = 'wallet.fraud';

    public const WALLET_ABUSE = 'wallet.abuse';

    public const RAPID_TRANSFERS = 'wallet.rapid_transfers';

    public const ABNORMAL_BALANCE = 'wallet.abnormal_balance';

    // Farming
    public const REWARD_FARMING = 'farming.rewards';

    public const REFERRAL_ABUSE = 'farming.referral_abuse';

    // Live
    public const FAKE_ENGAGEMENT = 'live.fake_engagement';

    public const ARTIFICIAL_RANKING = 'live.artificial_ranking';
}
