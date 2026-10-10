# 📑 فهرس ودليل صفحات ومكونات النظام (Pages & Views Index)

> **المشروع:** سرور كوفي ERP (Laravel 13 Multi-Tenancy + Pure Vue 3 SPA + Tailwind CSS v4)  
> **إجمالي الصفحات الموثقة:** 36 صفحة تشغيلية وإدارية  
> **تاريخ التحديث الشامل:** 2026-10-10  
> **معيار الهندسة:** نمط المنسق النحيف (Thin Orchestrator: 50-80 سطر)، مكونات أحادية المسؤولية، عزل الفروع (`X-Store-Id`)، ونزاهة مالية `DECIMAL(12,3)`.

---

## 🧭 1. صفحات العمليات التشغيلية والمستأجر (`SpaLayout.vue`)

| # | الصفحة والوثيقة | المسار (Route) | اسم المسار (Route Name) | الصلاحية المطلوبة (Permission) | الكنترولر الرئيسي (Controller) |
|---|---|---|---|---|---|
| 1 | [لوحة المؤشرات والقيادة](dashboard.md) | `/` | `dashboard` | `dashboard.view` | `DashboardController` |
| 2 | [نقطة البيع السريعة](pos.md) | `/pos` | `pos.index` | `pos.access` | `PosController` / `ShiftController` |
| 3 | [فواتير المبيعات](invoices.md) | `/invoices` | `invoices.index` | `invoices.view` | `InvoiceController` |
| 4 | [تفاصيل وطباعة الفاتورة](invoice-show.md) | `/invoices/:id` | `invoices.show` | `invoices.view` | `InvoiceController` |
| 5 | [دفتر اليومية والخزينة](daily-journal.md) | `/daily-journal` | `daily_journal.index` | `daily_journal.view` | `DailyJournalController` |
| 6 | [سجل المشتريات والتوريد](purchases.md) | `/purchases` | `purchases.index` | `purchases.view` | `PurchaseController` |
| 7 | [فاتورة مشتريات جديدة](create-purchase.md) | `/purchases/create` | `purchases.create` | `purchases.create` | `PurchaseController` |
| 8 | [رادار إعادة الطلب الذكي](smart-reorder.md) | `/purchases/smart-reorder` | `purchases.smart_reorder` | `purchases.view` | `PurchaseController` |
| 9 | [مرتجعات المبيعات والمشتريات](returns.md) | `/returns` | `returns.index` | `returns.manage` | `ReturnInvoiceController` |
| 10 | [تسجيل مرتجع جديد](create-return.md) | `/returns/create` | `returns.create` | `returns.manage` | `ReturnInvoiceController` |
| 11 | [سجل التحويلات المخزنية](stock-transfers.md) | `/stock-transfers` | `stock_transfers.index` | `transfers.view` | `StockTransferController` |
| 12 | [إذن تحويل مخزني جديد](create-stock-transfer.md) | `/stock-transfers/create` | `stock_transfers.create` | `stores.manage` | `StockTransferController` |
| 13 | [إدارة الأصناف والمخزون](items.md) | `/items` | `items.index` | `items.view` | `ItemController` |
| 14 | [فئات وتصنيفات الأصناف](categories.md) | `/categories` | `categories.index` | `items.view` | `CategoryController` |
| 15 | [سجل حركات مخزون الصنف](item-movements.md) | `/items/:id/movements` | `items.movements` | `items.view` | `ItemMovementController` |
| 16 | [أرصدة الأصناف بالمخازن](store-stocks.md) | `/store-stocks` | `store_stocks.index` | `items.view` | `StoreStockController` |
| 17 | [معمل تركيب وتجميع المنتجات](coffee-blender.md) | `/coffee-blender` | `coffee_blender.index` | `items.create` | `CoffeeBlenderController` |
| 18 | [إدارة الفروع والمستودعات](stores.md) | `/stores` | `stores.index` | `stores.manage` | `StoreController` |
| 19 | [إدارة دليل العملاء](customers.md) | `/customers` | `customers.index` | `customers.manage` | `CustomerController` |
| 20 | [كشف حساب العميل والمديونيات](customer-statement.md) | `/customers/:id/statement` | `customers.statement` | `customers.statement` | `CustomerController` |
| 21 | [إدارة دليل الموردين](suppliers.md) | `/suppliers` | `suppliers.index` | `suppliers.manage` | `SupplierController` |
| 22 | [كشف حساب المورد والمستحقات](supplier-statement.md) | `/suppliers/:id/statement` | `suppliers.statement` | `suppliers.statement` | `SupplierController` |
| 23 | [إدارة المصروفات وسندات الصرف](expenses.md) | `/expenses` | `expenses.index` | `expenses.manage` | `ExpenseController` |
| 24 | [التقارير المالية والأرباح](reports.md) | `/reports` | `reports.index` | `reports.view` | `ReportController` |
| 25 | [إدارة المستخدمين والموظفين](users.md) | `/users` | `users.index` | `roles.manage` | `UserController` |
| 26 | [مصفوفة الصلاحيات والأدوار](roles.md) | `/roles` | `roles.index` | `roles.manage` | `RoleController` |
| 27 | [سجل التدقيق الأمني والنشاطات](activity-logs.md) | `/activity-logs` | `activity-logs.index` | `logs.view` | `ActivityLogController` |
| 28 | [إعدادات النظام والمؤسسة](settings.md) | `/settings` | `settings.index` | `settings.manage` / `roles.manage` | `SettingController` |
| 29 | [الملف الشخصي وإعدادات الحساب](profile.md) | `/profile` | `profile.show` | مصادقة عامة (`auth:sanctum`) | `ProfileController` |
| 30 | [سلة المحذوفات والاسترجاع الآمن](trash.md) | `/trash` | `trash.index` | `trash.access` | `TrashController` |

---

## 👑 2. شاشات الإدارة المركزية والمنصة (`SuperAdminLayout.vue`)

| # | الصفحة والوثيقة | المسار (Route) | اسم المسار (Route Name) | الصلاحية المركزية (Central Ability) | الكنترولر الرئيسي (Controller) |
|---|---|---|---|---|---|
| 31 | [لوحة تحكم السوبر أدمن المركزية](super-admin-dashboard.md) | `/super-admin/dashboard` | `super_admin.dashboard` | `super_admin.access` (`super_admin.dashboard.view`) | `SuperAdminApiController` |
| 32 | [إدارة المستأجرين والشركات](super-admin-tenants.md) | `/super-admin/tenants` | `super_admin.tenants` | `super_admin.access` (`super_admin.tenants.view`) | `SuperAdminApiController` |
| 33 | [تفاصيل المستأجر والتحكم المركزي](super-admin-tenant-show.md) | `/super-admin/tenants/:id` | `super_admin.tenants.show` | `super_admin.access` (`super_admin.tenants.manage`) | `SuperAdminApiController` |
| 34 | [إدارة باقات الاشتراك والأسعار](super-admin-plans.md) | `/super-admin/plans` | `super_admin.plans` | `super_admin.access` (`super_admin.plans.view`) | `SuperAdminApiController` |
| 35 | [إدارة إصدارات التطبيق وحزم APK](super-admin-app-versions.md) | `/super-admin/app-versions` | `super_admin.app_versions` | `super_admin.access` (`super_admin.app_versions.view`) | `SuperAdminAppVersionController` |
| 36 | [إدارة وحدات القياس المركزية](super-admin-units.md) | `/super-admin/units` | `super_admin.units` | `super_admin.access` (`super_admin.settings.view`) | `SuperAdminApiController` |

---

## 🛡️ 3. القواعد المعمارية والأمنية الإلزامية في الصفحات:
1. **عزل سياق المستأجر والمخزن:** كافة طلبات واجهات المستأجر ترسل ترويسة `X-Store-Id` لتحديد المخزن النشط؛ وفي حال عدم امتلاك المستخدم حق الوصول للفرع يُعاد `403 store_access_denied`.
2. **عزل المنصة المركزية (`EnsureCentralContext`):** مسارات السوبر أدمن معزولة تماماً في `SuperAdminLayout` وتمنع الوصول من نطاقات المستأجرين عبر إرجاع `404 Not Found`.
3. **الدقة المالية الصارمة:** تعامل كافة القيم المحاسبية والكميات بدقة `DECIMAL(12,3)` مع تقريب half-up لضمان التطابق التام مع القيود والخزينة.
4. **التوطين والترجمة 100%:** لا توجد نصوص ثابتة داخل ملفات Vue؛ كافة النصوص تستدعى عبر `$t()` وتتطابق مع ملفات `backend/lang/ar/*.php` و `backend/lang/en/*.php`.
