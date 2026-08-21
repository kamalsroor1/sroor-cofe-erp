<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roleId = $request->input('role_id');
        $roles = Role::with('permissions')->get();

        $selectedRole = $roleId ? $roles->firstWhere('id', (int)$roleId) : ($roles->firstWhere('name', 'cashier') ?: $roles->first());
        $allPermissions = Permission::all();

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
                    'reports.view' => 'عرض تقارير الأرباح والمبيعات الشاملة',
                ],
            ],
            'stores' => [
                'title'       => 'الفروع والتحويلات المخزنية',
                'icon'        => '🏬',
                'permissions' => [
                    'stores.manage'   => 'إدارة الفروع وعربيات التوزيع',
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
                    'roles.manage' => 'إدارة المستخدمين والأدوار وتعديل الصلاحيات',
                    'logs.view'    => 'عرض سجل التدقيق الأمني والنشاطات',
                    'trash.access' => 'الوصول لسلة المحذوفات واسترجاع البيانات',
                ],
            ],
        ];

        return Inertia::render('Roles/Index', [
            'roles' => $roles->map(fn($r) => [
                'id'    => $r->id,
                'name'  => $r->name,
                'label' => match ($r->name) {
                    'admin'       => 'مدير النظام 👑',
                    'cashier'     => 'كاشير مبيعات 🛒',
                    'storekeeper' => 'أمين مخزن 📦',
                    'accountant'  => 'محاسب 💼',
                    default       => $r->name,
                },
                'permissions_count' => $r->permissions->count(),
            ]),
            'selected_role' => $selectedRole ? [
                'id'          => $selectedRole->id,
                'name'        => $selectedRole->name,
                'permissions' => $selectedRole->permissions->pluck('name')->toArray(),
            ] : null,
            'permission_modules' => $modules,
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, int $id): RedirectResponse
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'admin') {
            $role->syncPermissions(Permission::all());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            return redirect()->back()->with('info', __('auth.admin_role_full_access'));
        }

        $validated = $request->validated();
        $permissions = $validated['permissions'] ?? [];
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back()->with('success', __('auth.role_permissions_updated'));
    }
}