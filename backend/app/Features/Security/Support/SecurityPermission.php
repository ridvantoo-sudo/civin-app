<?php

namespace App\Features\Security\Support;

use App\Features\Admin\Support\AdminRole;
use App\Features\Users\Models\User;

/**
 * Security-only permissions, declared here rather than in AdminPermission so
 * Phase 12 does not modify the existing Admin module.
 *
 * Access resolves to: explicit Spatie permission → super admin flag → role.
 */
final class SecurityPermission
{
    public const VIEW_SECURITY = 'view security';

    public const MANAGE_SECURITY_RULES = 'manage security rules';

    public const REVIEW_SECURITY_CASES = 'review security cases';

    public const MANAGE_SECURITY_BANS = 'manage security bans';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_SECURITY,
            self::MANAGE_SECURITY_RULES,
            self::REVIEW_SECURITY_CASES,
            self::MANAGE_SECURITY_BANS,
        ];
    }

    /**
     * Roles that implicitly hold a security permission when the permission has
     * not been seeded into the Spatie tables.
     *
     * @return array<string, list<string>>
     */
    public static function roleFallback(): array
    {
        return [
            self::VIEW_SECURITY => [AdminRole::SUPER_ADMIN, AdminRole::MODERATOR, AdminRole::SUPPORT_ADMIN],
            self::MANAGE_SECURITY_RULES => [AdminRole::SUPER_ADMIN],
            self::REVIEW_SECURITY_CASES => [AdminRole::SUPER_ADMIN, AdminRole::MODERATOR],
            self::MANAGE_SECURITY_BANS => [AdminRole::SUPER_ADMIN, AdminRole::MODERATOR],
        ];
    }

    public static function allows(?User $user, string $permission): bool
    {
        if ($user === null || $user->isBanned() || $user->is_guest) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        if ($user->can($permission)) {
            return true;
        }

        $roles = self::roleFallback()[$permission] ?? [];

        return $roles !== [] && $user->hasAnyRole($roles);
    }
}
