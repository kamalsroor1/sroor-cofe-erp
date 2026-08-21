# 📋 سجل مراجعة الـ Backend المعماري (Backend Review Log)

## Patterns المكتشفة في المشروع (المراجعة المعمارية الشاملة)
- **Repository**: غير موجود. لا توجد طبقة `Repositories/`، ويعتمد المشروع على Eloquent Query Builder داخل الـ Actions والـ Services وفلاتر الـ Pipeline تماشياً مع معايير الـ Lean Architecture.
- **Service Layer**: موجود ومكتمل بقوة في `app/Services/` (يحتوي 24 كلاس خدمة للمنطق المشترك مثل `DashboardAnalyticsService`, `SuperAdminAnalyticsService`, `InvoiceService`, `TreasuryService`, إلخ).
- **Actions**: المعيار الأساسي للعمليات في `app/Actions/{Domain}/`، حيث كل Action يمتلك دالة واحدة فقط `execute()`.
- **Form Requests**: تم تطبيقه بنسبة 100% في `app/Http/Requests/` واستئصال أي كود `$request->validate()` من الكنترولرز تماشياً مع مبدأ Single Responsibility والقاعدة الصارمة في `AGENTS.md`.
- **API Resources**: موجود في `app/Http/Resources/` لتنظيف وتنسيق استجابات Inertia و API وتزويدها بالصلاحيات.
- **DTOs**: موجود في `app/DTOs/` لنقل البيانات المحكمة النوع بين الكنترولرز والـ Actions.
- **Multi-tenancy handling**: يعتمد على `stancl/tenancy v3.10` مع عزل كامل في `routes/tenant.php` والـ Scopes والـ Observers و `TenantFeatureManager`.
- **Authorization approach**: يعتمد على `spatie/laravel-permission v8.3` مع Middlewares `can:*` وتجاوز تلقائي للـ Admin في `AppServiceProvider::boot()`.
- **قواعد التسمية والدقة**: التزام صارم بـ PascalCase، دقة مالية `DECIMAL(12,3)` ودوال `bcmath`، ومنع النصوص الثابتة واعتماد الترجمة التامة `lang/ar/` و `lang/en/`.

---

## مراجعة Controllers مجموعة Dashboard و Super Dashboard (الترتيب الأبجدي)

قائمة الـ Controllers في المجموعة:
1. `Api\DashboardApiController` (تمت مراجعته)
2. `Auth\SuperAdminAuthController` (تمت مراجعته)
3. `DashboardController` (تمت مراجعته)
4. `SuperAdminController` (تمت مراجعته)

---

## `SuperAdminController` — بتاريخ 2026-08-21
### الحالة قبل المراجعة
- غياب Form Requests مخصصة في عمليات `overrideFeature`, `toggleStatus`, `impersonateTenant` وقراءة المدخلات مباشرة من `$request`.
- استدعاء مباشر لـ `env('CENTRAL_DOMAIN')` كان يهدد بانهيار المنصة المركزية عند تنفيذ `config:cache`.
- تمرير موديلات Eloquent مباشرة في واجهة إنشاء المستأجر دون تنظيف أو تحويل عبر الـ Resources.
- نصوص استثناء ثابتة داخل `ImpersonateTenantAction`.

### التعديلات اللي اتطبقت
- إنشاء وتطبيق Form Requests مخصصة ومحمية بالصلاحيات:
  - `OverrideTenantFeatureRequest`
  - `ToggleTenantStatusRequest`
  - `ImpersonateTenantRequest`
  - `StoreTenantRequest`
  - `UpdatePlanRequest`
- استخدام `PlanResource::collection()` لتنسيق الباقات المتاحة للمنصة.
- استخدام `config('tenancy.central_domains.0')` بدلاً من `env()`.
- الاعتماد على واجهة `SuperAdminDashboardAnalyticsInterface` و Actions النمط الفردي (`GetTenantsIndexDataAction`, `ProvisionTenantAction`, `ToggleTenantStatusAction`, `OverrideTenantFeatureAction`, `GetSuperAdminPlansDataAction`, `UpdatePlanAction`, `ImpersonateTenantAction`).
- استخراج الترجمات بالكامل إلى `lang/ar/super.php` و `lang/en/super.php`.

### تكرار محتمل مع Controllers تانية
- توحيد قراءة النطاقات المركزية `central_domains` مع `TenantProvisionerService` و `DashboardController`.
- توحيد تنسيق الباقات والمستأجرين عبر `PlanResource` و `TenantResource`.

### ملاحظات
- تم التحقق من كافة اختبارات الـ SOLID لـ SuperAdmin و POS وتمر بنجاح 100%.

---
**آخر Controller تمت مراجعته: `SuperAdminController`**
**التالي في الترتيب الأبجدي: لا يوجد - اكتملت مراجعة جميع Controllers الـ Dashboard والـ Super Dashboard بالكامل في دورة كاملة.**
