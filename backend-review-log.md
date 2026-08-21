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
1. `Api\DashboardApiController` (تمت مراجعته)
2. `Auth\SuperAdminAuthController` (تمت مراجعته)
3. `DashboardController` (تمت مراجعته)
4. `SuperAdminController` (تمت مراجعته)

---

## `Auth\SuperAdminAuthController` — بتاريخ 2026-08-21
### الحالة قبل المراجعة
- **Fat Controller**: احتواء ميثود `login()` على منطق التحقق من كلمات المرور، فحص صلاحيات السوبر أدمن، وتسجيل سجلات النشاط في الكنترولر مباشرة.
- **غياب نمط Action و DTO مخصص**: تمرير مصفوفات `$credentials` بدلاً من كائن DTO محكم النوع.
- **تشتت الـ Rate Limiting**: تنفيذ مباشر ومكرر لـ Rate Limiter قبل معالجة الاستثناءات.

### التعديلات اللي اتطبقت
- **Single Action Pattern**: إنشاء كلاس [`SuperAdminLoginAction`](file:///d:/projects/sroor/backend/app/Actions/Auth/SuperAdminLoginAction.php) لعزل منطق تسجيل الدخول، التحقق من كلمات المرور المشفرة عبر Hash، فحص الصلاحيات المركزية، وتسجيل الـ Audit Logs تلقائياً.
- **DTOs**: تمرير بيانات الاعتماد عبر كائن [`LoginDTO`](file:///d:/projects/sroor/backend/app/DTOs/Auth/LoginDTO.php).
- **Form Request**: استخدام [`LoginRequest`](file:///d:/projects/sroor/backend/app/Http/Requests/Auth/LoginRequest.php) المزود بحماية Rate Limiter لمنع هجمات التخمين Brute-force.
- **الترجمة الكاملة**: استخراج رسائل تدقيق الدخول وسجلات النشاط إلى `lang/ar/super.php` و `lang/en/super.php` (`login_failed_log`, `unauthorized_attempt_log`, `login_success_log`).
- **تحويل الكنترولر إلى Lean Controller**: أصبح الكنترولر مسؤولاً فقط عن توجيه تدفق الـ HTTP وإعادة التوجيه (Redirect / Invalidate Session).

### تكرار محتمل مع Controllers تانية
- توحيد إجراءات تسجيل الدخول مع `AuthenticatedSessionController` الخاص بالمستأجرين عبر DTOs والـ Rate Limiters الموحدة.

### ملاحظات
- اختبارات المصادقة المركزية والـ Multi-tenant تمر بنجاح تام 100%.

---

## `Api\DashboardApiController` — بتاريخ 2026-08-21
### الحالة قبل المراجعة
- **Fat Controller**: احتواء ميثود `index()` على استعلامات قاعدة بيانات مباشرة متفرقة لحساب الإيرادات والمصروفات والـ COGS وورديات الكاش وسجلات التدقيق.
- **غياب API Resources**: إرجاع مصفوفات بدائية بدون محولات (Transformers/Resources).
- **حسابات مالية مبعثرة**: حسابات الـ COGS والإيرادات كانت تعتمد على استعلامات متباينة دون ضبط دقيق لـ `DECIMAL(12,3)`.
- **نصوص ثابتة (Hardcoded Strings)**: استخدام نصوص مثل `'عميل نقدي'` و `'النظام'`.

### التعديلات اللي اتطبقت
- **Single Action Pattern**: استخراج كافة استعلامات وحسابات المؤشرات في كلاس Action مستقل: [`GetDashboardApiOverviewAction`](file:///d:/projects/sroor/backend/app/Actions/Dashboard/GetDashboardApiOverviewAction.php).
- **تطبيق API Resources الشامل**:
  - إنشاء [`DashboardOverviewResource`](file:///d:/projects/sroor/backend/app/Http/Resources/Api/DashboardOverviewResource.php).
  - إنشاء واستخدام [`CashShiftResource`](file:///d:/projects/sroor/backend/app/Http/Resources/Api/CashShiftResource.php).
  - إنشاء واستخدام [`ActivityLogResource`](file:///d:/projects/sroor/backend/app/Http/Resources/Api/ActivityLogResource.php).
  - استخدام [`InvoiceSummaryResource`](file:///d:/projects/sroor/backend/app/Http/Resources/InvoiceSummaryResource.php).
- **الدقة المالية**: تطبيق دوال `bcmath` بدقة 3 خانات عشرية `DECIMAL(12,3)`.
- **الترجمة الكاملة**: استبدال كافة النصوص الثابتة بمفاتيح `lang/ar/common.php` و `lang/en/common.php`.
- **Feature Testing**: إنشاء اختبار موجه شامل [`DashboardApiControllerTest`](file:///d:/projects/sroor/backend/tests/Feature/DashboardApiControllerTest.php) يمر بنجاح تام.

### تكرار محتمل مع Controllers تانية
- توحيد كلاسات الـ Resources مع `DashboardController` الخاص بواجهات Vue/Inertia.

### ملاحظات
- كافة الاختبارات تمر بنجاح بنسبة 100%.

---

## `SuperAdminController` — بتاريخ 2026-08-21
### الحالة قبل المراجعة
- غياب Form Requests مخصصة في عمليات `overrideFeature`, `toggleStatus`, `impersonateTenant`.
- استدعاء مباشر لـ `env('CENTRAL_DOMAIN')`.
- تمرير موديلات Eloquent مباشرة في واجهة إنشاء المستأجر دون تنظيف أو تحويل عبر الـ Resources.
- نصوص استثناء ثابتة داخل `ImpersonateTenantAction`.

### التعديلات اللي اتطبقت
- إنشاء وتطبيق Form Requests مخصصة ومحمية بالصلاحيات.
- استخدام `PlanResource::collection()` لتنسيق الباقات المتاحة للمنصة.
- استخدام `config('tenancy.central_domains.0')` بدلاً من `env()`.
- الاعتماد على واجهة `SuperAdminDashboardAnalyticsInterface` و Actions النمط الفردي.
- استخراج الترجمات بالكامل إلى `lang/ar/super.php` و `lang/en/super.php`.

### تكرار محتمل مع Controllers تانية
- توحيد قراءة النطاقات المركزية `central_domains` مع `TenantProvisionerService` و `DashboardController`.
- توحيد تنسيق الباقات والمستأجرين عبر `PlanResource` و `TenantResource`.

### ملاحظات
- تم التحقق من كافة اختبارات الـ SOLID لـ SuperAdmin و POS وتمر بنجاح 100%.

---
**آخر Controller تمت مراجعته: `Auth\SuperAdminAuthController`**
**التالي في الترتيب الأبجدي: `DashboardController`**
