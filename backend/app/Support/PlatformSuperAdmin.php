<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Single source of truth for "is this user a platform super admin".
 *
 * Platform rights come only from the central identity: the `super_admin` role
 * resolved in the central DB. Tenant DB data (roles inside a tenant database)
 * must never confer platform rights, so the check fails while tenancy is
 * initialized. No phone/email allowlists.
 */
final class PlatformSuperAdmin
{
    public static function check(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            return false;
        }

        return $user->hasRole('super_admin');
    }
}
