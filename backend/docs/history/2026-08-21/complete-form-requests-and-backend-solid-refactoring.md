# سجل تعديل: تعميم Form Requests وتنظيف Controllers الباك إند بالكامل وفق مبادئ SOLID
* **التاريخ والوقت:** 2026-08-21 04:25
* **الدور المفعل:** Backend Architect Agent
* **الهدف:** استئصال كافة أكواد التحقق المباشر `$request->validate()` من جميع الـ Controllers وتحويلها إلى 27 كلاس `FormRequest` مخصص، وتطبيق مبادئ SOLID و Zero Hardcoded Strings والدقة المالية التامة `bcmath`.

## 1. الملفات المعدلة والمنشأة:
### كلاسات الـ Form Requests الجديدة (`app/Http/Requests/`):
* `[NEW]` `app/Http/Requests/StoreExpenseRequest.php`
* `[NEW]` `app/Http/Requests/UpdateExpenseRequest.php`
* `[NEW]` `app/Http/Requests/OpenShiftRequest.php`
* `[NEW]` `app/Http/Requests/CloseShiftRequest.php`
* `[NEW]` `app/Http/Requests/StoreDailyJournalExpenseRequest.php`
* `[NEW]` `app/Http/Requests/StoreCustomerRequest.php`
* `[NEW]` `app/Http/Requests/UpdateCustomerRequest.php`
* `[NEW]` `app/Http/Requests/CollectCustomerPaymentRequest.php`
* `[NEW]` `app/Http/Requests/StoreSupplierRequest.php`
* `[NEW]` `app/Http/Requests/UpdateSupplierRequest.php`
* `[NEW]` `app/Http/Requests/PaySupplierRequest.php`
* `[NEW]` `app/Http/Requests/StoreItemRequest.php`
* `[NEW]` `app/Http/Requests/UpdateItemRequest.php`
* `[NEW]` `app/Http/Requests/UpdateInvoiceRequest.php`
* `[NEW]` `app/Http/Requests/CancelInvoiceRequest.php`
* `[NEW]` `app/Http/Requests/StorePurchaseRequest.php`
* `[NEW]` `app/Http/Requests/StoreReturnRequest.php`
* `[NEW]` `app/Http/Requests/StoreStockTransferRequest.php`
* `[NEW]` `app/Http/Requests/StoreStoreRequest.php`
* `[NEW]` `app/Http/Requests/UpdateStoreRequest.php`
* `[NEW]` `app/Http/Requests/AssignStoreUsersRequest.php`
* `[NEW]` `app/Http/Requests/StoreUserRequest.php`
* `[NEW]` `app/Http/Requests/UpdateUserRequest.php`
* `[NEW]` `app/Http/Requests/UpdateRolePermissionsRequest.php`
* `[NEW]` `app/Http/Requests/UpdateSettingsRequest.php`
* `[NEW]` `app/Http/Requests/UpdateProfileRequest.php`
* `[NEW]` `app/Http/Requests/CreateBlenderInvoiceRequest.php`

### الـ Controllers والخدمات المعدلة:
* `[MODIFIED]` `app/Http/Controllers/ExpenseController.php`
* `[MODIFIED]` `app/Http/Controllers/DailyJournalController.php`
* `[MODIFIED]` `app/Http/Controllers/CustomerController.php`
* `[MODIFIED]` `app/Http/Controllers/SupplierController.php`
* `[MODIFIED]` `app/Http/Controllers/ItemController.php`
* `[MODIFIED]` `app/Http/Controllers/InvoiceController.php`
* `[MODIFIED]` `app/Http/Controllers/PurchaseController.php`
* `[MODIFIED]` `app/Http/Controllers/ReturnController.php`
* `[MODIFIED]` `app/Http/Controllers/StockTransferController.php`
* `[MODIFIED]` `app/Http/Controllers/StoreController.php`
* `[MODIFIED]` `app/Http/Controllers/UserController.php`
* `[MODIFIED]` `app/Http/Controllers/RoleController.php`
* `[MODIFIED]` `app/Http/Controllers/SettingController.php`
* `[MODIFIED]` `app/Http/Controllers/ProfileController.php`
* `[MODIFIED]` `app/Http/Controllers/CoffeeBlenderController.php`
* `[MODIFIED]` `app/Http/Controllers/Auth/SuperAdminAuthController.php`
* `[MODIFIED]` `app/Http/Controllers/Api/ExpenseController.php`
* `[MODIFIED]` `app/Services/DashboardAnalyticsService.php`
* `[MODIFIED]` `app/Services/SuperAdminAnalyticsService.php`
* `[MODIFIED]` `app/Services/TenantProvisionerService.php`
* `[MODIFIED]` `lang/ar/nav.php` & `lang/en/nav.php`
* `[MODIFIED]` `lang/ar/super.php` & `lang/en/super.php`
* `[MODIFIED]` `backend-review-log.md`

## 2. القرارات التقنية:
* إزالة 100% من التحقق المباشر `$request->validate()` من الكنترولرز تماشياً مع الـ Single Responsibility Principle (SRP) وقواعد `AGENTS.md`.
* استخدام `bcmath` في حساب مؤشرات الـ MRR في المنصة المركزية `SuperAdminAnalyticsService`.
* استبدال كافة استدعاءات `env()` بـ `config()`.
* استخراج كافة النصوص الثابتة إلى ملفات الترجمة الرسمية للغتين العربية والإنجليزية.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وتمرير كافة الاختبارات المعمارية بنجاح 100%.
* [x] فحص الحفظ والتراجع Transaction Rollback.
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
