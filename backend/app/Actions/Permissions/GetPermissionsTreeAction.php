<?php

declare(strict_types=1);

namespace App\Actions\Permissions;

use App\Models\User;
use Spatie\Permission\Models\Role;

final class GetPermissionsTreeAction
{
    /**
     * Get system permissions catalog and user-specific permissions
     */
    public function execute(User $user): array
    {
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $userRoles = $user->getRoleNames()->toArray();
        $isAdmin = $user->hasRole('admin');

        $modules = [
            'sales' => [
                'title' => __('roles.modules.sales'),
                'icon' => 'shopping-cart',
                'permissions' => [
                    'pos.access',
                    'pos.price.below_min',
                    'pos.discount.override',
                    'invoices.view',
                    'invoices.create',
                    'invoices.edit',
                    'invoices.cancel',
                    'invoices.delete',
                    'invoices.discount',
                ],
            ],
            'inventory' => [
                'title' => __('roles.modules.inventory'),
                'icon' => 'package',
                'permissions' => [
                    'items.view',
                    'items.create',
                    'items.edit',
                    'items.delete',
                    'items.view_cost',
                    'inventory.adjust',
                ],
            ],
            'purchases' => [
                'title' => __('roles.modules.purchases'),
                'icon' => 'truck',
                'permissions' => [
                    'purchases.view',
                    'purchases.create',
                    'purchases.delete',
                ],
            ],
            'customers' => [
                'title' => __('roles.modules.customers'),
                'icon' => 'users',
                'permissions' => [
                    'customers.manage',
                    'customers.statement',
                    'customers.delete',
                ],
            ],
            'suppliers' => [
                'title' => __('roles.modules.suppliers'),
                'icon' => 'warehouse',
                'permissions' => [
                    'suppliers.manage',
                    'suppliers.statement',
                ],
            ],
            'expenses' => [
                'title' => __('roles.modules.expenses'),
                'icon' => 'banknote',
                'permissions' => [
                    'expenses.manage',
                ],
            ],
            'returns' => [
                'title' => __('roles.modules.returns'),
                'icon' => 'rotate-ccw',
                'permissions' => [
                    'returns.manage',
                    'returns.delete',
                ],
            ],
            'reports' => [
                'title' => __('roles.modules.reports'),
                'icon' => 'bar-chart3',
                'permissions' => [
                    'reports.view',
                ],
            ],
            'stores' => [
                'title' => __('roles.modules.stores'),
                'icon' => 'store',
                'permissions' => [
                    'stores.manage',
                    'stores.view_all',
                    'transfers.view',
                    'transfers.create',
                ],
            ],
            'daily_journal' => [
                'title' => __('roles.modules.daily_journal'),
                'icon' => 'wallet',
                'permissions' => [
                    'daily_journal.view',
                    'daily_journal.close_shift',
                    'daily_journal.manage',
                ],
            ],
            'administration' => [
                'title' => __('roles.modules.administration'),
                'icon' => 'shield-check',
                'permissions' => [
                    'roles.manage',
                    'settings.manage',
                    'logs.view',
                    'trash.access',
                ],
            ],
        ];

        // Labels come from lang/{ar,en}/permissions.php; only seeded names are listed (QA-2 parity).
        foreach ($modules as $key => $module) {
            $modules[$key]['permissions'] = collect($module['permissions'])
                ->mapWithKeys(fn (string $name): array => [$name => __('permissions.'.$name)])
                ->all();
        }

        $rolesData = [];
        if ($isAdmin || $user->can('roles.manage')) {
            $roles = Role::with('permissions')->get();
            $rolesData = $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'permissions' => $r->permissions->pluck('name')->toArray(),
            ])->toArray();
        }

        return [
            'user_permissions' => $userPermissions,
            'user_roles' => $userRoles,
            'is_admin' => $isAdmin,
            'permission_modules' => $modules,
            'roles' => $rolesData,
        ];
    }
}
