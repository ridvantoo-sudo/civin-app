<?php

namespace App\Features\Admin\Support;

final class AdminPermission
{
    public const MANAGE_USERS = 'manage users';

    public const MANAGE_LIVE_ROOMS = 'manage live rooms';

    public const MANAGE_WALLETS = 'manage wallets';

    public const APPROVE_WITHDRAWALS = 'approve withdrawals';

    public const MANAGE_GIFTS = 'manage gifts';

    public const MANAGE_VIP = 'manage vip';

    public const MANAGE_AGENCIES = 'manage agencies';

    public const REVIEW_REPORTS = 'review reports';

    public const MODERATE_CHAT = 'moderate chat';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::MANAGE_USERS,
            self::MANAGE_LIVE_ROOMS,
            self::MANAGE_WALLETS,
            self::APPROVE_WITHDRAWALS,
            self::MANAGE_GIFTS,
            self::MANAGE_VIP,
            self::MANAGE_AGENCIES,
            self::REVIEW_REPORTS,
            self::MODERATE_CHAT,
        ];
    }
}
