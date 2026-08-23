# سجل تعديل: إتمام المراجعة والإصلاح الشامل لمفاتيح الترجمة i18n
* **التاريخ والوقت:** 2026-08-22 03:05
* **الدور المفعل:** Frontend UI Agent & Backend Architect Agent
* **الهدف:** إتمام المراجعة الشاملة والتوطين بنسبة 100% لكافة شاشات وعروض ومكونات Pure Vue 3 SPA في تطبيق سرور كوفي ERP وإزالة أي نصوص ثابتة، وإضافة المفاتيح الرسمية للغتين العربية والإنجليزية.

## 1. الملفات المعدلة والمحدثة:
* `[MODIFIED]` `backend/lang/ar/inventory.php` & `backend/lang/en/inventory.php` - إضافة مفاتيح التحويلات المخزنية وإدارة الفروع والأرصدة.
* `[MODIFIED]` `backend/lang/ar/super.php` & `backend/lang/en/super.php` - إضافة مفاتيح لوحة السوبر أدمن، إدارة المستأجرين، الباقات، وإصدارات التطبيق OTA.
* `[MODIFIED]` `backend/lang/ar/contacts.php` & `backend/lang/en/contacts.php` - إضافة مفاتيح الموردين، المستحقات، وسندات الصرف وكشف الحساب.
* `[MODIFIED]` `backend/lang/ar/trash.php` & `backend/lang/en/trash.php` - إضافة مفاتيح سلة المحذوفات والاسترجاع والحذف النهائي.
* `[MODIFIED]` `backend/lang/ar/users.php` & `backend/lang/en/users.php` - إضافة مفاتيح إدارة الموظفين، الأدوار، وتعيينات الفروع.
* `[MODIFIED]` `backend/resources/js/views/StockTransfers/CreateStockTransferView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/Stores/StoresView.vue` & `StoreStocksView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/SuperAdmin/SuperAdminDashboardView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/SuperAdmin/SuperAdminTenantsView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/SuperAdmin/SuperAdminPlansView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/SuperAdmin/SuperAdminAppVersionsView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/Suppliers/SuppliersView.vue` & `SupplierStatementView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/Trash/TrashView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/views/Users/UsersView.vue` - توطين كامل.
* `[MODIFIED]` `backend/resources/js/Components/ActionMenu.vue` - استبدال Link بـ router-link وتوطين القوائم.
* `[MODIFIED]` `backend/resources/views/layouts/print-thermal.blade.php` - توطين كامل لإيصالات الكاشير الحرارية.
* `[MODIFIED]` `backend/resources/views/layouts/print-a4.blade.php` - توطين كامل لفواتير المبيعات الرسمية A4.
* `[MODIFIED]` `backend/resources/views/layouts/print-daily-journal-a4.blade.php` - توطين كامل لليومية وحركة الخزينة والورديات A4.
* `[MODIFIED]` `backend/resources/views/layouts/print-item-movements-a4.blade.php` - توطين كامل لكارت حركة الصنف A4.
* `[MODIFIED]` `backend/resources/views/layouts/print-report-a4.blade.php` - توطين كامل لتقارير الأرباح والمخازن والمالية A4.
* `[MODIFIED]` `backend/app/Services/ExportService.php` - توطين كامل لرؤوس ملفات الـ CSV المصدرة لإكسيل.
* `[MODIFIED]` `backend/app/Http/Controllers/ActivityLogController.php` - تنظيف وتوطين تصدير سجل النشاط.
* `[MODIFIED]` `docs/i18n-review-log.md` - توثيق كامل للـ 82 مكوناً وصفحة ومطابقة نسب التغطية 100%.

## 2. القرارات التقنية:
* اعتماد الترجمة عبر دوال `$t('domain.key')` و `trans('domain.key')` المربوطة بـ `window.spaTranslations` و `useAppConfigStore`.
* التحقق من المعايير المعمارية: لا يوجد أي `FLOAT` أو `DOUBLE`، وتنسيق الأرقام والعملات بدوال `formatMoney` و `formatDecimal` المتوافقة مع `DECIMAL(12,3)`.
* تنظيف الاعتماد المتبقي على Inertiajs واستبداله بـ Vue Router و REST API.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وبناء Vite بنجاح تام (`npm run build` code 0).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (`ar` & `en`).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
