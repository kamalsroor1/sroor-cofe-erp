<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class UpdateRolePermissionsAction
{
    /**
     * Update permissions assigned to a role and invalidate Spatie cache
     */
    public function execute(int $roleId, array $permissions): Role
    {
        $role = Role::findOrFail($roleId);

        if ($role->name === 'admin') {
            $role->syncPermissions(Permission::all());
        } else {
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->load('permissions');
    }
}
