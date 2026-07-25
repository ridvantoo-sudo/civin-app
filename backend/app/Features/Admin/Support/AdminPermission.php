<?php

namespace App\Features\Admin\Support;

final class AdminPermission
{
    public const MANAGE_USERS = 'manage users';

    public const MANAGE_LIVE_ROOMS = 'manage live rooms';

    public const MANAGE_WALLETS = 'manage wallets';

    public const APPROVE_WITHDRAWALS = 'approve withdrawals';

    public const APPROVE_OFFLINE_RECHARGES = 'approve offline recharges';

    public const MANAGE_GIFTS = 'manage gifts';

    public const MANAGE_VIP = 'manage vip';

    public const MANAGE_SVIP = 'manage svip';

    public const MANAGE_ADS = 'manage ads';

    public const MANAGE_MARKETPLACE = 'manage marketplace';

    public const MANAGE_AGENCIES = 'manage agencies';

    public const MANAGE_COIN_SELLERS = 'manage coin sellers';

    public const REVIEW_KYC = 'review kyc';

    public const REVIEW_REPORTS = 'review reports';

    public const MODERATE_CHAT = 'moderate chat';

    public const MANAGE_GAMES = 'manage games';

    public const VIEW_ANALYTICS = 'view analytics';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::MANAGE_USERS,
            self::MANAGE_LIVE_ROOMS,
            self::MANAGE_WALLETS,
            self::APPROVE_WITHDRAWALS,
            self::APPROVE_OFFLINE_RECHARGES,
            self::MANAGE_GIFTS,
            self::MANAGE_VIP,
            self::MANAGE_SVIP,
            self::MANAGE_ADS,
            self::MANAGE_MARKETPLACE,
            self::MANAGE_AGENCIES,
            self::MANAGE_COIN_SELLERS,
            self::REVIEW_KYC,
            self::REVIEW_REPORTS,
            self::MODERATE_CHAT,
            self::MANAGE_GAMES,
            self::VIEW_ANALYTICS,
        ];
    }
}
