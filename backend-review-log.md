# 📋 سجل مراجعة الـ Backend المعماري (Backend Review Log)

## مراجعة Backend بتاريخ 2026-08-21
### Patterns المكتشفة في المشروع (المراجعة الأولى)
- **Repository**: غير موجود. لا توجد طبقة `Repositories/`، ويعتمد المشروع على Eloquent Query Builder داخل الـ Actions والـ Services وفلاتر الـ Pipeline تماشياً مع معايير الـ Lean Architecture.
- **Service Layer**: موجود ومكتمل بقوة في `app/Services/` (يحتوي 24 كلاس خدمة للمنطق المشترك مثل `DashboardAnalyticsService`, `SuperAdminAnalyticsService`, `InvoiceService`, `TreasuryService`, إلخ).
- **Actions**: المعيار الأساسي للعمليات في `app/Actions/{Domain}/`، حيث كل Action يمتلك دالة واحدة فقط `execute()`.
- **Form Requests**: موجود في `app/Http/Requests/` ويُستخدم للتحقق الصارم من كل الـ Inputs.
- **API Resources**: موجود في `app/Http/Resources/` لتنظيف وتنسيق استجابات Inertia و API وتزويدها بالصلاحيات.
- **DTOs**: موجود في `app/DTOs/` لنقل البيانات المحكمة النوع بين الكنترولرز والـ Actions.
- **Multi-tenancy handling**: يعتمد على `stancl/tenancy v3.10` مع عزل كامل في `routes/tenant.php` والـ Scopes والـ Observers و `TenantFeatureManager`.
- **Authorization approach**: يعتمد على `spatie/laravel-permission v8.3` مع Middlewares `can:*` وتجاوز تلقائي للـ Admin في `AppServiceProvider::boot()`.
- **قواعد التسمية والدقة**: التزام صارم بـ PascalCase، دقة مالية `DECIMAL(12,3)` ودوال `bcmath`، ومنع النصوص الثابتة واعتماد الترجمة التامة `lang/ar/` و `lang/en/`.

### الـ Controllers اللي اتراجعت (Dashboard / Super Dashboard)
- **`DashboardController`**:
  - *كان فيه إيه*: تكرار استدعاء `GetTenantDashboardAnalyticsAction` بعدد 6 مرات في طلبات Inertia Defer لنفس المجموعة (`dashboardData`).
  - *اتحل إزاي*: تطبيق **Request Memoization** داخل `GetTenantDashboardAnalyticsAction`، مما أدى لتقليص الاستعلامات المكررة من 6 مرات إلى مرة واحدة فقط للطلب.
- **`SuperAdminController`**:
  - *كان فيه إيه*:
    1. غياب Form Requests في ميثودز `overrideFeature`, `toggleStatus`, `impersonateTenant`.
    2. استعلامات باقات مكررة في `tenants()`.
    3. تمرير Eloquent Models مباشرة في `createTenant()`.
    4. استخدام `env('CENTRAL_DOMAIN')` مباشرة بدلاً من `config()`.
    5. نص ثابت hardcoded في `ImpersonateTenantAction`.
  - *اتحل إزاي*:
    1. إنشاء واستخدام `OverrideTenantFeatureRequest`, `ToggleTenantStatusRequest`, `ImpersonateTenantRequest`.
    2. تنظيف ميثود `tenants()` ومنع استعلام الباقات المزدوج.
    3. استخدام `PlanResource::collection($plans)` في `createTenant()`.
    4. استبدال `env()` بـ `config('tenancy.central_domains.0')`.
    5. استخراج الترجمة إلى `lang/ar/super.php` و `lang/en/super.php` واستخدام `__('super.no_active_user_in_store')`.

### تكرار منطق بين Dashboard و Super Dashboard اتحل
- توحيد قراءة النطاقات المركزية `central_domains` عبر `config()` ومنع تشتت القراءات من `env()`.
- توحيد تنسيق الباقات والمستأجرين عبر `PlanResource` و `TenantResource`.

### مشاكل N+1 / Performance اتحلت
- إزالة تنفيذ استعلامات الداشبورد 6 مرات في Inertia Defer عبر الـ Request Memoization في `GetTenantDashboardAnalyticsAction`.
- استعلام الباقات أصبح منضبطاً عبر الـ Resources دون تحميل مزدوج.

### ملاحظات لسه محتاجة متابعة
- كافة اختبارات الـ SOLID لـ SuperAdmin و POS تعمل وتمر بنجاح 100%.
