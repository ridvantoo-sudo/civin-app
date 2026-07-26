<?php

use App\Features\Security\AntiCheat\Predictors\NullAnomalyPredictor;
use App\Features\Security\AntiCheat\Predictors\NullBehaviorPredictor;
use App\Features\Security\AntiCheat\Predictors\NullBotPredictor;
use App\Features\Security\AntiCheat\Predictors\NullFraudPredictor;
use App\Features\Security\Engine\BehaviorEngine;
use App\Features\Security\Engine\DeviceEngine;
use App\Features\Security\Engine\FraudEngine;
use App\Features\Security\Engine\GameSecurityEngine;
use App\Features\Security\Engine\LiveSecurityEngine;
use App\Features\Security\Engine\NetworkEngine;
use App\Features\Security\Engine\WalletSecurityEngine;
use App\Features\Security\Scanners\AccountAbuseScanner;
use App\Features\Security\Scanners\ApiAbuseScanner;
use App\Features\Security\Scanners\AutomationScanner;
use App\Features\Security\Scanners\BotBehaviorScanner;
use App\Features\Security\Scanners\DeviceIntegrityScanner;
use App\Features\Security\Scanners\FarmingScanner;
use App\Features\Security\Scanners\GameOutcomeScanner;
use App\Features\Security\Scanners\LiveAuthenticityScanner;
use App\Features\Security\Scanners\MultiSessionScanner;
use App\Features\Security\Scanners\NetworkAbuseScanner;
use App\Features\Security\Scanners\ReplayAttackScanner;
use App\Features\Security\Scanners\WalletFraudScanner;
use App\Features\Security\Support\SecurityAction;

return [
    /*
    | Redis / queue connection used for every security analysis job.
    | Workers should run: php artisan queue:work redis --queue=security
    | Nothing heavier than normalization ever runs on the source request path.
    */
    'queue' => env('SECURITY_QUEUE', 'security'),

    'queue_connection' => env('SECURITY_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'cache_store' => env('SECURITY_CACHE_STORE', env('CACHE_STORE', 'database')),

    /*
    | Master switch. When disabled the wildcard listener returns immediately so
    | the platform behaves exactly as it did before Phase 12.
    */
    'enabled' => filter_var(env('SECURITY_ENABLED', true), FILTER_VALIDATE_BOOL),

    /*
    | When false the engine still records events, scores and alerts but never
    | escalates beyond `warn`. Useful for shadow-deploying new detectors.
    */
    'enforcement_enabled' => filter_var(env('SECURITY_ENFORCEMENT_ENABLED', true), FILTER_VALIDATE_BOOL),

    /*
    | Event namespaces ignored by the wildcard collector (framework noise plus
    | our own events, which would otherwise cause an ingestion loop).
    */
    'ignored_event_prefixes' => [
        'Illuminate\\',
        'Laravel\\',
        'Filament\\',
        'Livewire\\',
        'eloquent.',
        'bootstrapped:',
        'composing:',
        'creating:',
        'created:',
        'updating:',
        'updated:',
        'saving:',
        'saved:',
        'deleting:',
        'deleted:',
        'retrieved:',
        'App\\Features\\Security\\',
        'App\\Features\\GameAnalytics\\',
    ],

    /*
    | Privacy: these keys are never persisted on security events. We keep only
    | the metadata required to prove or disprove abuse.
    */
    'redacted_payload_keys' => [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'secret',
        'api_key',
        'email',
        'phone',
        'phone_number',
        'firebase_uid',
        'device_token',
        'fcm_token',
        'push_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'latitude',
        'longitude',
        // Raw identifiers are dropped because the hashed forms are already
        // stored as first-class columns.
        'ip',
        'ip_address',
        'ipaddress',
        'device_id',
        'deviceid',
        'device_uuid',
        'deviceuuid',
        'session_id',
        'sessionid',
    ],

    /*
    | Raw IP addresses are hashed before storage unless explicitly allowed.
    | The salt must be set in production so hashes are not rainbow-tableable.
    */
    'privacy' => [
        'hash_ip_addresses' => filter_var(env('SECURITY_HASH_IPS', true), FILTER_VALIDATE_BOOL),
        'hash_salt' => env('SECURITY_HASH_SALT', env('APP_KEY', 'civin-security')),
        'store_user_agent' => filter_var(env('SECURITY_STORE_USER_AGENT', true), FILTER_VALIDATE_BOOL),
        'max_payload_keys' => 60,
    ],

    /*
    | Configurable retention windows, in days. PurgeSecurityDataJob honours them.
    */
    'retention' => [
        'events_days' => (int) env('SECURITY_RETENTION_EVENTS_DAYS', 90),
        'logs_days' => (int) env('SECURITY_RETENTION_LOGS_DAYS', 365),
        'alerts_days' => (int) env('SECURITY_RETENTION_ALERTS_DAYS', 180),
        'sessions_days' => (int) env('SECURITY_RETENTION_SESSIONS_DAYS', 60),
        'networks_days' => (int) env('SECURITY_RETENTION_NETWORKS_DAYS', 120),
    ],

    'realtime' => [
        'channel' => 'security.monitor',
        'broadcast_throttle_seconds' => (int) env('SECURITY_BROADCAST_THROTTLE', 5),
    ],

    /*
    | Score model. Every score is a 0-100 decimal. `risk`, `fraud` and `bot`
    | are "higher is worse"; `trust` and `security` are "higher is better".
    */
    'scores' => [
        'default_trust' => 70.0,
        'default_security' => 80.0,
        'decay_per_day' => 2.5,
        'max' => 100.0,
        'min' => 0.0,

        /*
        | A detection is evidence, not a conviction. Each signal contributes a
        | fraction of its weighted confidence, and one event's total is capped,
        | so escalation requires a pattern rather than a single bad reading.
        */
        'signal_weight' => 0.25,
        'max_event_delta' => 25.0,
    ],

    /*
    | Detector registry. Adding a scanner here is the ONLY change required to
    | extend detection — SecurityEngine itself stays closed for modification.
    */
    'scanners' => [
        AutomationScanner::class,
        BotBehaviorScanner::class,
        MultiSessionScanner::class,
        DeviceIntegrityScanner::class,
        NetworkAbuseScanner::class,
        ReplayAttackScanner::class,
        ApiAbuseScanner::class,
        GameOutcomeScanner::class,
        WalletFraudScanner::class,
        FarmingScanner::class,
        LiveAuthenticityScanner::class,
        AccountAbuseScanner::class,
    ],

    /*
    | Domain engines consulted by SecurityEngine, in order. Also open for
    | extension: append a class implementing SecurityAnalyzer.
    */
    'engines' => [
        BehaviorEngine::class,
        DeviceEngine::class,
        NetworkEngine::class,
        GameSecurityEngine::class,
        WalletSecurityEngine::class,
        LiveSecurityEngine::class,
        FraudEngine::class,
    ],

    /*
    | Machine-learning adapters. Swap any of these for a real model binding and
    | the engine picks it up without a code change.
    */
    'predictors' => [
        'fraud' => NullFraudPredictor::class,
        'behavior' => NullBehaviorPredictor::class,
        'bot' => NullBotPredictor::class,
        'anomaly' => NullAnomalyPredictor::class,
    ],

    /*
    | Detector tuning. Scanners read thresholds from here so operators can
    | retune detection without deploying code.
    */
    'thresholds' => [
        'automation' => [
            'min_samples' => 6,
            'interval_jitter_ms' => 45,
            'impossible_reaction_ms' => 120,
            'burst_actions_per_minute' => 120,
        ],
        'sessions' => [
            'max_concurrent' => 3,
            'max_devices_per_user' => 6,
            'max_users_per_device' => 4,
        ],
        'network' => [
            'max_users_per_ip' => 8,
            'max_countries_per_day' => 3,
        ],
        'game' => [
            'max_win_streak' => 12,
            'max_rtp' => 1.15,
            'min_rtp_samples' => 40,
            'max_quiz_accuracy' => 0.98,
            'min_quiz_samples' => 25,
            'dice_chi_square_samples' => 60,
        ],
        'wallet' => [
            'rapid_transfer_window_seconds' => 60,
            'rapid_transfer_count' => 10,
            'abnormal_balance_jump' => 1000000,
        ],
        'farming' => [
            'gift_loop_window_seconds' => 3600,
            'gift_loop_count' => 20,
            'signup_per_device_per_day' => 3,
            'signup_per_ip_per_day' => 10,
        ],
        'live' => [
            'viewer_join_leave_ratio' => 0.85,
            'min_viewer_samples' => 30,
            'bot_comment_similarity' => 0.9,
        ],
    ],

    /*
    | Default rule set. Rows in `security_rules` override these by `key`, which
    | is how enable/disable/priority/threshold/action hot-reload works.
    */
    'default_rules' => [
        [
            'key' => 'automation.auto_clicker',
            'name' => 'Auto clicker / macro cadence',
            'signal' => 'automation.auto_clicker',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::THROTTLE,
            'priority' => 100,
        ],
        [
            'key' => 'automation.impossible_reaction',
            'name' => 'Impossible reaction time',
            'signal' => 'automation.impossible_reaction',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::THROTTLE,
            'priority' => 105,
        ],
        [
            'key' => 'bot.play_pattern',
            'name' => 'Bot play pattern',
            'signal' => 'bot.play_pattern',
            'severity' => 'high',
            'threshold' => 75.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 110,
        ],
        [
            'key' => 'session.multiple_concurrent',
            'name' => 'Multiple simultaneous sessions',
            'signal' => 'session.multiple_concurrent',
            'severity' => 'medium',
            'threshold' => 60.0,
            'action' => SecurityAction::WARN,
            'priority' => 200,
        ],
        [
            'key' => 'device.emulator',
            'name' => 'Emulator usage',
            'signal' => 'device.emulator',
            'severity' => 'medium',
            'threshold' => 60.0,
            'action' => SecurityAction::WARN,
            'priority' => 210,
        ],
        [
            'key' => 'device.integrity_compromised',
            'name' => 'Rooted / jailbroken / tampered build',
            'signal' => 'device.integrity_compromised',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::SUSPEND,
            'priority' => 120,
        ],
        [
            'key' => 'device.cloned',
            'name' => 'Device cloning',
            'signal' => 'device.cloned',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 130,
        ],
        [
            'key' => 'network.anonymizer',
            'name' => 'VPN / proxy / TOR abuse',
            'signal' => 'network.anonymizer',
            'severity' => 'medium',
            'threshold' => 65.0,
            'action' => SecurityAction::WARN,
            'priority' => 220,
        ],
        [
            'key' => 'network.impossible_travel',
            'name' => 'Impossible travel / fake GPS',
            'signal' => 'network.impossible_travel',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 140,
        ],
        [
            'key' => 'request.replay',
            'name' => 'Replay attack / duplicate settlement',
            'signal' => 'request.replay',
            'severity' => 'critical',
            'threshold' => 60.0,
            'action' => SecurityAction::SUSPEND,
            'priority' => 10,
        ],
        [
            'key' => 'request.tampered',
            'name' => 'Tampered request signature',
            'signal' => 'request.tampered',
            'severity' => 'critical',
            'threshold' => 60.0,
            'action' => SecurityAction::SUSPEND,
            'priority' => 20,
        ],
        [
            'key' => 'api.abuse',
            'name' => 'API abuse / packet flood',
            'signal' => 'api.abuse',
            'severity' => 'medium',
            'threshold' => 65.0,
            'action' => SecurityAction::THROTTLE,
            'priority' => 230,
        ],
        [
            'key' => 'game.impossible_outcome',
            'name' => 'Impossible game outcome',
            'signal' => 'game.impossible_outcome',
            'severity' => 'critical',
            'threshold' => 65.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 30,
        ],
        [
            'key' => 'game.rng_manipulation',
            'name' => 'RNG manipulation attempt',
            'signal' => 'game.rng_manipulation',
            'severity' => 'critical',
            'threshold' => 65.0,
            'action' => SecurityAction::SUSPEND,
            'priority' => 25,
        ],
        [
            'key' => 'wallet.fraud',
            'name' => 'Recharge / withdrawal fraud',
            'signal' => 'wallet.fraud',
            'severity' => 'critical',
            'threshold' => 65.0,
            'action' => SecurityAction::SUSPEND,
            'priority' => 15,
        ],
        [
            'key' => 'wallet.abuse',
            'name' => 'Rapid transfers / abnormal balance',
            'signal' => 'wallet.abuse',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 150,
        ],
        [
            'key' => 'farming.rewards',
            'name' => 'Coin / gift / reward farming',
            'signal' => 'farming.rewards',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 160,
        ],
        [
            'key' => 'farming.referral_abuse',
            'name' => 'Referral abuse',
            'signal' => 'farming.referral_abuse',
            'severity' => 'medium',
            'threshold' => 65.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 240,
        ],
        [
            'key' => 'account.mass_creation',
            'name' => 'Mass account creation / smurfing',
            'signal' => 'account.mass_creation',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 170,
        ],
        [
            'key' => 'account.sharing',
            'name' => 'Account sharing',
            'signal' => 'account.sharing',
            'severity' => 'medium',
            'threshold' => 65.0,
            'action' => SecurityAction::WARN,
            'priority' => 250,
        ],
        [
            'key' => 'live.fake_engagement',
            'name' => 'Fake viewers / bot engagement',
            'signal' => 'live.fake_engagement',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 180,
        ],
        [
            'key' => 'live.artificial_ranking',
            'name' => 'Artificial ranking inflation',
            'signal' => 'live.artificial_ranking',
            'severity' => 'high',
            'threshold' => 70.0,
            'action' => SecurityAction::REVIEW,
            'priority' => 190,
        ],
    ],

    /*
    | Composite score thresholds that escalate independently of single signals.
    */
    'escalation' => [
        'risk_review' => 75.0,
        'risk_suspend' => 88.0,
        'risk_ban' => 96.0,
        'fraud_review' => 80.0,
        'bot_review' => 85.0,
    ],
];
