# سجل تعديل: المرحلة 4 - Module 09: مرتجعات المبيعات والمشتريات (Returns: Sales & Purchase Returns)
* **التاريخ والوقت:** 2026-08-21 15:25
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول مرتجعات المبيعات والمشتريات من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، Form Requests ومنع أي `validate()` داخل الكنترولرز، وبناء شاشات ReturnsView و CreateReturnView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Returns/ReturnDocumentDTO.php` - DTO محكم النوع لمستندات المرتجعات (مبيعات ومشتريات).
* `[NEW]` `backend/app/Actions/Returns/CreateReturnAction.php` - Single Action لتسجيل واعتماد المرتجع وعكس حركات المخزون وضبط أرصدة العملاء والموردين.
* `[NEW]` `backend/app/Actions/Returns/DeleteReturnAction.php` - Single Action لحذف وأرشفة مستند المرتجع.
* `[NEW]` `backend/app/Http/Resources/ReturnResource.php` - محول بيانات مستندات المرتجعات للـ API.
* `[NEW]` `backend/app/Http/Resources/ReturnItemResource.php` - محول بيانات بنود المرتجعات للـ API.
* `[MODIFIED]` `backend/app/Http/Requests/StoreReturnRequest.php` - التحقق الصارم عبر Form Request وتحديث الصلاحيات.
* `[REFACTORED]` `backend/app/Http/Controllers/Api/ReturnController.php` - متحكم API نقي وخفيف لعمليات المرتجعات.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات المرتجعات (`/returns`, `/returns/{id}`).
* `[NEW]` `backend/resources/js/views/Returns/ReturnsView.vue` - شاشة سجل المرتجعات والبحث والفلترة والمعاينة والأرشفة.
* `[NEW]` `backend/resources/js/views/Returns/CreateReturnView.vue` - شاشة تسجيل المرتجع واختيار العملاء/الموردين وجدول البنود الديناميكي.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات المرتجعات في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - تفعيل رابط المرتجعات في القائمة الجانبية.
* `[NEW]` `backend/tests/Feature/Api/ReturnsApiTest.php` - اختبارات Feature شاملة لمرتجعات المبيعات والمشتريات.

## 2. القرارات التقنية:
* التحقق التام عبر `StoreReturnRequest` بدون أي كود تحقق سطحي داخل الكنترولر.
* معالجة العمليات المالية والمخزنية بدقة `DECIMAL(12,3)` و `bcmath` داخل `DB::transaction()`.
* زيادة رصيد المخزن في مرتجع المبيعات (`sales_return_in`) وإنقاصه في مرتجع المشتريات (`purchase_return_out`).

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `ReturnsApiTest` بنجاح 100% (5/5 tests passed, 23 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (74/74 tests passed, 435 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 2.43 ثانية).
