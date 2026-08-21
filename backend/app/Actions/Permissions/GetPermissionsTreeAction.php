<?php

declare(strict_types=1);

namespace App\Actions\Permissions;

use App\Models\User;
use Spatie\Permission\Models\Permission;
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
                'title'       => 'المبيعات ونقاط البيع (POS)',
                'icon'        => 'shopping-cart',
                'permissions' => [
                    'pos.access'        => 'دخول شاشة الكاشير السريع (POS)',
                    'invoices.view'     => 'عرض سجل الفواتير',
                    'invoices.create'   => 'إنشاء وحفظ فواتير جديدة',
                    'invoices.edit'     => 'تعديل فواتير المبيعات المعتمدة',
                    'invoices.cancel'   => 'إلغاء الفواتير وعكس المخزون',
                    'invoices.delete'   => 'حذف وأرشفة فواتير المبيعات',
                    'invoices.discount' => 'صلاحية منح خصومات للعملاء',
                ],
            ],
            'inventory' => [
                'title'       => 'الأصناف والمخزون وخامات البن',
                'icon'        => 'package',
                'permissions' => [
                    'items.view'      => 'عرض دليل الأصناف والأرصدة',
                    'items.create'    => 'إضافة أصناف جديدة',
                    'items.edit'      => 'تعديل بيانات وأسعار الأصناف',
                    'items.delete'    => 'حذف أو أرشفة الأصناف',
                    'items.view_cost' => 'عرض أسعار التكلفة وهوامش الربح',
                ],
            ],
            'purchases' => [
                'title'       => 'المشتريات والتوريدات',
                'icon'        => 'truck',
                'permissions' => [
                    'purchases.view'   => 'عرض فواتير المشتريات',
                    'purchases.create' => 'تسجيل فواتير شراء وتوريد',
                    'purchases.delete' => 'إلغاء فواتير المشتريات',
                ],
            ],
            'customers' => [
                'title'       => 'العملاء والتحصيل النقدي',
                'icon'        => 'users',
                'permissions' => [
                    'customers.manage'    => 'إدارة دليل العملاء',
                    'customers.statement' => 'عرض وتصدير كشف حساب عميل',
                ],
            ],
            'suppliers' => [
                'title'       => 'الموردين وسندات السداد',
                'icon'        => 'factory',
                'permissions' => [
                    'suppliers.manage'    => 'إدارة دليل الموردين',
                    'suppliers.statement' => 'عرض وتصدير كشف حساب مورد',
                ],
            ],
            'expenses' => [
                'title'       => 'المصروفات والعهد النثرية',
                'icon'        => 'banknote',
                'permissions' => [
                    'expenses.manage' => 'تسجيل وتعديل وحذف المصروفات',
                ],
            ],
            'returns' => [
                'title'       => 'مرتجعات المبيعات والمشتريات',
                'icon'        => 'rotate-ccw',
                'permissions' => [
                    'returns.manage' => 'إدارة وتسجيل مرتجعات المبيعات والمشتريات',
                ],
            ],
            'reports' => [
                'title'       => 'التقارير المالية والأرباح',
                'icon'        => 'bar-chart-3',
                'permissions' => [
                    'reports.view' => 'عرض تقارير الأرباح والمبيعات والقوائم المالية',
                ],
            ],
            'stores' => [
                'title'       => 'الفروع والتحويلات المخزنية',
                'icon'        => 'store',
                'permissions' => [
                    'stores.manage'    => 'إدارة الفروع وتعيين الكاشيرين',
                    'transfers.view'   => 'عرض أذونات التحويل المخزني',
                    'transfers.create' => 'إنشاء تحويلات بين الفروع والمخازن',
                ],
            ],
            'daily_journal' => [
                'title'       => 'الورديات والخزينة (Z-Report)',
                'icon'        => 'wallet',
                'permissions' => [
                    'daily_journal.view'        => 'عرض دفتر اليومية وحركة الدرج',
                    'daily_journal.close_shift' => 'فتح وتقفيل ورديات الكاشير',
                ],
            ],
            'administration' => [
                'title'       => 'إدارة النظام والمستخدمين والرقابة',
                'icon'        => 'shield-check',
                'permissions' => [
                    'roles.manage' => 'إدارة المستخدمين وتعديل الصلاحيات',
                    'logs.view'    => 'عرض سجل التدقيق والأنشطة',
                    'trash.access' => 'الوصول لسلة المحذوفات واسترجاع البيانات',
                ],
            ],
        ];

        $rolesData = [];
        if ($isAdmin || $user->can('roles.manage')) {
            $roles = Role::with('permissions')->get();
            $rolesData = $roles->map(fn ($r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'permissions' => $r->permissions->pluck('name')->toArray(),
            ])->toArray();
        }

        return [
            'user_permissions'      => $userPermissions,
            'user_roles'            => $userRoles,
            'is_admin'              => $isAdmin,
            'permission_modules'    => $modules,
            'roles'                 => $rolesData,
        ];
    }
}
