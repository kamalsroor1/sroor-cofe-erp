<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CentralPermission;
use App\Models\CentralUser;

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
 *  - check(): "is a platform super admin": an active CentralUser holding the central-guard
 *    role `super_admin`. IDEN-1.4 removed the legacy App\Models\User branch: a row of a
 *    `users` table (tenant or central) is never a platform operator, whatever its roles.
 */
final class PlatformSuperAdmin
{
    public static function check(mixed $user): bool
    {
        if (self::tenancyInitialized()) {
            return false;
        }

        return $user instanceof CentralUser
            && $user->is_active
            && $user->hasRole(CentralPermission::ROLE_SUPER_ADMIN, CentralPermission::GUARD);
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

    private static function tenancyInitialized(): bool
    {
        return function_exists('tenancy') && tenancy()->initialized;
    }
}
