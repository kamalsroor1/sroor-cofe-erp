# سجل تعديل: إزالة كافة النصوص الثابتة (سرور كوفي) وتعميم companyName الديناميكي
* **التاريخ والوقت:** 2026-08-23 21:24
* **الدور المفعل:** Frontend UI / Backend Architect
* **الهدف:** فحص كافة ملفات النظام (Vue SPA, Blade Templates, Controllers, Livewire, Actions) وحذف أي اسم مؤسسة ثابت (Hardcoded 'سرور كوفي' / 'سرور') واستبداله بقيم ديناميكية تعتمد على `companyName` من `appConfigStore` أو موديل الإعدادات `Setting::get('company_name')`.

## 1. الملفات المعدلة:
* `[MODIFIED]` `resources/js/views/DashboardView.vue` - ربط `:company-name="appConfigStore.companyName"` بدون قيمة افتراضية ثابتة.
* `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` - تحويل ترويسة النظام والقائمة الجانبية للشاشات الصغيرة والـ Drawer إلى `appConfigStore.companyName`.
* `[MODIFIED]` `resources/js/Layouts/AppLayout.vue` - تحويل عناصر الشعار والقائمة الجانبية إلى `tenant?.name`.
* `[MODIFIED]` `resources/js/views/Invoices/InvoicePrintView.vue` - استخراج `companyName` و `companySubtitle` و `activeStoreName` ديناميكياً من بيانات الفاتورة و `appConfigStore`.
* `[MODIFIED]` `resources/js/views/Auth/LoginView.vue` - تعميم شارة تذييل شاشة الدخول إلى `appConfigStore.platformName`.
* `[MODIFIED]` `resources/js/Components/Common/DataTable.vue` - تنظيف تعليق رأس الملف من الاسم الثابت.
* `[MODIFIED]` `app/Actions/Invoices/GetInvoiceDetailsAction.php` - جعل نصوص ورسائل مشاركة الفاتورة عبر واتساب تقرأ `Setting::get('company_name')` ديناميكياً.
* `[MODIFIED]` `app/Http/Controllers/Api/AppUpdateController.php` - جعل نص إشعار التحديث العام للتطبيق بدون نصوص ثابتة.
* `[MODIFIED]` `app/Http/Controllers/SettingController.php` - جعل القيم الافتراضية للإعدادات ديناميكية.
* `[MODIFIED]` `app/Livewire/SettingsIndex.php` وجميع كلاسات Livewire - تحديث عناوين الصفحات والقيم الافتراضية.
* `[MODIFIED]` `resources/views/layouts/print-a4.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/layouts/print-daily-journal-a4.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/layouts/print-item-movements-a4.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/layouts/print-report-a4.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/layouts/print-thermal.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/livewire/customer-statement.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/livewire/supplier-statement.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/components/layouts/app.blade.php` - استخدام `Setting::get('company_name')`.
* `[MODIFIED]` `resources/views/livewire/settings-index.blade.php` - استبدال الـ Placeholders الثابتة بنصوص وصفية عامة.

## 2. القرارات التقنية:
* إزالة كافة نصوص 'سرور كوفي' و'مؤسسة سرور' الثابتة من الفرونت إند والباك إند وقوالب الطباعة، والاعتماد بنسبة 100% على اسم المؤسسة `companyName` المحدد لكل مستأجر / فرع من قاعدة البيانات أو الترويسات.
* التحقق من خلو ملفات `resources/js` و `app/` تماماً (0 مطابقة) لأي نص ثابت.

## 3. التحقق والاختبار:
* [x] بناء أصول الفرونت إند بنجاح `npm run build` في 6.04s.
* [x] اجتياز كافة اختبارات الـ API بنجاح `DashboardApiTest` (8 passed, 51 assertions).
* [x] اجتياز كافة اختبارات المتصفح الحقيقي Playwright E2E بنجاح تام (3 passed).
