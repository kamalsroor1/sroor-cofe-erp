# سجل تعديل: المرحلة 4 - Module 03: الموردين وكشوف الحساب وسندات الصرف (Suppliers & Statements)
* **التاريخ والوقت:** 2026-08-21 15:00
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول الموردين، كشوف الحساب، وسندات الصرف من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، وبناء شاشات SuppliersView و SupplierStatementView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Suppliers/SupplierDTO.php` - DTO لإنشاء وتعديل الموردين.
* `[NEW]` `backend/app/DTOs/Suppliers/PaySupplierDTO.php` - DTO لسندات صرف وسداد الموردين.
* `[NEW]` `backend/app/Actions/Suppliers/CreateSupplierAction.php` - Single Action لإنشاء المورد مع الرصيد الافتتاحي داخل Transaction.
* `[NEW]` `backend/app/Actions/Suppliers/UpdateSupplierAction.php` - Single Action لتعديل بيانات المورد.
* `[NEW]` `backend/app/Actions/Suppliers/DeleteSupplierAction.php` - Single Action لحذف المورد مع فحص موانع الحذف والمستحقات.
* `[NEW]` `backend/app/Actions/Suppliers/ToggleSupplierActiveAction.php` - Single Action لتفعيل/تعطيل حساب المورد.
* `[NEW]` `backend/app/Actions/Suppliers/PaySupplierAction.php` - Single Action لسداد مستحقات المورد وربطه بـ PaymentService.
* `[NEW]` `backend/app/Actions/Suppliers/GetSupplierStatementAction.php` - Single Action لحساب كشف الحساب التفصيلي والرصيد التراكمي بدقة bcmath.
* `[NEW]` `backend/app/Http/Resources/SupplierResource.php` - تنسيق بيانات المورد والإحصائيات وموانع الحذف.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/SupplierController.php` - متحكم API كامل للموردين والصرف وكشف الحساب.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات الصرف وكشف الحساب للموردين.
* `[NEW]` `backend/resources/js/views/Suppliers/SuppliersView.vue` - شاشة إدارة الموردين وسندات الصرف في Vue 3 SPA.
* `[NEW]` `backend/resources/js/views/Suppliers/SupplierStatementView.vue` - شاشة كشف حساب المورد التفصيلي.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات الموردين وكشف الحساب في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - ربط زر الموردين في القائمة الجانبية بالـ Router.
* `[NEW]` `backend/tests/Feature/Api/SuppliersApiTest.php` - اختبارات Feature شاملة لكافة عمليات الموردين والصرف وكشف الحساب.

## 2. القرارات التقنية:
* الحساب الدقيق للمستحقات والأرصدة التراكمية باستخدام `bcadd` و `bcsub` في `SupplierBalanceService` و `GetSupplierStatementAction`.
* حماية حسابات الموردين من الحذف عند وجود فواتير شراء أو سدادات أو رصيد غير مسوى.
* دعم السداد المتعدد (نقدي، فودافون كاش، إنستاباي، تحويل بنكي).

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `SuppliersApiTest` بنجاح 100% (7/7 tests passed, 49 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (37/37 tests passed, 246 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 4.73 ثانية).
