<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CentralPermission;
use App\Models\CentralUser;
use App\Models\User;

/**
 * Single source of truth for platform-operator rights.
 *
 * Platform rights come only from the central identity, resolved in the central DB, and
 * never while tenancy is initialized: tenant DB data must never confer platform rights.
 * No phone/email allowlists.
 *
 *  - can(): IDEN-1.2 granular check for an App\Models\CentralUser holding a
 *    CentralPermission on the `central` guard. Anything else (null, a tenant User, a
 *    string, an unknown ability) is false, never a TypeError.
 *  - check(): "is a platform super admin" (role `super_admin`). Accepts a CentralUser
 *    (central guard) and, until IDEN-1.4, the legacy App\Models\User branch.
 */
final class PlatformSuperAdmin
{
    public static function check(mixed $user): bool
    {
        if (self::tenancyInitialized()) {
            return false;
        }

        if ($user instanceof CentralUser) {
            return $user->is_active
                && $user->hasRole(CentralPermission::ROLE_SUPER_ADMIN, CentralPermission::GUARD);
        }

        return self::legacyCheck($user);
    }

    /**
     * True only for an active CentralUser, in central context, holding $permission on the
     * `central` guard (directly or through a role).
     */
    public static function can(mixed $user, CentralPermission|string $permission): bool
    {
        if (! $user instanceof CentralUser || ! $user->is_active || self::tenancyInitialized()) {
            return false;
        }

        $ability = $permission instanceof CentralPermission ? $permission : CentralPermission::tryFrom($permission);

        if ($ability === null) {
            return false;
        }

        return $user->checkPermissionTo($ability->value, CentralPermission::GUARD);
    }

    /**
     * Phase 0 operator: an App\Models\User in the central `users` table holding the
     * `web`-guard `super_admin` role. Still used by the current SPA and /api/v1/super-admin/*.
     *
     * @deprecated removed in IDEN-1.4 (W2-B3)
     */
    private static function legacyCheck(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole('super_admin');
    }

    private static function tenancyInitialized(): bool
    {
        return function_exists('tenancy') && tenancy()->initialized;
    }
}
