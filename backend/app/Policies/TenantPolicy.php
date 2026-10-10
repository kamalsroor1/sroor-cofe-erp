<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CentralPermission;
use App\Models\Tenant;
use App\Support\PlatformSuperAdmin;

/**
 * Tenants are a control-plane resource (IDEN-1.4): only an active App\Models\CentralUser
 * holding the matching CentralPermission (central guard, central context) may see or change
 * one. A tenant App\Models\User — whatever its roles, including a legacy `super_admin` /
 * `super_admin.access` or a store `admin` — is always denied here.
 *
 * The routes in routes/central.php authorize through `can:<CentralPermission>`; this policy
 * only keeps any future `can('update', $tenant)` call fail-closed and consistent with them.
 */
final class TenantPolicy
{
    public function viewAny(mixed $user): bool
    {
        return PlatformSuperAdmin::can($user, CentralPermission::TenantsView);
    }

    public function view(mixed $user, ?Tenant $tenant = null): bool
    {
        return PlatformSuperAdmin::can($user, CentralPermission::TenantsView);
    }

    public function create(mixed $user): bool
    {
        return PlatformSuperAdmin::can($user, CentralPermission::TenantsManage);
    }

    public function update(mixed $user, ?Tenant $tenant = null): bool
    {
        return PlatformSuperAdmin::can($user, CentralPermission::TenantsManage);
    }

    public function delete(mixed $user, ?Tenant $tenant = null): bool
    {
        return PlatformSuperAdmin::can($user, CentralPermission::TenantsManage);
    }
}
