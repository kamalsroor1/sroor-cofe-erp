# سجل تعديل: المرحلة 4 - Module 06: الورديات ودفتر اليومية والخزينة (Shifts & Daily Journal)
* **التاريخ والوقت:** 2026-08-21 15:14
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول الورديات وتقفيل الوردية Z-Report ودفتر اليومية وجرد النقدية والخزينة من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، وبناء شاشة DailyJournalView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Shifts/OpenShiftDTO.php` - DTO لفتح وردية العمل والرصيد الافتتاحي.
* `[NEW]` `backend/app/DTOs/Shifts/CloseShiftDTO.php` - DTO لتقفيل وردية العمل وجرد النقدية.
* `[NEW]` `backend/app/Actions/Shifts/GetActiveShiftAction.php` - Single Action لاستعلام الوردية المفتوحة ومؤشرات البيع اللحظية.
* `[NEW]` `backend/app/Actions/Shifts/OpenShiftAction.php` - Single Action لفتح وردية جديدة وتوليد الكود التسلسلي.
* `[NEW]` `backend/app/Actions/Shifts/CloseShiftAction.php` - Single Action لتقفيل الوردية وجرد النقدية وحساب الفارق بدقة `bcmath`.
* `[NEW]` `backend/app/Actions/Shifts/GetShiftZReportAction.php` - Single Action لتجهيز بيانات تقرير Z-Report للطباعة الحرارية.
* `[NEW]` `backend/app/Actions/Shifts/GetDailyJournalAction.php` - Single Action لحساب دفتر اليومية وجرد النقدية وحسابات الخزينة اليومية.
* `[NEW]` `backend/app/Http/Resources/CashShiftResource.php` - تنسيق بيانات الورديات والمجاميع المالية للـ API.
* `[MODIFIED]` `backend/app/Http/Requests/OpenShiftRequest.php` - تحديث الصلاحيات.
* `[MODIFIED]` `backend/app/Http/Requests/CloseShiftRequest.php` - تحديث الصلاحيات.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/ShiftController.php` - متحكم API متكامل للورديات.
* `[NEW]` `backend/app/Http/Controllers/Api/DailyJournalController.php` - متحكم API لدفتر اليومية وجرد الخزينة.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات الورديات وتقرير Z ودفتر اليومية.
* `[NEW]` `backend/resources/js/views/DailyJournal/DailyJournalView.vue` - شاشة دفتر اليومية وحالة الوردية اللحظية وتقارير Z.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسار `/daily-journal`.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - تفعيل رابط دفتر اليومية والخزينة في القائمة الجانبية.
* `[NEW]` `backend/tests/Feature/Api/ShiftsAndDailyJournalApiTest.php` - اختبارات Feature شاملة لجميع عمليات الورديات ودفتر اليومية.

## 2. القرارات التقنية:
* الحساب الدقيق لرصيد الدرج المتوقع عبر `bcmath`: `افتتاحي + مبيعات كاش + تحصيلات عملاء - مدفوعات موردين - مصروفات كاش`.
* إتاحة الفتح والإغلاق الفوري مع المعاينة الحية للفارق (عجز / زيادة / مطابقة).
* تجهيز بيانات التقرير Z بالكامل للطباعة الحرارية Thermal Printing.

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `ShiftsAndDailyJournalApiTest` بنجاح 100% (6/6 tests passed, 27 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (57/57 tests passed, 335 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 5.14 ثانية).
