<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define all permissions grouped by module
        $permissions = [
            // POS & Invoices
            'pos.access'          => 'الوصول لشاشة نقطة البيع (POS)',
            'invoices.view'       => 'عرض سجل فواتير المبيعات',
            'invoices.create'     => 'إنشاء واعتماد فواتير المبيعات',
            'invoices.edit'       => 'تعديل فواتير المبيعات المعتمدة',
            'invoices.cancel'     => 'إلغاء الفواتير وعكس أثر المخزون',
            'invoices.delete'     => 'حذف وأرشفة فواتير المبيعات',
            'invoices.discount'   => 'صلاحية منح خصومات للعملاء',

            // Items & Inventory
            'items.view'          => 'عرض قائمة الأصناف والأسعار',
            'items.create'        => 'إضافة أصناف جديدة للمخزون',
            'items.edit'          => 'تعديل بيانات وأسعار الأصناف',
            'items.delete'        => 'أرشفة وحذف الأصناف',
            'items.view_cost'     => 'رؤية سعر التكلفة وهوامش الربح',

            // Purchases
            'purchases.view'      => 'عرض سجل فواتير المشتريات',
            'purchases.create'    => 'تسجيل وتوريد مشتريات جديدة للمخزن',
            'purchases.delete'    => 'أرشفة فواتير المشتريات',

            // Stores & Transfers
            'stores.manage'       => 'إدارة الفروع وتعيين الموظفين',
            'transfers.view'      => 'عرض أذونات التحويل المخزني',
            'transfers.create'    => 'إنشاء أذونات تحويل وشحن عربات التوزيع',

            // Contacts
            'customers.manage'    => 'إدارة دليل العملاء',
            'customers.statement' => 'عرض وتصدير كشف حساب عميل',
            'suppliers.manage'    => 'إدارة دليل الموردين وحساباتهم',
            'suppliers.statement' => 'عرض وتصدير كشف حساب مورد',

            // Financials & Daily Journal
            'daily_journal.view'        => 'عرض اليومية النقدية وحركة الدرج والشفتات',
            'daily_journal.close_shift' => 'فتح وتقفيل ورديات الكاشير واليومية',
            'expenses.manage'           => 'تسجيل وتعديل وحذف المصروفات',
            'returns.manage'            => 'إدارة مرتجعات المبيعات والمشتريات',

            // Admin & Reports
            'reports.view'        => 'عرض التقارير المالية والأرباح ومقارنة الفروع',
            'trash.access'        => 'الوصول لسلة المحذوفات المركزية واسترجاع البيانات',
            'roles.manage'        => 'إدارة المستخدمين والأدوار والصلاحيات',
            'logs.view'           => 'عرض وفحص سجل العمليات والرقابة الذاتية',
            'super_admin.access'  => 'الوصول للمنصة المركزية وإدارة المشتركين والباقات',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web']);
        }

        // 2. Roles Setup & Permission Assignment
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);
        $storeRole = Role::firstOrCreate(['name' => 'storekeeper']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);

        // Super Admin gets ALL permissions including central platform
        $superAdminRole->syncPermissions(Permission::all());

        // Store Admin gets all local ERP permissions EXCEPT central super_admin.access
        $storeAdminPermissions = Permission::where('name', '!=', 'super_admin.access')->get();
        $adminRole->syncPermissions($storeAdminPermissions);

        // Cashier permissions
        $cashierRole->syncPermissions([
            'pos.access',
            'invoices.view',
            'invoices.create',
            'items.view',
            'customers.manage',
            'customers.statement',
            'daily_journal.view',
            'daily_journal.close_shift',
            'returns.manage',
        ]);

        // Storekeeper permissions
        $storeRole->syncPermissions([
            'items.view',
            'items.create',
            'items.edit',
            'purchases.view',
            'purchases.create',
            'transfers.view',
            'transfers.create',
            'suppliers.manage',
            'suppliers.statement',
            'returns.manage',
        ]);

        // Accountant permissions
        $accountantRole->syncPermissions([
            'invoices.view',
            'purchases.view',
            'customers.manage',
            'customers.statement',
            'suppliers.manage',
            'suppliers.statement',
            'daily_journal.view',
            'daily_journal.close_shift',
            'expenses.manage',
            'reports.view',
            'items.view',
            'items.view_cost',
        ]);
    }
}
