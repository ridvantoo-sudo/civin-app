<?php

namespace App\Features\Security\Support;

/**
 * Protected platform surfaces. A surface is discovered from the event's
 * namespace, so new Civin modules are covered without touching this list.
 */
final class SecuritySurface
{
    public const AUTH = 'auth';

    public const LIVE = 'live';

    public const VOICE = 'voice';

    public const GAMES = 'games';

    public const WALLET = 'wallet';

    public const PK = 'pk';

    public const GIFTS = 'gifts';

    public const RANKING = 'ranking';

    public const VIP = 'vip';

    public const AGENCY = 'agency';

    public const MARKETPLACE = 'marketplace';

    public const CHAT = 'chat';

    public const DEVICE = 'device';

    public const PLATFORM = 'platform';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::AUTH,
            self::LIVE,
            self::VOICE,
            self::GAMES,
            self::WALLET,
            self::PK,
            self::GIFTS,
            self::RANKING,
            self::VIP,
            self::AGENCY,
            self::MARKETPLACE,
            self::CHAT,
            self::DEVICE,
            self::PLATFORM,
        ];
    }

    /**
     * Maps a fully qualified event class onto a surface. Unknown namespaces
     * fall back to `platform` rather than being dropped.
     */
    public static function fromEventName(string $eventName): string
    {
        $map = [
            '\\Authentication\\' => self::AUTH,
            '\\Devices\\' => self::DEVICE,
            '\\LiveStreaming\\' => self::LIVE,
            '\\LiveShopping\\' => self::LIVE,
            '\\LiveChat\\' => self::CHAT,
            '\\VoicePkBattle\\' => self::VOICE,
            '\\VoiceRoom\\' => self::VOICE,
            '\\Games\\' => self::GAMES,
            '\\Wallet\\' => self::WALLET,
            '\\MultiPkBattle\\' => self::PK,
            '\\PkBattle\\' => self::PK,
            '\\Gifts\\' => self::GIFTS,
            '\\Ranking\\' => self::RANKING,
            '\\Svip\\' => self::VIP,
            '\\Vip\\' => self::VIP,
            '\\Agency\\' => self::AGENCY,
            '\\Marketplace\\' => self::MARKETPLACE,
            '\\CoinSeller\\' => self::MARKETPLACE,
        ];

        foreach ($map as $needle => $surface) {
            if (str_contains($eventName, $needle)) {
                return $surface;
            }
        }

        return self::PLATFORM;
    }
}
