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
                'title'       => 'المبيعات ونقاط البيع (POS)',
                'icon'        => '🛒',
                'permissions' => [
                    'pos.access'      => 'دخول شاشة الكاشير السريع (POS)',
                    'invoices.view'   => 'عرض سجل الفواتير',
                    'invoices.create' => 'إنشاء وحفظ فواتير جديدة',
                    'invoices.cancel' => 'إلغاء الفواتير وعكس المخزون',
                    'invoices.print'  => 'طباعة إيصالات الفواتير',
                ],
            ],
            'inventory' => [
                'title'       => 'الأصناف والمخزون وخامات البن',
                'icon'        => '📦',
                'permissions' => [
                    'items.view'      => 'عرض دليل الأصناف والأرصدة',
                    'items.create'    => 'إضافة أصناف جديدة وتعديلها',
                    'items.delete'    => 'حذف أو أرشفة الأصناف',
                    'items.cost.view' => 'عرض أسعار التكلفة وهامش الربح',
                ],
            ],
            'purchases' => [
                'title'       => 'المشتريات والتوريدات',
                'icon'        => '🚚',
                'permissions' => [
                    'purchases.view'   => 'عرض فواتير المشتريات',
                    'purchases.create' => 'تسجيل فواتير شراء جديدة',
                    'purchases.delete' => 'إلغاء فواتير المشتريات',
                ],
            ],
            'customers' => [
                'title'       => 'العملاء والتحصيل النقدي',
                'icon'        => '👥',
                'permissions' => [
                    'customers.manage'    => 'إدارة العملاء وإضافة عميل جديد',
                    'customers.statement' => 'عرض كشف حساب العميل والطباعة',
                ],
            ],
            'suppliers' => [
                'title'       => 'الموردين وسندات السداد',
                'icon'        => '🏭',
                'permissions' => [
                    'suppliers.manage'    => 'إدارة الموردين وتسجيل سداد للمورد',
                    'suppliers.statement' => 'عرض كشف حساب المورد والطباعة',
                ],
            ],
            'expenses' => [
                'title'       => 'المصروفات والنثريات',
                'icon'        => '💸',
                'permissions' => [
                    'expenses.manage' => 'تسجيل وتعديل المصروفات التشغيلية',
                ],
            ],
            'reports' => [
                'title'       => 'التقارير المالية والأرباح',
                'icon'        => '📈',
                'permissions' => [
                    'reports.view'     => 'عرض تقارير الأرباح والمبيعات الشاملة',
                    'reports.advanced' => 'التقارير المالية المتقدمة وتصدير البيانات',
                ],
            ],
            'stores' => [
                'title'       => 'الفروع والتحويلات المخزنية',
                'icon'        => '🏬',
                'permissions' => [
                    'stores.view'      => 'عرض الفروع والمخازن',
                    'stores.manage'    => 'إدارة الفروع وعربيات التوزيع',
                    'transfers.view'   => 'عرض أذونات التحويل المخزني',
                    'transfers.create' => 'إنشاء تحويلات بين المخازن',
                ],
            ],
            'daily_journal' => [
                'title'       => 'الورديات والخزينة (Z-Report)',
                'icon'        => '💵',
                'permissions' => [
                    'daily_journal.view' => 'فتح وإغلاق الورديات واعتماد Z-Report',
                ],
            ],
            'roles' => [
                'title'       => 'إدارة النظام والمستخدمين',
                'icon'        => '🛡️',
                'permissions' => [
                    'users.manage' => 'إدارة الموظفين وحسابات المستخدمين',
                    'roles.manage' => 'إدارة الأدوار وتعديل الصلاحيات',
                    'logs.view'    => 'عرض سجل التدقيق الأمني والنشاطات',
                    'trash.access' => 'الوصول لسلة المحذوفات واسترجاع البيانات',
                ],
            ],
        ];

        return [
            'roles' => $roles->map(fn($r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'label'             => match ($r->name) {
                    'admin'       => 'مدير النظام 👑',
                    'cashier'     => 'كاشير مبيعات 🛒',
                    'storekeeper' => 'أمين مخزن 📦',
                    'accountant'  => 'محاسب 💼',
                    default       => $r->name,
                },
                'permissions_count' => $r->permissions->count(),
                'permissions'       => $r->permissions->pluck('name')->toArray(),
            ]),
            'selected_role' => $selectedRole ? [
                'id'          => $selectedRole->id,
                'name'        => $selectedRole->name,
                'permissions' => $selectedRole->permissions->pluck('name')->toArray(),
            ] : null,
            'permission_modules' => $modules,
        ];
    }
}
