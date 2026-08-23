# سجل تعديل: المرحلة 4 - Module 05: الأصناف وحركات المخزون والنواقص (Items & Stock Movements)
* **التاريخ والوقت:** 2026-08-21 15:11
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول الأصناف وكروت الصنف وحركات المخزون ورادار النواقص من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، وبناء شاشتي ItemsView و ItemMovementsView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Items/ItemDTO.php` - DTO لكروت الأصناف والأسعار وحدود المخزون.
* `[NEW]` `backend/app/DTOs/Items/AdjustStockDTO.php` - DTO لتسويات وجرد المخزون والهدر.
* `[NEW]` `backend/app/Actions/Items/CreateItemAction.php` - Single Action لإنشاء الصنف وتهيئة أرصدة الفروع تلقائياً داخل Transaction.
* `[NEW]` `backend/app/Actions/Items/UpdateItemAction.php` - Single Action لتعديل بيانات الصنف.
* `[NEW]` `backend/app/Actions/Items/DeleteItemAction.php` - Single Action لحذف الصنف مع فحص الموانع التشغيلية.
* `[NEW]` `backend/app/Actions/Items/ToggleItemActiveAction.php` - Single Action لتفعيل وتعطيل الصنف.
* `[NEW]` `backend/app/Actions/Items/AdjustItemStockAction.php` - Single Action لتسوية المخزون مع قفل سطري `lockForUpdate` وحساب `bcmath` وتسجيل حركة المخزون `StockMovement`.
* `[NEW]` `backend/app/Actions/Items/GetItemMovementsAction.php` - Single Action لكشف حركات المخزون التفصيلي.
* `[NEW]` `backend/app/Http/Requests/AdjustStockRequest.php` - Form Request للتحقق من بيانات التسوية المخزنية.
* `[MODIFIED]` `backend/app/Http/Requests/StoreItemRequest.php` - تحديث صلاحيات الحفظ للأدمن.
* `[MODIFIED]` `backend/app/Http/Requests/UpdateItemRequest.php` - تحديث صلاحيات التعديل للأدمن.
* `[NEW]` `backend/app/Http/Resources/ItemResource.php` - تنسيق بيانات الأصناف وأسعار وأرصدة الفروع.
* `[NEW]` `backend/app/Http/Resources/StockMovementResource.php` - تنسيق بيانات حركات المخزون.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/ItemController.php` - متحكم API متكامل للأصناف والتسويات ورادار النواقص.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات الأصناف، النواقص، التسويات، وكشف الحركات.
* `[NEW]` `backend/resources/js/views/Items/ItemsView.vue` - شاشة كروت الأصناف والتقييم المالي والتسويات السريعة.
* `[NEW]` `backend/resources/js/views/Items/ItemMovementsView.vue` - شاشة كشف حركات المخزون التفصيلي للصنف.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات الأصناف وحركات المخزون في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - تفعيل رابط الأصناف والمخزون في القائمة الجانبية.
* `[NEW]` `backend/tests/Feature/Api/ItemsApiTest.php` - اختبارات Feature شاملة لجميع عمليات الأصناف والتسويات والنواقص.

## 2. القرارات التقنية:
* تطبيق القفل السطري `lockForUpdate()` عند أي تعديل أو تسوية في رصيد الصنف `StoreStock` و `Item`.
* حساب كافة الكميات والأرصدة والتسويات بدوال `bcmath` (`bcadd`, `bcsub`).
* دعم رادار النواقص (`lowStock`) اللحظي مع حساب العجز والكمية المقترحة لإعادة الطلب.

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `ItemsApiTest` بنجاح 100% (9/9 tests passed, 36 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (51/51 tests passed, 308 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 4.39 ثانية).
