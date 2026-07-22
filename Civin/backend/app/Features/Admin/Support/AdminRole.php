<?php

namespace App\Features\Admin\Support;

final class AdminRole
{
    public const SUPER_ADMIN = 'Super Admin';

    public const MODERATOR = 'Moderator';

    public const FINANCE_ADMIN = 'Finance Admin';

    public const SUPPORT_ADMIN = 'Support Admin';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::SUPER_ADMIN,
            self::MODERATOR,
            self::FINANCE_ADMIN,
            self::SUPPORT_ADMIN,
        ];
    }

    /** @return array<string, list<string>> */
    public static function permissionMap(): array
    {
        return [
            self::SUPER_ADMIN => AdminPermission::all(),
            self::MODERATOR => [
                AdminPermission::MANAGE_USERS,
                AdminPermission::MANAGE_LIVE_ROOMS,
                AdminPermission::REVIEW_REPORTS,
                AdminPermission::MODERATE_CHAT,
            ],
            self::FINANCE_ADMIN => [
                AdminPermission::MANAGE_WALLETS,
                AdminPermission::APPROVE_WITHDRAWALS,
                AdminPermission::MANAGE_GIFTS,
                AdminPermission::MANAGE_VIP,
            ],
            self::SUPPORT_ADMIN => [
                AdminPermission::MANAGE_USERS,
                AdminPermission::REVIEW_REPORTS,
                AdminPermission::MANAGE_AGENCIES,
            ],
        ];
    }
}
