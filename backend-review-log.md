# 📋 سجل مراجعة الـ Backend المعماري (Backend Review Log)

## Patterns المكتشفة في المشروع (المراجعة المعمارية الشاملة)
- **Repository**: غير موجود. لا توجد طبقة `Repositories/`، ويعتمد المشروع على Eloquent Query Builder داخل الـ Actions والـ Services وفلاتر الـ Pipeline تماشياً مع معايير الـ Lean Architecture.
- **Service Layer**: موجود ومكتمل بقوة في `app/Services/` (يحتوي 24 كلاس خدمة للمنطق المشترك مثل `DashboardAnalyticsService`, `SuperAdminAnalyticsService`, `InvoiceService`, `TreasuryService`, إلخ).
- **Actions**: المعيار الأساسي للعمليات في `app/Actions/{Domain}/`، حيث كل Action يمتلك دالة واحدة فقط `execute()`.
- **Form Requests**: تم تطبيقه بنسبة 100% في `app/Http/Requests/` واستئصال أي كود `$request->validate()` من الكنترولرز تماشياً مع مبدأ Single Responsibility والقاعدة الصارمة في `AGENTS.md`.
- **API Resources**: تم اعتماده بنسبة 100% في `app/Http/Resources/` لتنظيف وتنسيق استجابات الـ API ولوحات التحكم وتزويدها بالصلاحيات وتوحيد صيغة البيانات.
- **DTOs**: موجود في `app/DTOs/` لنقل البيانات المحكمة النوع بين الكنترولرز والـ Actions.
- **Multi-tenancy handling**: يعتمد على `stancl/tenancy v3.10` مع عزل كامل في `routes/tenant.php` والـ Scopes والـ Observers و `TenantFeatureManager`.
- **Authorization approach**: يعتمد على `spatie/laravel-permission v8.3` مع Middlewares `can:*` وتجاوز تلقائي للـ Admin في `AppServiceProvider::boot()`.
- **قواعد التسمية والدقة**: التزام صارم بـ PascalCase، دقة مالية `DECIMAL(12,3)` ودوال `bcmath`، ومنع النصوص الثابتة واعتماد الترجمة التامة `lang/ar/` و `lang/en/`.

---

## مراجعة Controllers مجموعة Dashboard و Super Dashboard (الترتيب الأبجدي)

قائمة الـ Controllers في المجموعة:
1. `Api\DashboardApiController` (تمت مراجعته وتطبيق الـ API Resources)
2. `Auth\SuperAdminAuthController` (تمت مراجعته)
3. `DashboardController` (تمت مراجعته)
4. `SuperAdminController` (تمت مراجعته)

---

## `Api\DashboardApiController` — بتاريخ 2026-08-21
### الحالة قبل المراجعة
- **Fat Controller**: احتواء ميثود `index()` على استعلامات قاعدة بيانات مباشرة متفرقة لحساب الإيرادات والمصروفات والـ COGS وورديات الكاش وسجلات التدقيق.
- **غياب API Resources**: إرجاع مصفوفات بدائية بدون محولات (Transformers/Resources)، مما يسبب تكرار الكود وعدم اتساق حقول الـ API.
- **حسابات مالية مبعثرة**: حسابات الـ COGS والإيرادات كانت تعتمد على استعلامات متباينة دون ضبط دقيق لـ `DECIMAL(12,3)`.
- **نصوص ثابتة (Hardcoded Strings)**: استخدام نصوص مثل `'عميل نقدي'` و `'النظام'` داخل استجابة الـ JSON مباشرة.

### التعديلات اللي اتطبقت
- **Single Action Pattern**: استخراج كافة استعلامات وحسابات المؤشرات في كلاس Action مستقل: [`GetDashboardApiOverviewAction`](file:///d:/projects/sroor/backend/app/Actions/Dashboard/GetDashboardApiOverviewAction.php).
- **تطبيق API Resources الشامل**:
  - إنشاء [`DashboardOverviewResource`](file:///d:/projects/sroor/backend/app/Http/Resources/Api/DashboardOverviewResource.php) لتغليف وتوحيد استجابة لوحة التحكم.
  - إنشاء واستخدام [`CashShiftResource`](file:///d:/projects/sroor/backend/app/Http/Resources/Api/CashShiftResource.php) لبيانات وردية الكاش المفتوحة.
  - إنشاء واستخدام [`ActivityLogResource`](file:///d:/projects/sroor/backend/app/Http/Resources/Api/ActivityLogResource.php) لسجلات تدقيق العمليات.
  - استخدام [`InvoiceSummaryResource`](file:///d:/projects/sroor/backend/app/Http/Resources/InvoiceSummaryResource.php) لأحدث الفواتير المسجلة.
- **الدقة المالية**: تطبيق دوال `bcmath` (`bcsub`, `bcmul`, `bcdiv`, `bcadd`) بدقة 3 خانات عشرية `DECIMAL(12,3)`.
- **الترجمة الكاملة**: استبدال كافة النصوص الثابتة بمفاتيح `lang/ar/common.php` و `lang/en/common.php` (`__('common.system')`, `__('common.unspecified')`).
- **Feature Testing**: إنشاء اختبار موجه شامل [`DashboardApiControllerTest`](file:///d:/projects/sroor/backend/tests/Feature/DashboardApiControllerTest.php) يمر بنجاح تام (12 Assertions).

### تكرار محتمل مع Controllers تانية
- توحيد كلاسات الـ Resources مع `DashboardController` الخاص بواجهات Vue/Inertia لتفادي ازدواجية بنية البيانات بين الويب وتطبيق الموبايل.

### ملاحظات
- كافة الاختبارات (30 Feature Tests تشمل 117 Assertions) تمر بنجاح بنسبة 100%.

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
**آخر Controller تمت مراجعته: `Api\DashboardApiController`**
**التالي في الترتيب الأبجدي: `Auth\SuperAdminAuthController`**
