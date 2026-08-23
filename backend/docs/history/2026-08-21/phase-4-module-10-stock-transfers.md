# سجل تعديل: المرحلة 4 - Module 10: التحويلات المخزنية بين الفروع والمخازن (Stock Transfers)
* **التاريخ والوقت:** 2026-08-21 15:27
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول التحويلات المخزنية بين الفروع والمخازن من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، API Resources، Form Requests ومنع أي `validate()` داخل الكنترولرز، وبناء شاشات StockTransfersView و CreateStockTransferView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Transfers/CreateTransferDTO.php` - DTO محكم النوع لبيانات التحويل المخزني.
* `[NEW]` `backend/app/DTOs/Transfers/CancelTransferDTO.php` - DTO لإلغاء التحويل وعكس الرصيد.
* `[NEW]` `backend/app/Actions/Transfers/CreateStockTransferAction.php` - Single Action لاعتماد التحويل المخزني ونقل الأرصدة وتسجيل حركات المخزون.
* `[NEW]` `backend/app/Actions/Transfers/CancelStockTransferAction.php` - Single Action لإلغاء التحويل وعكس حركة المخزون بأمان للمخزن المصدر.
* `[NEW]` `backend/app/Http/Resources/StockTransferResource.php` - محول بيانات أذونات التحويل المخزني للـ API.
* `[NEW]` `backend/app/Http/Resources/StockTransferItemResource.php` - محول بيانات بنود التحويل المخزني للـ API.
* `[NEW]` `backend/app/Http/Requests/CancelStockTransferRequest.php` - Form Request لإلغاء التحويلات.
* `[MODIFIED]` `backend/app/Http/Requests/StoreStockTransferRequest.php` - تحديث الصلاحيات والتحقق الصارم.
* `[REFACTORED]` `backend/app/Http/Controllers/Api/StockTransferController.php` - متحكم API نقي وخفيف لعمليات التحويل المخزني.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات التحويل المخزني (`/transfers`, `/transfers/{id}`, `/transfers/{id}/cancel`).
* `[NEW]` `backend/resources/js/views/StockTransfers/StockTransfersView.vue` - شاشة سجل أذونات التحويل والبحث والفلترة والمعاينة والإلغاء.
* `[NEW]` `backend/resources/js/views/StockTransfers/CreateStockTransferView.vue` - شاشة إنشاء وتنفيذ إذن التحويل واختيار الفروع والأصناف.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات التحويل المخزني في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - تفعيل رابط التحويلات المخزنية في القائمة الجانبية.
* `[NEW]` `backend/tests/Feature/Api/StockTransfersApiTest.php` - اختبارات Feature شاملة للتحويلات المخزنية.

## 2. القرارات التقنية:
* التحقق التام عبر Form Requests وفصلها تماماً عن الكنترولر.
* معالجة العمليات المخزنية بدقة `DECIMAL(12,3)` و `bcmath` داخل `DB::transaction()`.
* قفل سطري `lockForUpdate()` على رصيد المخزن المصدر والمستلم لمنع الـ Race Conditions.
* تسجيل حركات المخزون المزدوجة (`transfer_out` و `transfer_in`) وعكسها عند الإلغاء (`transfer_reversal_out` و `transfer_reversal_in`).

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `StockTransfersApiTest` بنجاح 100% (4/4 tests passed, 25 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (78/78 tests passed, 460 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 3.02 ثانية).
