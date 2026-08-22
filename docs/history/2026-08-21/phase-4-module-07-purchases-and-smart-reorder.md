# سجل تعديل: المرحلة 4 - Module 07: المشتريات والتوريد وإعادة الطلب الذكي (Purchases & Smart Reorder)
* **التاريخ والوقت:** 2026-08-21 15:17
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول المشتريات وتوريد الخامات ورادار إعادة الطلب الذكي بالذكاء الاصطناعي من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، وبناء شاشات PurchasesView و CreatePurchaseView و SmartReorderView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Purchases/PurchaseDTO.php` - DTO لفواتير المشتريات وتوريد الخامات وتوزيع التكاليف.
* `[NEW]` `backend/app/DTOs/Purchases/CancelPurchaseDTO.php` - DTO لإلغاء فواتير الشراء وعكس المخزون.
* `[NEW]` `backend/app/Actions/Purchases/CreatePurchaseAction.php` - Single Action لتسجيل واعتماد فاتورة المشتريات وتوزيع التكاليف وتحديث المخزون والـ WAC.
* `[NEW]` `backend/app/Actions/Purchases/CancelPurchaseAction.php` - Single Action لإلغاء الفاتورة وفحص كفاية الرصيد وعكس المخزون والمديونية.
* `[NEW]` `backend/app/Actions/Purchases/GetSmartReorderSuggestionsAction.php` - Single Action لمحرك ورادار إعادة الطلب التنبؤي ومعدلات السحب اليومي.
* `[NEW]` `backend/app/Http/Resources/PurchaseResource.php` - تنسيق بيانات فواتير المشتريات والمجاميع المالية للـ API.
* `[NEW]` `backend/app/Http/Resources/PurchaseItemResource.php` - تنسيق بنود وأصناف التوريد والتكاليف المحملة.
* `[MODIFIED]` `backend/app/Http/Requests/StorePurchaseRequest.php` - تحديث الصلاحيات والأدوار وقواعد التحقق.
* `[REFACTORED]` `backend/app/Http/Controllers/Api/PurchaseController.php` - متحكم API متكامل للمشتريات وإعادة الطلب الذكي.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات المشتريات ورادار إعادة الطلب الذكي.
* `[NEW]` `backend/resources/js/views/Purchases/PurchasesView.vue` - شاشة سجل فواتير المشتريات والمعاينة التفصيلية.
* `[NEW]` `backend/resources/js/views/Purchases/CreatePurchaseView.vue` - شاشة إنشاء فاتورة مشتريات وتوريد بنود جديدة.
* `[NEW]` `backend/resources/js/views/Purchases/SmartReorderView.vue` - شاشة رادار إعادة الطلب الذكي بالذكاء الاصطناعي.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات المشتريات في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - تفعيل رابط المشتريات والتوريد في القائمة الجانبية.
* `[NEW]` `backend/tests/Feature/Api/PurchasesApiTest.php` - اختبارات Feature شاملة لجميع عمليات المشتريات والتوريد والرادار الذكي.

## 2. القرارات التقنية:
* معالجة العمليات الحسابية للمشتريات بدقة `DECIMAL(12,3)` و `bcmath`.
* قفل سطري آمن `lockForUpdate()` عند تحديث رصيد المخزن ومحرك التكلفة المرجحة WAC لمنع الـ Race Conditions.
* التحقق من كفاية الرصيد في المخزن الرئيسي والفرعي قبل السماح بإلغاء الفاتورة لمنع الأرصدة السالبة.
* ربط رادار إعادة الطلب الذكي بإنشاء أوامر التوريد المجمعة عبر معلمة `?prefill=`.

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `PurchasesApiTest` بنجاح 100% (5/5 tests passed, 31 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (62/62 tests passed, 366 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 2.77 ثانية).
