<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class GetRolesMatrixAction
{
    /**
     * Return roles with assigned permissions and structured permission modules
     */
    public function execute(?int $selectedRoleId = null): array
    {
        $isTenant = function_exists('tenant') && tenant();

        $query = Role::with('permissions');
        if ($isTenant) {
            $query->where('name', '!=', 'super_admin');
        }

        $roles = $query->get();

        $selectedRole = $selectedRoleId
            ? $roles->firstWhere('id', $selectedRoleId)
            : ($roles->firstWhere('name', 'cashier') ?: $roles->first());

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
                'icon' => 'receipt',
                'permissions' => [
                    'expenses.manage',
                ],
            ],
            'returns' => [
                'title' => __('roles.modules.returns'),
                'icon' => 'rotate-ccw',
                'permissions' => [
                    'returns.manage',
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
                'icon' => 'banknote',
                'permissions' => [
                    'daily_journal.view',
                    'daily_journal.close_shift',
                    'daily_journal.manage',
                ],
            ],
            'roles' => [
                'title' => __('roles.modules.roles'),
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

        return [
            'roles' => $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => in_array($r->name, ['admin', 'cashier', 'storekeeper', 'accountant'], true)
                    ? __('users.role_'.$r->name)
                    : $r->name,
                'permissions_count' => $r->permissions->count(),
                'permissions' => $r->permissions->pluck('name')->toArray(),
            ]),
            'selected_role' => $selectedRole ? [
                'id' => $selectedRole->id,
                'name' => $selectedRole->name,
                'permissions' => $selectedRole->permissions->pluck('name')->toArray(),
            ] : null,
            'permission_modules' => $modules,
        ];
    }
}
