<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Exceptions\PermissionEscalationException;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Roles\RoleGrantGuard;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class UpdateRolePermissionsAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly RoleGrantGuard $roleGrantGuard
    ) {}

    /**
     * Update permissions assigned to a role and invalidate Spatie cache.
     *
     * super_admin role and super_admin.* permissions are central-only: the role is
     * never editable from a tenant (404) and those permissions are never synced.
     *
     * SEC-04: an actor without the `admin` role (a roles.manage delegate) may not edit the admin
     * role, may not edit a role they hold themselves, and may not grant a permission they do not
     * hold. Only ADDED permissions are checked, so a full list that keeps pre-existing permissions
     * the actor lacks is accepted, and removing permissions is always allowed. Checked on the
     * locked role row before anything is written (403).
     *
     * @param  list<string>  $permissions
     */
    public function execute(int $roleId, array $permissions, User $actor): Role
    {
        $added = [];
        $removed = [];

        DB::transaction(function () use ($roleId, $permissions, $actor, &$added, &$removed): void {
            $role = Role::where('name', '!=', 'super_admin')->whereKey($roleId)->lockForUpdate()->firstOrFail();

            $before = $role->permissions()->pluck('name')->all();

            if (! $actor->hasRole('admin')) {
                $this->guardNonAdminActor($actor, $role, $permissions, $before);
            }

            if ($role->name === 'admin') {
                $role->syncPermissions(Permission::where('name', 'not like', 'super_admin.%')->get());
            } else {
                $permissions = array_values(array_filter(
                    $permissions,
                    fn ($permission) => ! str_starts_with((string) $permission, 'super_admin.')
                ));

                $role->syncPermissions($permissions);
            }

            $after = $role->permissions()->pluck('name')->all();
            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findOrFail($roleId);

        if ($added !== [] || $removed !== []) {
            $this->activityLogService->log(
                module: 'roles',
                action: 'permissions_updated',
                description: __('roles.activity_permissions_updated', ['role' => $role->name, 'user' => $actor->name]),
                subject: $role,
                properties: ['role' => $role->name, 'added' => $added, 'removed' => $removed],
                userId: $actor->id
            );
        }

        return $role->load('permissions');
    }

    /**
     * @param  list<string>  $requested
     * @param  list<string>  $current
     */
    private function guardNonAdminActor(User $actor, Role $role, array $requested, array $current): void
    {
        if ($role->name === 'admin') {
            throw new PermissionEscalationException(__('roles.cannot_edit_admin_role'));
        }

        if ($actor->hasRole($role->name)) {
            throw new PermissionEscalationException(__('roles.cannot_edit_own_role'));
        }

        $this->roleGrantGuard->assertCanGrantPermissions($actor, array_values(array_diff($requested, $current)));
    }
}
