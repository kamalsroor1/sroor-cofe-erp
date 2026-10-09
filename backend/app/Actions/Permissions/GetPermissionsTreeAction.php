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
                'title' => 'المبيعات ونقاط البيع (POS)',
                'icon' => 'shopping-cart',
                'permissions' => [
                    'pos.access',
                    'invoices.view',
                    'invoices.create',
                    'invoices.edit',
                    'invoices.cancel',
                    'invoices.delete',
                    'invoices.discount',
                ],
            ],
            'inventory' => [
                'title' => 'الأصناف والمخزون وخامات البن',
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
                'title' => 'المشتريات والتوريدات',
                'icon' => 'truck',
                'permissions' => [
                    'purchases.view',
                    'purchases.create',
                    'purchases.delete',
                ],
            ],
            'customers' => [
                'title' => 'العملاء والتحصيل النقدي',
                'icon' => 'users',
                'permissions' => [
                    'customers.manage',
                    'customers.statement',
                ],
            ],
            'suppliers' => [
                'title' => 'الموردين وسندات السداد',
                'icon' => 'factory',
                'permissions' => [
                    'suppliers.manage',
                    'suppliers.statement',
                ],
            ],
            'expenses' => [
                'title' => 'المصروفات والعهد النثرية',
                'icon' => 'banknote',
                'permissions' => [
                    'expenses.manage',
                ],
            ],
            'returns' => [
                'title' => 'مرتجعات المبيعات والمشتريات',
                'icon' => 'rotate-ccw',
                'permissions' => [
                    'returns.manage',
                ],
            ],
            'reports' => [
                'title' => 'التقارير المالية والأرباح',
                'icon' => 'bar-chart-3',
                'permissions' => [
                    'reports.view',
                ],
            ],
            'stores' => [
                'title' => 'الفروع والتحويلات المخزنية',
                'icon' => 'store',
                'permissions' => [
                    'stores.manage',
                    'stores.view_all',
                    'transfers.view',
                    'transfers.create',
                ],
            ],
            'daily_journal' => [
                'title' => 'الورديات والخزينة (Z-Report)',
                'icon' => 'wallet',
                'permissions' => [
                    'daily_journal.view',
                    'daily_journal.close_shift',
                    'daily_journal.manage',
                ],
            ],
            'administration' => [
                'title' => 'إدارة النظام والمستخدمين والرقابة',
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
