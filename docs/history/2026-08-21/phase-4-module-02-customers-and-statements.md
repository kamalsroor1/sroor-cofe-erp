# سجل تعديل: المرحلة 4 - Module 02: العملاء وكشوف الحساب التفصيلية (Customers & Statements)
* **التاريخ والوقت:** 2026-08-21 14:57
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول العملاء، كشوف الحساب، وسندات التحصيل من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، وبناء شاشات CustomersView و CustomerStatementView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Customers/CustomerDTO.php` - DTO لإنشاء وتعديل العملاء.
* `[NEW]` `backend/app/DTOs/Customers/CollectCustomerPaymentDTO.php` - DTO لسندات التحصيل.
* `[NEW]` `backend/app/Actions/Customers/CreateCustomerAction.php` - Single Action لإنشاء العميل مع الرصيد الافتتاحي داخل Transaction.
* `[NEW]` `backend/app/Actions/Customers/UpdateCustomerAction.php` - Single Action لتعديل بيانات العميل.
* `[NEW]` `backend/app/Actions/Customers/DeleteCustomerAction.php` - Single Action لحذف العميل مع فحص موانع الحذف والمديونيات.
* `[NEW]` `backend/app/Actions/Customers/ToggleCustomerActiveAction.php` - Single Action لتفعيل/تعطيل حساب العميل.
* `[NEW]` `backend/app/Actions/Customers/CollectCustomerPaymentAction.php` - Single Action لتحصيل المديونية وربطه بـ PaymentService.
* `[NEW]` `backend/app/Actions/Customers/GetCustomerStatementAction.php` - Single Action لحساب كشف الحساب التفصيلي والرصيد التراكمي بدقة bcmath.
* `[NEW]` `backend/app/Http/Resources/CustomerResource.php` - تنسيق بيانات العميل والإحصائيات وموانع الحذف.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/CustomerController.php` - متحكم API كامل للعملاء والتحصيل وكشف الحساب.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات التحصيل وكشف الحساب.
* `[NEW]` `backend/resources/js/views/Customers/CustomersView.vue` - شاشة إدارة العملاء والتحصيل في Vue 3 SPA.
* `[NEW]` `backend/resources/js/views/Customers/CustomerStatementView.vue` - شاشة كشف حساب العميل التفصيلي.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات العملاء وكشف الحساب في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - ربط زر العملاء في القائمة الجانبية بالـ Router.
* `[NEW]` `backend/tests/Feature/Api/CustomersApiTest.php` - اختبارات Feature شاملة لكافة عمليات العملاء والتحصيل وكشف الحساب.

## 2. القرارات التقنية:
* الحساب الدقيق للمديونيات والأرصدة التراكمية باستخدام `bcadd` و `bcsub` في `CustomerBalanceService` و `GetCustomerStatementAction`.
* حماية حسابات العملاء من الحذف عند وجود فواتير أو سدادات أو رصيد غير مسوى.
* دعم السداد المتعدد (نقدي، فودافون كاش، إنستاباي، تحويل بنكي).

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `CustomersApiTest` بنجاح 100% (7/7 tests passed, 49 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (30/30 tests passed, 197 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 6.37 ثانية).
