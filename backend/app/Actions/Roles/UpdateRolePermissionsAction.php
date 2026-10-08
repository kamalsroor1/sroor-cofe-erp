<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class UpdateRolePermissionsAction
{
    /**
     * Update permissions assigned to a role and invalidate Spatie cache.
     *
     * super_admin role and super_admin.* permissions are central-only: the role is
     * never editable from a tenant (404) and those permissions are never synced.
     */
    public function execute(int $roleId, array $permissions): Role
    {
        $role = Role::where('name', '!=', 'super_admin')->findOrFail($roleId);

        DB::transaction(function () use ($role, $permissions): void {
            if ($role->name === 'admin') {
                $role->syncPermissions(Permission::where('name', 'not like', 'super_admin.%')->get());

                return;
            }

            $permissions = array_values(array_filter(
                $permissions,
                fn ($permission) => ! str_starts_with((string) $permission, 'super_admin.')
            ));

            $role->syncPermissions($permissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->load('permissions');
    }
}
