# سجل تعديل: المرحلة 4 - Module 04: المصروفات والعهد النثرية وتصنيفاتها (Expenses & Petty Cash)
* **التاريخ والوقت:** 2026-08-21 15:06
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول المصروفات والعهد النثرية من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، وبناء شاشة ExpensesView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Expenses/ExpenseDTO.php` - DTO لتسجيل وتعديل المصروفات.
* `[NEW]` `backend/app/Actions/Expenses/CreateExpenseAction.php` - Single Action لإنشاء المصروف وتوليد الكود التسلسلي داخل Transaction.
* `[NEW]` `backend/app/Actions/Expenses/UpdateExpenseAction.php` - Single Action لتعديل بيانات المصروف.
* `[NEW]` `backend/app/Actions/Expenses/DeleteExpenseAction.php` - Single Action لحذف المصروف.
* `[NEW]` `backend/app/Actions/Expenses/GetExpensesSummaryAction.php` - Single Action لحساب إجمالي مصروفات الشهر والمصروفات النقدية وإجمالي الفلترة بدقة bcmath.
* `[NEW]` `backend/app/Http/Resources/ExpenseResource.php` - تنسيق بيانات المصروف ومراكز التكلفة للـ API.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/ExpenseController.php` - متحكم API كامل للمصروفات مع الفلاتر والمؤشرات المالية.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسار عرض تفاصيل المصروف show.
* `[NEW]` `backend/resources/js/views/Expenses/ExpensesView.vue` - شاشة إدارة المصروفات والعهد في Vue 3 SPA.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسار المصروفات في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - إضافة رابط المصروفات في القائمة الجانبية.
* `[NEW]` `backend/tests/Feature/Api/ExpensesApiTest.php` - اختبارات Feature شاملة لكافة عمليات المصروفات والإحصائيات.

## 2. القرارات التقنية:
* استخدام الكود التسلسلي التلقائي الذكي `EXP-ymd-0001`.
* حساب المجاميع المالية الشهرية بدقة ومطابقة طرق السداد (كاش، إنستاباي، محفظة، فيزا، تحويل، شيك).
* ربط المصروف بالفرع النشط وبالمستخدم المسجل تلقائياً.

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `ExpensesApiTest` بنجاح 100% (5/5 tests passed, 26 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (42/42 tests passed, 272 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 4.26 ثانية).
