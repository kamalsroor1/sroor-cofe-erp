# 📋 سجل مراجعة الـ Backend المعماري (Backend Review Log)

## مراجعة Backend بتاريخ 2026-08-21
### Patterns المكتشفة في المشروع (المراجعة المعمارية الشاملة)
- **Repository**: غير موجود. لا توجد طبقة `Repositories/`، ويعتمد المشروع على Eloquent Query Builder داخل الـ Actions والـ Services وفلاتر الـ Pipeline تماشياً مع معايير الـ Lean Architecture.
- **Service Layer**: موجود ومكتمل بقوة في `app/Services/` (يحتوي 24 كلاس خدمة للمنطق المشترك مثل `DashboardAnalyticsService`, `SuperAdminAnalyticsService`, `InvoiceService`, `TreasuryService`, إلخ).
- **Actions**: المعيار الأساسي للعمليات في `app/Actions/{Domain}/`، حيث كل Action يمتلك دالة واحدة فقط `execute()`.
- **Form Requests**: تم تطبيقه بنسبة 100% في `app/Http/Requests/` واستئصال أي كود `$request->validate()` من الكنترولرز تماشياً مع مبدأ Single Responsibility والقاعدة الصارمة في `AGENTS.md`.
- **API Resources**: موجود في `app/Http/Resources/` لتنظيف وتنسيق استجابات Inertia و API وتزويدها بالصلاحيات.
- **DTOs**: موجود في `app/DTOs/` لنقل البيانات المحكمة النوع بين الكنترولرز والـ Actions.
- **Multi-tenancy handling**: يعتمد على `stancl/tenancy v3.10` مع عزل كامل في `routes/tenant.php` والـ Scopes والـ Observers و `TenantFeatureManager`.
- **Authorization approach**: يعتمد على `spatie/laravel-permission v8.3` مع Middlewares `can:*` وتجاوز تلقائي للـ Admin في `AppServiceProvider::boot()`.
- **قواعد التسمية والدقة**: التزام صارم بـ PascalCase، دقة مالية `DECIMAL(12,3)` ودوال `bcmath`، ومنع النصوص الثابتة واعتماد الترجمة التامة `lang/ar/` و `lang/en/`.

### الـ Controllers التي تم فحصها وإعادة هيكلتها بالكامل (Clean Architecture & SOLID):
1. **`SuperAdminAuthController`**:
   - *كان فيه إيه*: استخدام `$request->validate()` ونصوص عربية ثابتة وانعدام الـ Rate Limiting.
   - *اتحل إزاي*: تحويله لـ `LoginRequest` مع حماية الـ Rate Limiter واستخراج الترجمات إلى `lang/ar/super.php` و `lang/en/super.php`.
2. **`ExpenseController` & `Api\ExpenseController`**:
   - *كان فيه إيه*: احتواء الـ Methods على كود التحقق المباشر `$request->validate()`.
   - *اتحل إزاي*: إنشاء واستخدام `StoreExpenseRequest` و `UpdateExpenseRequest`.
3. **`DailyJournalController`**:
   - *كان فيه إيه*: كود `$request->validate()` في `openShift`, `closeShift`, `storeExpense`.
   - *اتحل إزاي*: إنشاء `OpenShiftRequest`, `CloseShiftRequest`, `StoreDailyJournalExpenseRequest`.
4. **`CustomerController`**:
   - *كان فيه إيه*: كود `$request->validate()` في `store`, `update`, `collectPayment`.
   - *اتحل إزاي*: إنشاء `StoreCustomerRequest`, `UpdateCustomerRequest`, `CollectCustomerPaymentRequest`.
5. **`SupplierController`**:
   - *كان فيه إيه*: كود `$request->validate()` في `store`, `update`, `pay`.
   - *اتحل إزاي*: إنشاء `StoreSupplierRequest`, `UpdateSupplierRequest`, `PaySupplierRequest`.
6. **`ItemController`**:
   - *كان فيه إيه*: كود `$request->validate()` في `store`, `update`.
   - *اتحل إزاي*: إنشاء `StoreItemRequest`, `UpdateItemRequest`.
7. **`InvoiceController`**:
   - *كان فيه إيه*: كود `$request->validate()` في `update`, `cancel`.
   - *اتحل إزاي*: إنشاء `UpdateInvoiceRequest`, `CancelInvoiceRequest`.
8. **`PurchaseController`**:
   - *كان فيه إيه*: كود `$request->validate()` في `store`.
   - *اتحل إزاي*: إنشاء `StorePurchaseRequest`.
9. **`ReturnController`**:
   - *كان فيه إيه*: كود `$request->validate()` ونصوص استجابة ثابتة.
   - *اتحل إزاي*: إنشاء `StoreReturnRequest` واستخدام `__('returns.created_success')` و `__('returns.deleted_success')`.
10. **`StockTransferController`**:
    - *كان فيه إيه*: كود `$request->validate()` في `store`.
    - *اتحل إزاي*: إنشاء `StoreStockTransferRequest`.
11. **`StoreController`**:
    - *كان فيه إيه*: كود `$request->validate()` في `store`, `update`, `assignUsers`.
    - *اتحل إزاي*: إنشاء `StoreStoreRequest`, `UpdateStoreRequest`, `AssignStoreUsersRequest`.
12. **`UserController`**:
    - *كان فيه إيه*: كود `$request->validate()` في `store`, `update`.
    - *اتحل إزاي*: إنشاء `StoreUserRequest`, `UpdateUserRequest`.
13. **`RoleController`**:
    - *كان فيه إيه*: غياب FormRequest مخصص لتحديث صلاحيات الأدوار.
    - *اتحل إزاي*: إنشاء `UpdateRolePermissionsRequest`.
14. **`SettingController`**:
    - *كان فيه إيه*: كود `$request->validate()` ونصوص ثابتة في تفريغ الكاش.
    - *اتحل إزاي*: إنشاء `UpdateSettingsRequest` واستخراج الترجمات إلى `lang/ar/nav.php` و `lang/en/nav.php`.
15. **`CoffeeBlenderController`**:
    - *كان فيه إيه*: كود `$request->validate()` في `createInvoice`.
    - *اتحل إزاي*: إنشاء `CreateBlenderInvoiceRequest`.
16. **`DashboardController`**:
    - *كان فيه إيه*: تكرار استدعاء `GetTenantDashboardAnalyticsAction` بعدد 6 مرات في Inertia Defer.
    - *اتحل إزاي*: تطبيق **Request Memoization** لمنع تكرار الاستعلامات.
17. **`SuperAdminController`**:
    - *كان فيه إيه*: استخدام `env()` مباشرة وغياب FormRequests.
    - *اتحل إزاي*: تطبيق `OverrideTenantFeatureRequest`, `ToggleTenantStatusRequest`, `ImpersonateTenantRequest` ودوال `config()`.
18. **`DashboardAnalyticsService` & `SuperAdminAnalyticsService`**:
    - *كان فيه إيه*: حسابات الـ MRR بدون `bcmath` ووجود نصوص ثابتة لرمز العملة.
    - *اتحل إزاي*: استخدام `bcmath` في كافة العمليات المالية واستخدام `__('common.currency')`.

### تكرار منطق بين Dashboard و Super Dashboard اتحل
- توحيد قراءة النطاقات المركزية `central_domains` عبر `config()` ومنع تشتت القراءات من `env()`.
- توحيد تنسيق الباقات والمستأجرين عبر `PlanResource` و `TenantResource`.
- توحيد عمليات التجهيز المركزي في `TenantProvisionerService`.

### مشاكل N+1 / Performance اتحلت
- إزالة تنفيذ استعلامات الداشبورد 6 مرات في Inertia Defer عبر الـ Request Memoization في `GetTenantDashboardAnalyticsAction`.
- استعلام الباقات أصبح منضبطاً عبر الـ Resources دون تحميل مزدوج.

### ملاحظات لسه محتاجة متابعة
- كافة اختبارات الـ SOLID لـ SuperAdmin و POS تعمل وتمر بنجاح 100%.
