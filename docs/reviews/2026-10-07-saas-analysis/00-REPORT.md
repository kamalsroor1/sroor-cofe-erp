# تقرير الـ CTO النهائي — مراجعة جاهزية Sroor ERP كـ SaaS متعدد المستأجرين

> **البرنش:** `feature/multi-tenant` · **التاريخ:** 2026-10-07 · **النوع:** مراجعة read-only متعددة الوكلاء (9 أبعاد، كل finding critical/high اتراجع adversarially)
> **الستاك:** Laravel 13 + stancl/tenancy v3 (DB-per-tenant) + Sanctum + Vue 3 SPA + Capacitor (Android) + Electron (Windows)
> **سياسة الأسرار:** التقرير ده **مفيهوش أي قيمة سرية** (باسوردات، tokens، مفاتيح، أرقام الموبايل اللي بتدي صلاحيات). المذكور بس المكان ونوع السر.

---

## 1. الملخص التنفيذي

**هل المنتج جاهز يتباع كـ SaaS؟ لأ، وبصراحة: مش آمن حتى يتعرض على الإنترنت بالشكل الحالي.**

الأساس الهندسي كويس فعلًا: قاعدة بيانات منفصلة لكل مستأجر (stancl v3)، كل الفلوس والكميات `DECIMAL(12,3)` مع `bcmath`، و`DB::transaction` + `lockForUpdate` على المخزون والأرصدة، وطبقات Request → DTO → Action → Resource، وموديل بيانات SaaS جاهز (plans / plan_features / subscriptions / حالة المستأجر)، و306 تست PHPUnit. يعني **المشكلة مش في المعمار، المشكلة في الأسلاك**.

لكن الوضع الحالي فيه أربع فئات بتمنع البيع منعًا قاطعًا:

1. **اختراق كامل من غير باسورد.** `POST /api/v1/auth/quick-login` عام ومن غير throttle، وبيدي token بصلاحية `['*']` لأي مستخدم بالـ id أو الموبايل أو الإيميل. ومعاه `GET /auth/workspace-users` بيعرض قايمة المستخدمين لأي حد. أي حد على الإنترنت يقدر ياخد admin أي مستأجر، ويقدر ياخد الـ super-admin المركزي.
2. **أي مستخدم في أي مستأجر يقدر يبقى super-admin للمنصة كلها**، بتلات طرق مستقلة: رقمين موبايل hardcoded في `Gate::before` (وأي كاشير يقدر يحط الرقم ده لنفسه من `/profile`)، ودور `super_admin` متزرع في كل DB مستأجر وممكن يتعين، وراوتات `/super-admin/*` شغالة جوه سياق المستأجر. ومن هناك يقدر يوقف مستأجرين تانيين، ويمد اشتراكه 10 سنين، ويغير DB config ويشغل migrations.
3. **تسريب بيانات بين المستأجرين وتسريب أسرار السيرفر.** الكاش مشترك بين المستأجرين (تقارير ABC والأرباح وخريطة الصلاحيات)، والـ endpoint العام للترجمات فيه path traversal بيرجع `config/*` كله بما فيه `APP_KEY` وباسوردات الـ DB. وكمان طباعة الفواتير من غير login بـ id متسلسل.
4. **تشغيل وأسرار في حالة طوارئ.** باسورد SSH لحساب الاستضافة (اللي شايل كل الـ DBs) مكتوب في حوالي 80 سكريبت ومتعمله push على ريموتين. وفيه APP_KEYs وباسوردات MySQL و webhook token في الريبو. وفيه سكريبتات deploy بتعمل `migrate:fresh --seed` على production وبتمسح بيانات مستأجر. وفيه commit محلي (`76f32ce0`) فيه dumps إنتاج حوالي 22MB، وأي push جاي هينشرها. وتطبيق Electron فيه مسار RCE عن طريق deep link والـ updater.

وفوق ده، **المسارات المالية اليومية بتنتج أرقام غلط**: الدفع الجزئي في الـ POS بيتسجل مدفوع بالكامل، والدفع المقسم والمصاريف بيتشالوا بصمت، وفيه فواتير مكررة من الضغط المزدوج، وإقفال الوردية بيعد الكاش مرتين، والرصيد الافتتاحي للعملاء والموردين بيتمسح مع أول حركة، والمرتجعات مش مربوطة بفاتورة وبتتمسح من غير ما الأثر يتعكس. **والتجاري متخزن بس مش متطبق**: الإيقاف وانتهاء الاشتراك وحدود الباقات والـ feature flags كلها مالهاش أي enforcement، ومفيش signup ولا دفع، ومفيش backup لأي DB مستأجر.

**الخبر الكويس:** معظم الإصلاحات الحرجة صغيرة ومحددة المكان (S/M). أغلب الثغرات القاتلة تتقفل في **1–2 أسبوع** (Phase 0). الوصول لـ SaaS قابل للبيع لأول عملاء مدفوعين محتاج تقريبًا **10–14 أسبوع** من العمل المركز (Phase 0 → 2).

**التقييم العام: 2.7 / 10** (متوسط الأبعاد). **القرار: ممنوع onboarding أي مستأجر جديد، وممنوع أي push أو deploy، لحد ما Phase 0 يخلص.**

---

## 2. جدول الدرجات وعدد المشاكل

| # | البُعد | الدرجة /10 | Critical | High | Medium | Low | Info | الملف |
|---|---|---|---|---|---|---|---|---|
| 1 | SaaS & multi-tenancy core | **2** | 11 | 19 | 34 | 7 | 1 | [01](01-saas-tenancy.md) |
| 2 | Security & access control | **2** | 14 | 10 | 20 | 13 | 1 | [02](02-security.md) |
| 3 | Financial & inventory integrity | **4** | 0 | 25 | 39 | 13 | 2 | [03](03-financial-integrity.md) |
| 4 | Backend architecture & performance | **3** | 4 | 14 | 40 | 14 | 4 | [04](04-architecture.md) |
| 5 | Vue SPA / UX / RTL / POS / devices | **2.5** | 8 | 10 | 45 | 18 | 2 | [05](05-frontend.md) |
| 6 | Localization (ar/en) | **3** | 3 | 0 | 52 | 19 | 4 | [06](06-i18n.md) |
| 7 | Testing & QA | **3** | 6 | 17 | 34 | 14 | 1 | [07](07-testing-qa.md) |
| 8 | DevOps, deployment & repo hygiene | **1.5** | 4 | 29 | 26 | 7 | 1 | [08](08-devops.md) |
| 9 | Product scope, docs & roadmap | **3** | 3 | 11 | 41 | 23 | 3 | [09](09-product-docs.md) |
| | **الإجمالي (قبل إزالة التكرار)** | **متوسط 2.7** | **53** | **135** | **331** | **128** | **19** | |

> **ملاحظة مهمة عن التكرار:** الـ 53 critical **مش 53 مشكلة**. بعد إزالة التكرار بين الأبعاد هما **11 مشكلة جذرية critical**. مثلًا الـ quick-login لوحده متسجل 13 مرة، وقايمة الموبايلات الـ hardcoded 13 مرة. والـ 135 high بيتلخصوا في حوالي **30 مشكلة جذرية**. القسم 3 تحت فيه الجدول بعد الدمج.

---

## 3. أخطر المشاكل الفريدة (بعد إزالة التكرار)

**مقياس الجهد:** S = أقل من يوم · M = من 1 لـ 3 أيام · L = من 1 لـ 2 أسبوع · XL = أكتر من أسبوعين.
**ملحوظة:** مراجع الـ findings بصيغة `بُعد.رقم`، يعني مثلًا `2.9` هو الـ finding رقم 9 في `02-security.md`.

### 3.1 أمان وحدود الـ tenancy

| # | الخطورة | المشكلة | المكان | الأثر | الحل | الجهد |
|---|---|---|---|---|---|---|
| 1 | 🔴 Critical | **Login من غير باسورد + قايمة مستخدمين عامة.** `quick-login` بيدور على المستخدم بـ phone أو email أو id وبيدي token `['*']`، من غير باسورد ولا throttle ولا expiry. وكمان بيكتب الـ token plaintext في `users.api_token`. والمستأجر بيختاره العميل بنفسه عن طريق `X-Tenant`. | `backend/routes/api.php:28-29` · `app/Actions/Auth/ApiQuickLoginAction.php:23-61` · `AuthController.php:93-110` · `LoginView.vue:284,304,419` · التست `tests/Feature/Api/AuthApiTest.php:287` بيثبّت الثغرة · (1.1، 1.10، 2.1، 2.9، 2.12، 4.1، 4.3، 5.1، 5.4، 5.5، 5.6، 7.2، 7.4) | استيلاء كامل من الإنترنت على أي مستأجر وعلى الـ super-admin المركزي. ده **جديد على البرنش ده ومش موجود على main**. | امسح الراوتين أو حطهم ورا `ApiTokenAuth`. اعكس التست. خلّي الـ default `password` في `LoginView`. الغِ كل الـ tokens اللي بتبدأ بـ `quick-login-` وكل الـ tokens اللي اتعملت من وقت الـ commit ده، وفضّي `users.api_token`. لو التبديل السريع للكاشير مطلوب، اعمله كـ PIN مربوط بجهاز وفرع ومحتاج terminal token موجود (Phase 1). | S |
| 2 | 🔴 Critical | **قايمة موبايلات hardcoded بتدي god-mode.** `Gate::before` بيرجع `true` لأي ability، ومنها `super_admin.access`، لمستخدم موبايله واحد من **رقمين hardcoded (الأرقام محجوبة هنا)**. والفحص بيتعمل على جدول users بتاع المستأجر النشط، وأي كاشير يقدر يغير موبايله لنفسه من `PUT /profile`، لأن الـ unique متشيك جوه DB المستأجر بس. | `app/Providers/AppServiceProvider.php:37-45,49` · `TelescopeServiceProvider.php:82` · `routes/web.php:38-39` (وكمان أي إيميل على دومين الشركة) · `UserResource.php:19` · `ToggleTenantStatusRequest.php:13` · `OverrideTenantFeatureRequest.php:13` · `UpdateProfileRequest.php:13,23` · `UpdateProfileAction.php:26` · الأرقام كمان موجودة في `lang/ar/auth.php:21` و`lang/en/auth.php:26` (phone_placeholder) و`TenantSampleSeeder.php:28` والـ JS المبني وأصول Android وfixtures الـ e2e · (1.2، 1.7، 2.2، 2.5، 2.7، 2.10، 2.13، 4.2، 5.7، 7.5، 8.2، 9.2، 9.3) | أي مستخدم، حتى كاشير، يبقى super-admin للمنصة. ده كمان تسريب PII لأرقام شخصية جوه الكود. | امسح الـ allowlist من كل الأماكن دي، وخلّي صلاحية super-admin تعتمد بس على هوية مركزية. وحط قيمة وهمية في ملفات الـ lang. | S |
| 3 | 🔴 Critical | **دور `super_admin` متزرع في كل DB مستأجر وممكن يتعين.** `PermissionsSeeder` بيعمل الدور ده بكل الصلاحيات جوه كل مستأجر. و`Store/UpdateUserRequest` بيتحققوا بـ `exists:roles,name` وبس. و`UpdateRolePermissionsAction` بيعمل sync لدور `admin` على `Permission::all()`، وده فيه `super_admin.access`. الجزء الأخير ده استنتاج من ترتيب الـ providers واتأكد بالكود بس، مش بتست. | `database/seeders/PermissionsSeeder.php:61-77` · `TenantProvisionerService.php:85-87` · `StoreUserRequest.php:23` · `UpdateUserRequest.php:26,54` · `CreateUserAction:29` · `UpdateUserAction:35` · `Actions/Roles/UpdateRolePermissionsAction.php:20-26` · `UserController.php:62` (بيخفيه من الـ UI بس) · التست `SuperAdminApiTest.php:62` بيعتبره صح · (1.3، 1.8، 1.11، 2.3، 2.6، 4.4، 5.2، 7.3، 7.6، 9.1) | أي admin مستأجر يرقّي نفسه أو حد تاني لـ super-admin المنصة. | افصل الـ seeder لجزء central وجزء tenant. اعمل deny-list لـ `super_admin` و`super_admin.*` في الـ requests وفي sync الصلاحيات. واكتب أمر one-off (بعد مراجعة) يشيل الدور من الـ DBs الحالية. | S–M |
| 4 | 🔴 Critical | **(السبب الجذري للصفوف 1–3) الـ control plane متاح من سياق المستأجر.** جروب `/api/v1/super-admin/*` موجود جوه `ResolveApiTenancy` + `ApiTokenAuth`، ومحمي بـ `can:super_admin.access` وبس. والـ controller بيشتغل على `Tenant` (CentralConnection)، ومفيش أي فحص لـ `tenancy()->initialized`. | `routes/api.php:195-221` · `SuperAdminApiController` (toggleStatus :136، destroyTenant :247، updateDatabaseConfig :268) · `StoreTenantRequest::authorize` = true · `UpdateTenantDatabaseConfigRequest.php:13` بيسمح لدور `admin` بتاع المستأجر | مستأجر واحد يقدر يشوف كل المستأجرين ويوقفهم ويمد اشتراكه لحد 3650 يوم (bypass للفوترة) ويغير features ويشغل migrations ويعيد توجيه DB credentials. الحذف معطل **بالصدفة** بس، بسبب parse error (صف 10). | middleware اسمه `EnsureCentralContext` يرجّع 403/404 لو `tenancy()->initialized`. وعلى المدى المتوسط: guard مركزي و`CentralUser` وراوتات منفصلة فعليًا (دومين أو prefix مركزي). | S (الـ middleware) / L (فصل الهوية) |
| 5 | 🔴 Critical | **الكاش مشترك بين المستأجرين.** `CacheTenancyBootstrapper` متعطل. والـ cache store بيتعمل وقت الـ boot على الاتصال المركزي (عن طريق `Gate::before` → `PermissionRegistrar`). ومفاتيح التقارير مفيهاش tenant id. ومفتاح Spatie واحد لكل المنصة (24 ساعة). وسكريبت الـ deploy كمان بيحط `CACHE_STORE=file`. | `config/tenancy.php:36` · `Services/InventoryAnalyticsService.php:29` (`erp_abc_*`) · `Services/ProfitLossService.php:29` (`erp_pnl_*`) · `config/permission.php:209` · `UpdateRolePermissionsAction.php:26` · `clearCache()` مش متطابق مع المفاتيح ومحدش بيناديه · (1.4، 1.5، 1.6، 1.16، 2.4، 8.20) | مستأجر بيشوف أصناف وإيرادات وتكلفة وأرباح مستأجر تاني لمدة 15 دقيقة، وده شبه أكيد مع الفلاتر الافتراضية (فرع 1، الشهر الحالي). وكمان قرارات الصلاحيات ممكن تتحسب من خريطة أدوار مستأجر تاني. | حل سريع: ضيف `tenant('id')` لكل مفتاح، ومفتاح Spatie لكل مستأجر في listener على `TenancyBootstrapped`. والحل الاستراتيجي: `CacheTenancyBootstrapper` على store بيدعم tags (Redis)، واختبار معماري يمنع أي مفتاح كاش من غير scope. | S / M |
| 6 | 🔴 Critical | **Path traversal وتضمين ملفات PHP في endpoint الترجمات العام.** قيمة `locale` (أو header `X-Locale`) بتروح زي ما هي لـ `lang_path()` و`glob()` و`trans()`. يعني `?locale=../config` بيرجع كل arrays الـ config كـ JSON. | `app/Actions/System/GetTranslationsAction.php:18-22,40` · `Http/Controllers/Api/SystemContextApiController.php:47` · `routes/api.php:30` (برة الـ auth) · `GetSystemContextAction.php:103` · (6.1، 6.2، 6.3) | تسريب `APP_KEY` (تزوير cookies وpayloads مشفرة) وباسوردات DB وmail وTelegram، لو `config:cache` مش شغال، وفيه سكريبتات بتمسحه. ومش بس كده، ده بيشغّل أي `*.php` موجود في فولدر يتوصله بـ `../`. **اتعامل معاه كـ incident.** | allowlist `['ar','en']` في الـ controller وفي الـ Action (defence in depth). اكتب تست regression. **ودوّر الأسرار** (القسم 4). وراجع الـ access logs بحثًا عن `locale=..`. | S |
| 7 | 🔴 Critical | **فواتير بتتطبع من غير login.** `/invoices/{id}/print`، `/print/thermal`، `/print/a4` موجودين قبل جروب الـ auth، وبيعملوا `Invoice::findOrFail($id)` بـ id متسلسل. ونفس الحاجة على الـ host المركزي، ومعاها `/daily-journal/print` و`/reports/print` وتصدير CSV. | `routes/tenant.php:59-77` · `routes/web.php:56-68,180,278-282` · `print-a4.blade.php:207-218` · `print-thermal.blade.php:174-178` · (2.8، 1.13، 1.30، 3.5، 4.7، 4.15، 4.16، 5.11، 9.5) | أي حد يعرف دومين المستأجر يقدر يسحب أسماء العملاء وتليفوناتهم وعناوينهم وأرصدتهم وكل المبيعات. | انقلهم جوه الـ auth مع `can:invoices.view` وفحص الفرع، أو استخدم `URL::temporarySignedRoute` لـ popup الطباعة وElectron. وامسح النسخ المكررة في `web.php`. | S–M |
| 8 | 🟠 High | **Fallback مركزي شغال كـ master key.** أي مستخدم `admin` مركزي بياخد صلاحية admin في أي مستأجر، عن طريق الـ login fallback والـ token fallback (اللي بيطابق بالموبايل). وكمان **بيعمل أو بيرقّي مستخدم admin جوه DB العميل بصمت**. والـ impersonation الرسمي مش متوصل. | `Http/Middleware/ApiTokenAuth.php:50-63` · `Actions/Auth/ApiLoginAction.php:31-58` · (1.14، 1.29، 2.15، 7.14) | باب خلفي من غير تدقيق في بيانات كل العملاء. | امسح الـ fallbacks. وصّل الـ impersonation الرسمي (stancl token، TTL 60 ثانية)، واعمل audit log لكل جلسة. | M |
| 9 | 🟠 High | **الـ tokens ضعيفة.** الـ Sanctum tokens مالهاش expiry (مفيش `config/sanctum.php`). وفيه `users.api_token` plaintext ومقبول كـ credential. و`?api_token=` في الـ URL. وبوابة Telescope بتقبل token في الـ query وبتعمل remember-me session. | `ApiTokenAuth.php:21` · `TelescopeServiceProvider.php:59-87` · `routes/web.php:17-48` · (2.16، 2.23، 8.31) | الـ tokens بتتسرب في الـ logs والـ Referer، وصلاحيتها أبدية. | publish لـ `sanctum.php` مع expiry. امسح عمود `api_token` وقراءة التوكن من الـ query. واقفل Telescope/Pulse في production أو خليهم مركزي فقط. | S–M |
| 10 | 🟠 High | **`DeleteTenantAction` و`UpdateTenantDatabaseConfigAction` فيهم parse errors**، لأن المتغيرات اتشالت في commit `606da74b`. ولو اتصلحوا زي ما هم، الحذف هيعمل DROP للـ DB فورًا ومن غير backup ولا فترة سماح. | `Actions/Tenants/DeleteTenantAction.php:12-16` · `UpdateTenantDatabaseConfigAction.php:11` · `TenancyServiceProvider.php:39` · (1.23، 5.13، 7.18، 9.7، 1.20) | الـ endpoints بترجّع 500. ولو حد صلّحهم قبل ما الثغرات 2–4 تتقفل، أي مستأجر يقدر يمسح DB عميل تاني. | **صلّحهم بعد قفل الصفوف 2–4 بس.** وخلّي الحذف = `status=cancelled`، وبعدين retention → export → purge مع تأكيد مكتوب. | S (الإصلاح) / M (الـ lifecycle) |
| 11 | 🟠 High | **مفيش عزل للفروع جوه المستأجر.** الـ middleware `store.access` متسجل ومش متركب على أي راوت. و`X-Store-Id` أو `store_id` اللي جاي من العميل مصدّق على طول (والـ body بيكسب على الـ header). وindex/show/cancel مش متقيدين بالفرع. والـ dashboard بيرجّع أرباح أي فرع لأي مستخدم. | `bootstrap/app.php:28` · `ApiTokenAuth.php:83-86,160` · `StorePOSInvoiceRequest.php:22-23,66` · `InvoiceController:40` · `CancelSalesInvoiceAction:22` · `TreasuryController:34` · `ReportController:37` · `DashboardApiController.php:29` · `PosView.vue:708` (`|| 1`) · (1.15، 2.18، 2.19، 3.4، 3.12، 3.22، 4.8، 7.19، 7.20، 9.12) | كاشير في فرع يبيع من مخزون فرع تاني، ويقرأ فواتيره ويلغيها، ويشوف أرباح كل الفروع. | `ResolveActiveStore` middleware يتحقق من `user->stores()` ويسمح بـ `all` بس لصلاحية خاصة. وطبّقه على POS والفواتير والورديات والتحويلات والتقارير، مع تستات عزل. | M–L |

### 3.2 سلامة الحسابات والمخزون

| # | الخطورة | المشكلة | المكان | الأثر | الحل | الجهد |
|---|---|---|---|---|---|---|
| 12 | 🔴 Critical | **الدفع الجزئي في الـ POS بيتجاهل المبلغ اللي الكاشير كتبه.** الطلب بيبعت `paidAmount` القديم، والـ input مربوط بـ `cashReceived`. والسيرفر بيثق في `paid_amount` من غير سقف. | `views/POS/PosView.vue:714,480-489` · `POSCheckoutPanel` L128-131 · `InvoiceService::confirmInvoice` L193-206 · (5.3) | فواتير آجلة بتتسجل **مدفوعة بالكامل**، وبيتعمل سند قبض، **ومديونية العميل بتضيع**. | ابعت `cashReceived` كـ string بـ 3 أرقام عشرية، وتحقق في السيرفر من `0 < paid < net` في الحالة الجزئية. | S |
| 13 | 🔴 Critical | **الرصيد الافتتاحي للعملاء والموردين بيتمسح مع أول حركة.** الرصيد الافتتاحي بيتحط في `current_balance`، وبعدين خدمات إعادة الحساب بتبني الرصيد من الصفر. والفورم بيبعت `initial_balance` والـ backend مستني `opening_balance`. والتستات بتخبي ده (فاتورة مزيفة في `CustomersApiTest`). | `Actions/Customers/CreateCustomerAction.php:24` · `Actions/Suppliers/CreateSupplierAction.php:24` · `Services/CustomerBalanceService.php:16-44` · `SupplierBalanceService.php:17-48` · `ReturnService.php:164-170` · (7.1، 3.14، 3.18، 4.10) | ذمم مدينة ودائنة بتختفي من غير أثر. | عمود `opening_balance DECIMAL(12,3)` (أو مستند رصيد افتتاحي) يدخل في كل إعادة حساب وكشف حساب. صلّح اسم الحقل. **واعمل audit للبيانات الحالية.** | M |
| 14 | 🟠 High | **الدفع المقسم والمصاريف بيتشالوا بصمت.** الـ POS بيبعت `payments` و`expenses` على `/invoices`، و`StoreSalesInvoiceRequest` مفيهوش rules ليهم، و`CreateInvoiceDTO` مفيهوش payments. | `PosView.vue:707-724` · `Http/Requests/StoreSalesInvoiceRequest.php:39-57` · `InvoiceService.php:130,225,241-251` · (3.2، 4.5، 5.9، 7.13) | صافي الفاتورة والقناة غلط، وتقرير Z والخزينة حسب طريقة الدفع غلط. | ضيف `payments.*` و`additional_expenses.*`، أو حوّل الـ SPA لـ `/pos/checkout`. واكتب contract test بالـ payload الحقيقي. | M |
| 15 | 🟠 High | **مفيش idempotency، وفيه فواتير مكررة.** F9 وCtrl+Enter بيتخطوا `isSubmitting`، ومفيش client UUID، والـ timeout 30 ثانية بيسيب السلة لإعادة المحاولة. | `PosView.vue:700-743,814-818` · `Services/api.js:12` · (3.1، 5.10) | فاتورة مكررة، يعني المخزون بيتخصم مرتين والخزينة بتتضخم. | حماية فورية في الـ UI. وبعد كده `client_uuid` + unique `(store_id, client_uuid)`، وترجّع نفس الفاتورة لو الطلب اتعاد. | S / M |
| 16 | 🟠 High | **مخزون الفرع مش متفرض لو مفيش صف `StoreStock`.** أي فرع بيتعمل بعد الأصناف بيبيع من المخزون الكلي، وشاشة الـ POS بتعرض المخزون الكلي كأنه مخزون الفرع. | `Services/StockService.php:59-73` · `PurchaseService.php:273` · `CreateStoreAction` · `GetPOSBootstrapDataAction:64` · (3.3، 3.11، 4.11، 7.9) | مجموع مخزون الفروع بيبطّل يساوي الإجمالي، وبيع من رصيد مش موجود. | اعتبر الصف الناقص = 0 وارمي exception. واعمل seed لصفوف صفرية عند إنشاء فرع، وصلّح الـ COALESCE. | S |
| 17 | 🟠 High | **إقفال الوردية غلط في كل وردية.** الكاش بيتعد مرتين (`paid_amount` + سند `PAY-INV`). وكل مرتجع بيتخصم كاسترداد نقدي، مع إنه اتسجل كمان كرصيد للعميل. والـ payments مالهاش `store_id` فبتتخلط بين الفروع. ومفيش lock على الإقفال، وTelegram بيتنادى جوه الـ transaction. | `Services/ShiftService.php:46,81-138` · `CloseShiftAction:23` · `TreasuryService.php:34` · (3.7، 3.9، 3.17، 3.20، 3.21، 4.12، 4.14، 7.8) | عجز وهمي في كل وردية، وتنبيهات Telegram كاذبة، وخزينة الفروع مختلطة. | مصدر واحد للكاش. ضيف `store_id` للـ payments. اعمل `lockForUpdate` على الوردية، وابعت Telegram بعد الـ commit. واكتب تست ببيعة حقيقية. | M |
| 18 | 🟠 High | **إلغاء الفاتورة ناقص.** مش بيعكس سندات الدفع ولا المصاريف المدفوعة من الخزينة، ومش بياخد المرتجعات السابقة في الحساب، فالعكس بيحصل مرتين. | `Services/InvoiceService.php:278-335` · (3.6، 7.12) | رصيد دائن وهمي للعميل (غالبًا عميل نقدي) والخزينة لسه حاسبة الوارد، وبرضه مخزون زيادة. | قرار سياسة (إذن صرف ولا رصيد)، واعكس الـ Payments والـ Expenses، واعكس الكمية غير المرتجعة بس، أو امنع الإلغاء لو فيه مرتجع. | M |
| 19 | 🟠 High | **المرتجعات باب للاحتيال الداخلي.** المرتجع مش مربوط بفاتورة، والكمية والسعر مفتوحين. والحذف soft delete من غير عكس، والكاشير يقدر يعمله. والـ restore والـ force-delete من سلة المهملات من غير عكس، والـ force-delete بيمسح مستند مالي فيزيائيًا. | `Http/Requests/StoreReturnRequest.php:18-29` · `Services/ReturnService.php:63-89` · `Actions/Returns/DeleteReturnAction.php:15-17` · `Trash/RestoreTrashRecordAction.php:28-32` · `ForceDeleteTrashRecordAction:28` · `PermissionsSeeder.php:93,107` · (3.8، 3.10، 3.24، 4.6، 7.10، 7.11، 7.22) | تلاعب بالمخزون والأرصدة، ومخالفة صريحة لقاعدة "ممنوع الحذف الفيزيائي" في `AGENTS.md`. | فورًا: امنع الحذف والـ restore والـ force-delete للمرتجعات، واسحب الصلاحية من الكاشير. وبعد كده: `invoice_id` إلزامي، و`invoice_item_id`، وسقف = المباع − المرتجع، وإلغاء بعكس كامل. | S / L |
| 20 | 🟠 High | **أخطاء المشتريات والموردين.** المصروف المدفوع من الخزينة بيتسجل كدفعة للمورد، فبيتعد مرتين وبيقلل المديونية. وإلغاء الشراء بيعمل soft delete لسندات نقدية حقيقية. وسند المورد ممكن يتسجل على فاتورة مورد تاني أو فاتورة ملغية. | `Services/PurchaseService.php:164,300` · `TreasuryService.php:40,212` · `PaymentService.php:86` · (3.13، 3.15، 3.16، 3.19، 3.25) | رصيد الموردين والخزينة غلط، و"كاش وهمي" بيرجع الخزينة. | افصل المصروف عن الدفعة، والغِ السندات بقيد عكسي، وتحقق من المورد والمتبقي. | M |
| 21 | 🟠 High | **تقارير الأرباح متضخمة ومتناقضة.** تقرير P&L بيتجاهل المرتجعات، والتقريرين بيدوا أرقام مختلفة، ونسخة الطباعة/ABC بتحسب التكلفة التاريخية بسعر النهارده. | `Actions/Reports/GetProfitLossReportAction.php:21` · `Services/ProfitLossService.php:74` · (3.23، 4.13) | قرارات إدارية مبنية على أرباح غلط. | مصدر واحد للـ P&L من snapshot التكلفة في سطور الفاتورة، مع خصم المرتجعات. | M |

### 3.3 الـ SaaS التجاري

| # | الخطورة | المشكلة | المكان | الأثر | الحل | الجهد |
|---|---|---|---|---|---|---|
| 22 | 🟠 High | **الإيقاف وانتهاء الاشتراك والتجربة مش متطبقين.** `ResolveApiTenancy` بيعمل initialize لأي مستأجر من غير ما يبص على حالته. والفحص الوحيد في الـ resolver العام، والعميل يقدر يتخطاه. والـ tokens بتعيش بعد الإيقاف. ولما مفيش مستأجر، الراوتات بتشتغل على الـ DB المركزي. | `Http/Middleware/ResolveApiTenancy.php:30-37,46,68,72` · `ResolveTenantWorkspaceAction.php:48` · (1.12، 1.27، 2.17، 5.14، 7.16، 8.23، 9.8) | مفيش أي وسيلة تحصّل بيها فلوس: العميل الموقوف شغال عادي. | middleware `EnsureTenantActive` بعد الـ resolver، وإلغاء الـ tokens عند الإيقاف، وأمر يومي للـ lifecycle (trial → past_due → read-only → suspended)، و404 لو مفيش مستأجر. | M |
| 23 | 🟠 High | **حدود الباقات والـ features للعرض بس.** `max_*` و`checkLimit` و`hasFeature` و`isFeatureEnabled` محدش بيناديهم. و`checkLimit` نفسه غلط (مفاتيح غلط وعمود مش موجود). و`FeatureGate.vue` مش مستخدم وبيسمح بكل حاجة افتراضيًا. و`useModules` بيقرأ JSON ثابت وقت الـ build. والـ blender شغال للكل. | `Models/Tenant.php:67,99-141` · `Services/TenantFeatureManager.php:13` · `CreateUserAction.php:19` · `Composables/useModules.js:1` · `UpdatePlanRequest` (min:1 مينفعش يعبّر عن "غير محدود") · (1.28، 4.17، 5.12، 5.15، 7.17، 9.6، 9.9، 9.10) | مينفعش تبيع باقات متدرجة. | `PlanLimitGuard` مركزي (null = غير محدود) في actions الإنشاء، و`feature:<key>` middleware، والـ features المحسوبة تتبعت في `/system/context`، و`FeatureGate` يمنع افتراضيًا. | L |
| 24 | 🟠 High | **مفيش backup لأي DB مستأجر.** الـ scheduler شغال في السياق المركزي بس، وأوامر `notify:*` بتفشل في كل تشغيل. و`backup:telegram` بيبعت dump الـ DB المركزي **gzip من غير تشفير** (والـ caption بيقول إنه مشفر)، وجواه tokens الـ super-admin وباسوردات DBs المستأجرين. وتنبيهات المستأجر بتروح لبوت صاحب المنصة افتراضيًا. ومحرك الـ dump المكتوب يدوي مش موثوق (من غير snapshot، وسقف 50MB، وعمره ما اتجرب في restore). | `routes/console.php:24-38` · `Services/DatabaseBackupService.php:39,67` · `TelegramService.php:22,382-383` · `TenancyServiceProvider.php:39` · (1.17، 1.18، 1.19، 1.20، 4.9، 8.18، 8.22، 8.25، 8.27، 8.28، 9.11) | لو حصل أي عطل، بيانات كل العملاء ممكن تضيع. وأسرار المنصة رايحة لشات Telegram. | **وقّف** `backup:telegram` و`notify:*` فورًا. واعمل backup مشفر لكل مستأجر (`mysqldump --single-transaction` / spatie/laravel-backup) خارج السيرفر، مع retention وتجارب restore. | S (الإيقاف) / L |
| 25 | 🟠 High | **مفيش ضرائب ولا فاتورة إلكترونية على مستندات البيع.** | `database/migrations/tenant/2026_08_08_160005_create_invoices_and_items_tables.php:16` · (9.13) | عائق قانوني وتجاري قدام البيع لمحلات مسجلة ضريبيًا. | قرار منتج: VAT والربط مع منظومة الفاتورة الإلكترونية (ETA) كمرحلة. | L–XL |
| 26 | 🟠 High | **الـ backup من جوه المنتج بايظ.** 6 راوتات إعدادات بتشاور على methods مش موجودة، وواجهة الـ backup يتيمة. وكمان 13 راوت في `tenant.php` بيشاوروا على methods مش موجودة. | `routes/tenant.php:240` · (9.14، و9 medium #30) | ميزة معلنة بترجّع 500. | امسحها أو نفّذها على backup لكل مستأجر. | S–M |

> **فجوات تجارية medium مرتبطة** (في `01` و`09`): مفيش signup ذاتي ولا بوابة دفع (Paymob/Stripe) ولا فواتير اشتراك. وجدول `subscriptions` بيتكتب مرة واحدة بس، فالـ MRR غلط. والـ provisioning مش atomic ومتزامن جوه الـ HTTP request. وفيه 3–4 نسخ متضاربة من التسعير. والـ branding لكل مستأجر ناقص. ومفيش export للبيانات ولا offboarding.

### 3.4 الواجهة والأجهزة

| # | الخطورة | المشكلة | المكان | الأثر | الحل | الجهد |
|---|---|---|---|---|---|---|
| 27 | 🔴 Critical | **Electron: من deep link لحد RCE.** `parseTenantFromDeepLink` بيعمل trim وlowercase بس، فقيمة زي `evil.com/` بتحمّل أصل خارجي، وبيتحفظ ويرجع يتحمل مع كل تشغيل. و`will-navigate` فاضي، ومفيش `setWindowOpenHandler`. والـ preload بيعرض `updater.downloadAndInstall` لأي أصل، والـ `ipcMain` مش بيتحقق من الـ sender. والـ updater بيقبل http ومن غير hash ولا توقيع، وبيعمل `spawn(exe, ['/S'])`. | `desktop/main.js:26-71,238-240,539-542` · `desktop/preload.js:39-40` · `desktop/src/updater/nativeUpdater.js:98` · (2.14، 5.8، 5.16، 8.3) | لينك واحد (أو XSS) يشغّل أي EXE بصمت على أجهزة الكاشير. | regex للـ tenant `^[a-z0-9-]{1,63}$` + فحص الـ hostname، وallowlist في `will-navigate` و`setWindowOpenHandler`، وفحص `senderFrame`، وhttps بس + SHA-256 من `app_versions`. وبعد كده electron-updater مع Authenticode. | S / L |
| 28 | 🟠 High | **تطبيق Android بيوصل للمستأجر `2m` بس.** الـ host-based resolution بيغطي على `X-Tenant`. | `Http/Middleware/ResolveApiTenancy.php:46` · (5.17) | تطبيق الموبايل مينفعش يتباع لأي عميل تاني. | الأولوية للـ header الموقّع/المتحقق منه في سياق الـ app، أو دومين لكل مستأجر. | M |
| 29 | 🟠 High | **"تحديث" Android مزيف.** بيعرض progress وهمي ويعمل reload، ولو التحديث إجباري بيلف في loop مالوش نهاية. | `resources/js/Composables/useAppUpdate.js:201` · (5.18) | العملاء متعلقين على نسخ قديمة، أو التطبيق واقف في loop. | تنزيل APK حقيقي + checksum، أو تحديث من المتجر. | M |

> **ملاحظات medium للواجهة** (`05`, `06`): مفيش language switcher، والـ boot بيجبر `ar`. والسيرفر عمره ما بيعمل `setLocale`، فالإنجليزي مش قابل للاستخدام عمليًا. وفيه حوالي 80 مفتاح ترجمة ناقص، و79 fallback من نوع `?:` عمرهم ما هيشتغلوا. وعنوان الـ shell لسه "سرور كوفي". واختلاف `services/` و`Services/` في الـ casing هيكسر الـ build على Linux. ومفيش offline للـ POS.

### 3.5 DevOps والأسرار

| # | الخطورة | المشكلة | المكان | الأثر | الحل | الجهد |
|---|---|---|---|---|---|---|
| 30 | 🔴 Critical | **باسورد SSH لحساب الاستضافة المشترك مكتوب في حوالي 80 سكريبت متتبع**، ومتعمله push على `origin` (sroor-cofe-erp) و`erp-hub` في main وfeature/multi-tenant وfeature/api-migration. وكل السكريبتات بتستخدم `paramiko AutoAddPolicy`. | `backup_sroor_db.py:7` · `publish_windows_version.py:6` · `deploy_root_baraa.py:12` · `restore_sroor_db.py:7` · `deploy_sroor.py:9` وغيرهم · (2.11، 8.1، 8.4، 8.14، 1.26، 8.24) | أي حد عنده صلاحية قراءة على الريبو بياخد shell على الحساب اللي فيه الـ DB المركزي وكل DBs المستأجرين وكل الـ `.env` وكل الـ APP_KEYs. | **دوّر الباسورد فورًا** وخلّي SSH بالمفاتيح بس. وبعدين purge من الـ history على الريموتين. | S (التدوير) / M (الـ purge) |
| 31 | 🟠 High | **APP_KEYs إنتاجية وباسوردات MySQL متكومتة**، والباسورد بيتبعت في الـ command line (`--password=`)، وواحد من الـ APP_KEYs متكرر في `phpunit.xml`. | `deploy_root_baraa.py:85,99` · `deploy_to_sroor_subdomain.py:54,70` · `deploy_with_mysql.py:42,58` · `fix_shipping_php83.py:28,44` · `backup_sroor_db.py:11,34` · `restore_sroor_db.py:11,20` · `deploy_remote.py` · `backend/phpunit.xml:22` · (2.21، 8.8) | تزوير sessions، ووصول مباشر للـ DB المركزي. | دوّر الكل، وانقلهم لـ secret store أو env. | S–M |
| 32 | 🟠 High | **webhook الـ deploy بـ token ثابت متكومت**، وفي الـ query string، ومن غير replay protection، وبيعمل `git reset --hard` + `migrate --force`. وموجود في الـ web root، **ومتضمن جوه الـ APK** (لأن webDir الـ Capacitor = `public/`). | `.github/workflows/deploy.yml:49` · `update_webhook.php:4` · `backend/public/update_webhook.php:4` · (1.22، 2.22، 2.24، 8.13، 8.30) | أي حد معاه الـ APK يقدر يعمل deploy أو reset للإنتاج. | امسح الـ webhook، ودوّر الـ token، ونقله لـ GitHub Secrets، واعمل deploy عن طريق SSH key. | S |
| 33 | 🟠 High | **dumps إنتاج في الـ commit المحلي `76f32ce0`.** (اتأكدنا منه: ahead 1، ومش متعمله push.) فيه `backups/*.sql` و`*.sql.gz` وsnapshots إنتاج وCSV وتقارير تكلفة، حوالي 22MB، وفيها مستخدمين وعملاء وbcrypt hashes. وكمان حوالي 40 سكريبت تاني فيه باسوردات. و`.gitignore` مفيهوش `backups/` ولا `*.sql`. و`deploy_root_baraa.py:30-32` بيعمل `git add . && commit && push`. | `backups/sroor_prod_2026-09-29.sql.gz` · `backups/sroor_backup_latest.sql` · `.gitignore` · `deploy_root_baraa.py:30-32` · (2.20، 8.5، 8.19، 8.26، 8.32، 8.33، 9.4) | أول push أو deploy هينشر PII العملاء والـ hashes. | **ممنوع push.** اعمل soft reset وcommit تاني من غير `backups/`. وحدّث `.gitignore`. وامسح `git add .` من السكريبت. | S |
| 34 | 🟠 High | **سكريبت الـ deploy الخاص بالـ SaaS مدمّر.** بيعمل deploy لـ `feature/api-migration` مش البرنش ده، وفي كل تشغيل بيمسح صف `tenant_sroor` (والـ DB بيفضل يتيم). وبيعمل migrate لـ `2m` بس. وبيشغّل `tenant:populate-realistic-data 2m`، اللي بيعمل truncate للفواتير والمخزون والمدفوعات من غير شرط (`--fresh` مش متشيك)، وبيرجّع مستخدمين god-mode بباسورد افتراضي تافه. وكمان بيكتب فوق الـ `.env` بتاع الإنتاج، و`deploy.sh` بيعيد توليد APP_KEY. | `deploy_root_baraa.py:71-72,82,124-129` · `Console/Commands/PopulateRealisticTenantDataCommand.php:107-175` · `deploy.sh` · (1.21، 1.24، 4.18، 8.7، 8.11، 8.17، 8.21) | مسح بيانات عميل حقيقي مع كل deploy. | امسح السطور 124 و129. وضيف `abort` في production + فحص `--fresh` حقيقي للأمر. وبطّل تكتب فوق الـ `.env`. | S |
| 35 | 🟠 High | **سكريبتات بتعمل `migrate:fresh --force --seed` على مسارات الإنتاج** (منهم اتنين على الـ DB الحي). وفيه `db:seed --force`. و`restore_sroor_db.py` بيكتب فوق الإنتاج من غير تأكيد. | `fix_shipping_php83.py:63` · `deploy_with_mysql.py:79` · `deploy_sroor.py:68` · `deploy_hostinger_php83.py:50` · `deploy_hostinger_php84.py:56` · `deploy_subdomain.py:54` · `finish_subdomain.py:45` · `deploy_all_locations.py:46` · `restore_sroor_db.py:20` · (8.6، 8.12، 8.29) | تشغيل واحد غلط بيمسح الإنتاج بالكامل. | `git rm` للسكريبتات دي كلها. | S |
| 36 | 🟠 High | **مفيش release pipeline حقيقي.** فيه حوالي 15 نسخة `deploy_*.py`، و`|| true` في كل خطوة، و`git reset --hard origin/main`، ومن غير maintenance mode ولا rollback. ومفيش مسار بيعمل migrate لكل المستأجرين. وجوب الـ CI بيتشغل من الـ root والـ `composer.json` جوه `backend/`، فالتستات عمرها ما بتوقف deploy. وفيه `|| echo` بيخلي الـ deploy "ناجح" دايمًا. | `deploy.sh:29-32` · `.github/workflows/deploy.yml` · `deploy_root_baraa.py:125` · (8.10، 8.15، 8.16) | المستأجرين بيفضلوا على schemas قديمة، والـ deploys مكسورة ومحدش بيعرف. | pipeline واحد في GitHub Actions: `set -euo pipefail`، وtests في `backend/`، و`releases/` + symlink، و`tenants:migrate --force` (الفشل يوقف الـ deploy)، وhealth check على `/up`، وrollback. | L |
| 37 | 🟠 High | **credentials افتراضية في الإنتاج.** `DatabaseSeeder` (الـ seeder المتظبط للمستأجر) بيعمل مستخدمين god-mode بباسوردات معروفة، و`deploy.sh` بيشغّل `--seed`. وسكريبتات `test_live_*.py` و`seed_fresh_direct.py` و`verify_production_users.py` فيها بيانات دخول admin للإنتاج. وتسجيل الدخول الافتراضي في الـ e2e هو الـ super-admin. | `database/seeders/DatabaseSeeder.php:17-45` · `TenantSampleSeeder.php:28-29` · `test_live_browser.py:23-24` · `test_live_activity_logs.py:13` · `test_live_touch_pos.py:13` · `test_production_verification.py:14` · `test_sidebar_click.py:14` · `seed_fresh_direct.py:58-69` · `verify_production_users.py:26-30` · `e2e/auth/login.setup.js:16` · (1.25، 7.23، 8.9) | دخول مباشر بباسورد معروف، حتى من غير الـ quick-login. | غيّر باسوردات الإنتاج، وافصل demo seeders عن production، واعمل باسورد عشوائي + reset إجباري عند أول دخول. | S–M |

### 3.6 الاختبارات

| # | الخطورة | المشكلة | المكان | الأثر | الحل | الجهد |
|---|---|---|---|---|---|---|
| 38 | 🟠 High | **العزل بين المستأجرين والفروع مش متختبر خالص، وفيه تستات بتثبّت الثغرات.** مفيش تست بيبعت `X-Tenant` أو بيعمل `tenancy()->initialize`. و`TestCase` بيحط جداول المستأجر في نفس الـ sqlite. وsqlite `:memory:` مش بيختبر `lockForUpdate` ولا تقريب DECIMAL. و`AuthApiTest:287` و`SuperAdminApiTest:62` و`ProfileApiTest` (fixture برقم مميز) بيعتبروا الثغرات سلوك صحيح. وتست الـ provisioning بيكتب ملف sqlite جوه الريبو. | `tests/TestCase.php:13-15` · `phpunit.xml` · `tests/Feature/Api/AuthApiTest.php:287` · `SuperAdminApiTest.php:62` · `CustomersApiTest.php:205-251` · `ReturnServiceTest` · `InvoiceServiceTest` · (7.7، 7.15، و1/7 medium) | 305 تست ناجح بيدوا **ثقة كاذبة**. ولا ثغرة critical واحدة كان ممكن تتمسك. | harness بمستأجرين (اتنين DB بملفات أو MySQL)، و`TenancyIsolationTest`، وتستات تصعيد صلاحيات، وتستات ثوابت مالية (invariants)، وjob على MySQL 8 في الـ CI. | L |
| 39 | 🟠 High | **14 صلاحية مستخدمة في الكود ومش موجودة في `PermissionsSeeder`.** الأدوار المتزرعة بتفقد features بصمت، والتستات بتخبي ده لأنها بتعمل الصلاحيات دي ad hoc. | `database/seeders/PermissionsSeeder.php:18` · (7.21) | مستأجرين جداد بيستلموا أدوار ناقصة. | زامن الـ seeder مع الكود، واكتب تست يتأكد إن كل `can:` موجودة. | S |

### 3.7 التوثيق

| # | الخطورة | المشكلة | المكان | الأثر | الحل | الجهد |
|---|---|---|---|---|---|---|
| 40 | 🟡 Medium (مجمّعة) | **التوثيق الحاكم بيوصف منتج تاني.** `AGENTS.md` و`project-spec.md` و`docs/README.md` و`frontend-architecture.md` و`backend-architecture.md` لسه بيوصفوا Livewire/Alpine، بل وبيمنعوا Vue. و`database-schema.md` بيوصف schema قبل الـ SaaS (16 جدول). وخطة الـ SaaS بتقول Inertia/PrimeVue و"من غير REST API". و`tasks-breakdown.md` مفيهوش ولا مهمة SaaS، وفيه مهام متعلّم عليها `[x]` ومش متنفذة (تحويل الخزن). وجدول التدقيق بيقول 100% ومن عينة 5 ادعاءات "done" واحدة بس كانت صح. و`AGENTS.md` بيفرض token في الـ query لـ Telescope. | `AGENTS.md` · `docs/03-architecture/*` · `docs/05-planning/tasks-breakdown.md` · `system-architecture-master.md` · (9 medium #22-37، #54) | الـ agents والمطورين بيبنوا على قواعد غلط، وبيتولد شغل متعارض. | علّم الوثائق القديمة إنها "historical". وضيف قسم SaaS لـ `tasks-breakdown.md` حالته مستخرجة من الكود. وحدّث `AGENTS.md` للستاك الفعلي (Vue 3 SPA + API). | M |

---

## 4. قبل أي push أو deploy — إجراءات فورية

> **دي مش "مهام تحسين"، دي احتواء incident.** الترتيب مهم.

1. **تجميد:** متعملش `git push` ومتشغلش `deploy_root_baraa.py` ولا أي `deploy_*.py`، ومتعملش onboarding لأي مستأجر جديد.
2. **نضّف الـ commit المحلي `76f32ce0` قبل أي push** (اتأكدنا إنه ahead 1 ومش متعمله push): `git reset --soft HEAD~1`، وبعدين commit تاني من غير `backups/**` (الـ dumps والـ CSV والـ HTML والـ PDF) ومن غير سكريبتات الباسوردات الجديدة. وضيف `backups/` و`*.sql` و`*.sql.gz` و`*.apk` و`test-results/` لـ `.gitignore`، وانقل الـ dumps لتخزين مشفر برة الريبو. وامسح `git add .` من `deploy_root_baraa.py:30-32`.
3. **دوّر الأسرار** (ده إجراء للمالك من hPanel/GitHub، ولازم يحصل مهما حصل في الـ history):
   - باسورد SSH/hPanel لحساب Hostinger، وحوّل SSH للمفاتيح بس.
   - باسوردات MySQL للـ DB المركزي وDB `sroor` وأي DB مستأجر بنفس الباسورد (فيه شك في إعادة استخدام الباسورد).
   - كل الـ `APP_KEY` الإنتاجية، لأنها مكشوفة في السكريبتات **وممكن تكون اتسربت من endpoint الترجمات**. وخد بالك إن تدوير APP_KEY بيبطّل الـ sessions والبيانات المشفرة، فلازم تخطط له.
   - webhook token الـ deploy، وانقله لـ GitHub Secrets.
   - باسوردات حسابات admin والـ super-admin الافتراضية في الإنتاج.
   - Telegram bot token لو كان موجود في الـ config ساعة ما الـ traversal كان متاح، واعتبر أي dump اتبعت لـ Telegram قبل كده **مخترق**.
4. **Hotfixes لازم تنزل قبل أي deploy جاي** (كلهم S): امسح `quick-login` و`workspace-users` (صف 1). امسح قايمة الموبايلات (صف 2). اعمل middleware السياق المركزي لـ `/super-admin` (صف 4). امنع تعيين `super_admin` (صف 3). اعمل allowlist للـ locale (صف 6). اقفل راوتات الطباعة (صف 7). وقّف `backup:telegram` و`notify:*` (صف 24). امسح السطور 124 و129 من `deploy_root_baraa.py` و`git rm` لسكريبتات `migrate:fresh` (صفوف 34–35).
5. **بعد الـ deploy:** الغِ كل الـ Sanctum tokens في كل DBs المستأجرين والـ DB المركزي، وفضّي `users.api_token`.
6. **تحقيق جنائي read-only:** دوّر في الـ access logs على `POST /api/v1/auth/quick-login` و`/super-admin/*` و`locale=..` و`X-Locale` اللي فيه `..` و`/invoices/*/print` من IPs غريبة. وراجع كل DB مستأجر: مين معاه دور `super_admin`؟ مين موبايله واحد من الرقمين؟ ومين admin مركزي اتعمله provision بصمت؟
7. **فعّل GitHub secret scanning + push protection** على الريبوهين، واتأكد إنهم private.
8. **بعد التدوير بس:** purge للـ history (`git filter-repo`/BFG) على الريموتين، وforce-push، وكل المتعاونين يعملوا re-clone.
9. **Electron:** متوزعش أي نسخة desktop قبل hotfix الـ deep link والـ updater (صف 27).

---

## 5. الفجوات بين الوضع الحالي و SaaS قابل للبيع

| المجال | الموجود دلوقتي | المطلوب للبيع |
|---|---|---|
| **الهوية والصلاحيات** | super-admin معرّف بدور أو موبايل جوه DB المستأجر، و`quick-login` عام، وfallbacks مركزية | هوية مركزية منفصلة (guard + `CentralUser` + دومين/prefix مركزي)، والـ tenant routes تطلب مستأجر initialized، وtokens بـ expiry وabilities، وPIN مربوط بجهاز للكاشير |
| **عزل المستأجرين** | DB منفصل لكل مستأجر ✅، بس الكاش مشترك، والطباعة عامة، وفيه fallback للـ DB المركزي | `CacheTenancyBootstrapper` (Redis)، ومفيش راوت من غير auth، و404 لو مفيش مستأجر، وتستات عزل حقيقية |
| **عزل الفروع** | `store_id` من العميل مصدّق | middleware يتحقق من صلاحية المستخدم على الفرع، و`store_id` على الـ payments، وscopes |
| **الـ lifecycle التجاري** | حقول status/trial/subscription متخزنة بس | enforcement للحالة على كل request، وjob يومي (trial → past_due → read-only → suspended)، وتذكيرات 7/3/1 أيام، وledger للاشتراكات |
| **الباقات** | `max_*` وfeatures من غير أي استدعاء | `PlanLimitGuard` + `feature:` middleware + gating في الـ UI بالمنع افتراضيًا |
| **Signup والفوترة** | المستأجر بيتعمل من super-admin بس | signup ذاتي، وتجربة 14 يوم، وبوابة دفع (Paymob/Stripe) + webhooks، وفواتير اشتراك، وصفحة billing للمستأجر، وتسعير واحد معتمد |
| **حماية البيانات** | مفيش backup لأي مستأجر، والحذف DROP فوري | backup مشفر لكل مستأجر خارج السيرفر + retention + restore drills، وexport بيانات للمستأجر، وإلغاء → احتفاظ → purge |
| **سلامة المال** | primitives ممتازة، والـ flows مكسورة (الدفع الجزئي، التقسيم، الإلغاء، المرتجعات، الوردية، الرصيد الافتتاحي) | عقد checkout واحد، وidempotency، ومرتجعات مربوطة بمصدر، وإلغاء بعكس كامل، وتستات invariants على MySQL |
| **التشغيل** | حوالي 120 سكريبت ad-hoc، وأسرار في git، واستضافة مشتركة واحدة | pipeline واحد بالـ CI، وsecret store، وstaging، وDB users بأقل صلاحية لكل مستأجر، وqueue worker تحت supervisor، ولوجات وتنبيهات مركزية |
| **الأجهزة** | Android مربوط بـ `2m`، وتحديثه مزيف، وElectron قابل لـ RCE | تطبيق متعدد المستأجرين، وتحديثات موقّعة (Authenticode وchecksum للـ APK) |
| **اللغة** | العربي بس عمليًا | locale middleware، وswitcher بـ RTL/LTR، واستثناءات domain مترجمة، وmaster للوحدات (`is_discrete`) بدل أسماء عربية hardcoded |
| **عمومية المنتج** | مفردات قهوة، والـ blender شغال للكل، ووحدة نص حر | flag للـ business type، وتحويل الـ blender لـ recipes/BOM add-on بـ `blender.access`، وbarcode ميزان ووحدات متعددة |
| **الامتثال** | مفيش ضرايب ولا فاتورة إلكترونية | VAT + تكامل ETA (حسب السوق المستهدف) |
| **الاختبارات** | 306 تست على sqlite واحد، وبعضها بيثبّت الثغرات | harness بمستأجرين، وتستات أمان وعزل، وMySQL CI، وE2E في الـ release gate |

---

## 6. Quick wins (يوم واحد أو أقل لكل بند، وأثرها كبير)

1. امسح `quick-login` و`workspace-users`، واعكس `AuthApiTest:287`، وخلّي `LoginView` على `password` افتراضيًا. **حوالي ساعة.**
2. امسح قايمة الموبايلات من الـ 7 أماكن + ملفات الـ lang. **حوالي ساعتين.**
3. `EnsureCentralContext` على `/super-admin/*` + تست. **نص يوم.**
4. Deny-list لـ `super_admin` في `Store/UpdateUserRequest` و`UpdateRolePermissionsAction`، وفصل الـ seeder. **نص يوم.**
5. allowlist للـ `locale` في endpoint الترجمات + تست regression. **ساعة.**
6. انقل راوتات الطباعة جوه الـ auth، أو حوّلها لـ signed URLs. **نص يوم.**
7. `tenant('id')` في مفاتيح `erp_abc_*` و`erp_pnl_*`، ومفتاح Spatie لكل مستأجر. **نص يوم.**
8. `throttle` على كل راوتات الـ auth العامة، و`config/sanctum.php` بـ expiry. **ساعة.**
9. وقّف `backup:telegram` و`notify:*` في `routes/console.php`. **15 دقيقة.**
10. POS: أول سطر في `submitInvoice` يبقى `if (isSubmitting.value) return`، وتجاهل `e.repeat`، وابعت `cashReceived` في الدفع الجزئي. **ساعتين.**
11. `StockService::deductStock`: الصف الناقص = 0 + exception. وseed صفوف صفرية في `CreateStoreAction`. **نص يوم.**
12. امنع حذف أو restore أو force-delete المرتجعات، واسحب `returns.manage` (للحذف) من الكاشير وأمين المخزن. **ساعة.**
13. `EnsureTenantActive` بعد `ResolveApiTenancy`، و`FeatureGate.vue` يمنع افتراضيًا. **نص يوم.**
14. Electron: regex للـ tenant + فحص الـ hostname + `will-navigate`/`setWindowOpenHandler` + فحص الـ sender في IPC. **نص يوم.**
15. CI: `working-directory: backend`، وشيل `|| echo`. **ساعة.**
16. `git rm` لسكريبتات `migrate:fresh` و`deploy_all_locations.py`، وامسح السطور 124 و129 من `deploy_root_baraa.py`، وحط guard للإنتاج في `PopulateRealisticTenantDataCommand`. **ساعة.**
17. صلّح الـ casing بتاع `services/` و`Services/` قبل أي build على Linux. **ساعة.**

---

## 7. خارطة الطريق

> الأسابيع دي تقديرية لفريق من 2–3 مطورين. التوازي ممكن بين الـ backend والـ frontend/devops.

### Phase 0 — أمان واستقرار فوري (أسبوع لـ 2) — **شرط لأي حاجة بعدها**
| الأولوية | البند | الجهد |
|---|---|---|
| P0 | كل إجراءات القسم 4 (التجميد، تنضيف `76f32ce0`، تدوير الأسرار، secret scanning) | 2–3 أيام (المالك + مطور) |
| P0 | Hotfixes الصفوف 1–7 (quick-login، الموبايلات، دور super_admin، السياق المركزي، الكاش، الـ traversal، الطباعة) + تستات regression لكل واحدة | 3–4 أيام |
| P0 | إيقاف الـ scheduler المركزي، وتنضيف سكريبتات الـ deploy المدمرة، وguard لأمر populate | يوم |
| P0 | Electron hotfix (الـ deep link + الـ updater)، وإيقاف توزيع النسخ القديمة | 1–2 يوم |
| P0 | POS: الدفع الجزئي وحماية الـ double-submit (صفوف 12 و15 الجزء السريع) | يوم |
| P0 | التحقيق الجنائي في الـ logs، وaudit أدوار وموبايلات كل المستأجرين | 1–2 يوم |

**معيار الخروج:** مفيش أي راوت من غير auth إلا اللي في allowlist صريح، ومفيش طريقة لمستخدم مستأجر يوصل لـ `/super-admin`، ومفيش أسرار في الريبو، وتستات الأمان الجديدة خضرا.

### Phase 1 — أساس الـ SaaS (4–6 أسابيع)
| الأولوية | البند | الجهد |
|---|---|---|
| P1 | فصل الهوية: guard مركزي + `CentralUser` + راوتات مركزية منفصلة، ومسح الـ central fallbacks، وتوصيل الـ impersonation الرسمي مع audit | 1.5–2 أسبوع |
| P1 | عزل الكاش بـ `CacheTenancyBootstrapper` + Redis، واختبار معماري لمفاتيح الكاش | 3–5 أيام |
| P1 | `EnsureTenantActive` + job الـ lifecycle اليومي + إلغاء الـ tokens عند الإيقاف + تذكيرات | 1 أسبوع |
| P1 | `PlanLimitGuard` + `feature:` middleware + gating في الـ UI + تصحيح `checkLimit`، وpin كل موديلات المنصة على الاتصال المركزي (Plan, Subscription, PlanFeature, Setting) | 1–1.5 أسبوع |
| P1 | عزل الفروع: `ResolveActiveStore` + `store_id` على الـ payments + scopes + تستات | 1 أسبوع |
| P1 | Backup مشفر لكل مستأجر + retention + restore drill، والـ lifecycle الآمن لحذف المستأجر (وبعده بس إصلاح الـ parse errors) | 1 أسبوع |
| P1 | Release pipeline واحد (CI → tests → releases/symlink → `tenants:migrate` → health → rollback)، ونقل الأسرار، وتقاعد سكريبتات الـ root | 1–1.5 أسبوع |
| P1 | Harness اختبار متعدد المستأجرين + job على MySQL 8 في الـ CI + route-security gate + gitleaks | 1 أسبوع |

### Phase 2 — سلامة مالية واختبارات (4–6 أسابيع، جزء منها ممكن يتوازى مع Phase 1)
| الأولوية | البند | الجهد |
|---|---|---|
| P1 | دمج إصلاحات `main` (`d08edc92` وPR #3: المتوسط المرجح، توزيع الخصم، منع الـ restore الساذج) **قبل** أي إصلاح مالي تاني | 2–3 أيام |
| P1 | عقد checkout واحد للـ POS (`payments` و`expenses`) + `client_uuid` idempotency + contract tests | 1 أسبوع |
| P1 | الرصيد الافتتاحي كبيان أساسي + audit للبيانات الحالية | 3–5 أيام |
| P1 | إعادة تصميم المرتجعات (مربوطة بمصدر، بسقف، إلغاء بعكس) + منع الحذف الفيزيائي في الـ trash | 1.5 أسبوع |
| P1 | إلغاء الفاتورة الكامل + تصحيحات المشتريات والموردين + إقفال الوردية | 1–1.5 أسبوع |
| P2 | توحيد الـ P&L (snapshot التكلفة + المرتجعات) | 3–4 أيام |
| P1 | أمر reconciliation read-only يتشغل على نسخة من الإنتاج عشان نعرف حجم الانحراف الموجود فعلًا، وبعدين أدوات إصلاح بـ `--dry-run` | 1 أسبوع |
| P1 | تستات invariants (الرصيد = الافتتاحي + الـ ledger، والمخزون = مجموع الحركات) + تستات تزامن على MySQL + E2E للـ POS (نقدي، جزئي، آجل، مقسم، مصاريف، ضغط مزدوج) | 1.5 أسبوع |

### Phase 3 — النمو (8–12 أسبوع، بعد أول عملاء مدفوعين)
| الأولوية | البند | الجهد |
|---|---|---|
| P2 | Signup ذاتي + تجربة مجانية + بوابة دفع + فواتير اشتراك + صفحة billing، وتسعير واحد معتمد | 3–4 أسابيع |
| P2 | Bilingual حقيقي: locale middleware، وswitcher بـ RTL/LTR، والمفاتيح الناقصة، واستثناءات domain مترجمة، وCI لتغطية المفاتيح | 1.5–2 أسبوع |
| P2 | عمومية المنتج: master للوحدات و`is_weighted` وbarcode الميزان، وتحويل الـ blender لـ add-on، وشيل مفردات القهوة، وbranding كامل لكل مستأجر (Android/desktop/manifest) | 2–3 أسابيع |
| P2 | Android متعدد المستأجرين + تحديث حقيقي، وElectron بـ electron-updater + Authenticode | 1.5–2 أسبوع |
| P2 | VAT والفاتورة الإلكترونية (ETA) | 3–5 أسابيع (حسب القرار) |
| P3 | Offline POS بقايمة انتظار idempotent، وعروض وولاء، وحد ائتمان للعملاء، وexport/import للمستأجر | حسب الأولوية |
| P2 | الانتقال من الاستضافة المشتركة لبنية فيها staging وفصل بيئات | 2–3 أسابيع |
| P2 | تحديث التوثيق الحاكم (`AGENTS.md`، `docs/03-architecture`، `tasks-breakdown.md`) | 3–5 أيام (يتعمل بدري ويتحدث باستمرار) |

**الإجمالي لحد ما يبقى جاهز لأول عملاء مدفوعين (Phase 0 + 1 + 2):** تقريبًا **10–14 أسبوع**.

---

## 8. نقاط القوة

- **العزل على مستوى التخزين حقيقي:** DB منفصل لكل مستأجر بـ stancl v3، وbootstrappers للـ Database والـ Filesystem والـ Queue، و35 tenant migration منفصلين عن 19 central migration. وموديلات `Tenant` و`Domain` و`AppVersion` و`CentralUser` مثبتة على الاتصال المركزي.
- **الـ Sanctum tokens معزولة لكل مستأجر في المسار الطبيعي:** البحث بالـ id في DB المستأجر + `hash_equals`، و`ResolveApiTenancy` بيشتغل قبل `ApiTokenAuth`.
- **الـ primitives المالية ممتازة:** `DECIMAL(12,3)` + `decimal:3` في 59 عمود ومفيش ولا float، و`bcmath` (scale 3، و6 للنسب)، و`DB::transaction` + `lockForUpdate` بترتيب ثابت (Item ← source ← destination). والأرصدة بتتحسب من المستندات (self-healing). و`StockMovement` بـ before/after، وsnapshot للتكلفة في سطور الفاتورة، وترقيم unique.
- **طبقات نظيفة:** FormRequest ← DTO ← Action ← Resource، ومفيش `$guarded = []`، ومفيش sort بعمود جاي من العميل، و`per_page` محدود، وeager loading انتقائي، وCSV streaming. وفيه 21 Policy.
- **موديل بيانات SaaS جاهز للفوترة:** plans (أسعار DECIMAL، و5 حدود، وfeatures JSON)، وplan_features، وsubscriptions، وحالة وتواريخ المستأجر، و`TenantFeatureManager` بيدمج الـ overrides. والـ provisioning طبقاته نظيفة وقابل يتستخدم في signup.
- **الـ SPA مبني صح:** Vue 3 بـ script setup، وlazy routes، وaxios client واحد، وguards بالـ meta، وviews رفيعة + composables، وskeleton وempty states، وPOS بيشتغل بالكيبورد، وsuper-admin SPA شغال.
- **بنية الترجمة سليمة:** `lang/*.php` مصدر واحد، و`lang:export` بيشتغل تلقائيًا، وتطابق حوالي 2.8 ألف مفتاح في ar/en، ومفيش اختلاف في الـ placeholders.
- **Electron بإعدادات أساسية سليمة** (`contextIsolation`، و`nodeIntegration:false`، وcontextBridge)، وطباعة RTL صامتة. وCapacitor بيخزن بيانات البيومترك في Keystore.
- **قاعدة اختبارات كبيرة:** 306 تست و1389 assertion، وتغطية 123 من 150 راوت API، وتستات مشتريات وتحويلات دقيقة، وPlaywright آمن على localhost.
- **الـ dumps لسه ما اتعملهاش push**، فالتسريب ده ممكن نمنعه بالكامل.

---

## 9. أسئلة مفتوحة تحتاج قرار الـ CTO

1. **التبديل السريع للكاشير (quick-login):** هل هو متطلب منتج حقيقي لأجهزة POS مشتركة؟ لو آه، الموافقة على تصميم PIN مربوط بجهاز وفرع (Phase 1). ولو لأ، يتمسح نهائي.
2. **هل اتعرضنا لاختراق فعلًا؟** محتاجين صلاحية للـ access logs، ومحتاجين نعرف إيه شات Telegram المستلم للـ backups. هل اتبعتله dumps مركزية؟ ومين عنده وصول ليه؟
3. **حالة الريبوهات:** هل `sroor-cofe-erp` و`erp-hub` private؟ ومين عنده صلاحية قراءة؟ وده بيحدد حجم الـ purge المطلوب.
4. **الـ DB المركزي في الإنتاج:** هل لسه فيه جداول تشغيلية قديمة من أيام المستأجر الواحد (فواتير، عملاء)؟ لو آه، راوتات `web.php` العامة بتسرّب بيانات حية حاليًا.
5. **إعدادات الإنتاج الفعلية:** `CACHE_STORE` (سكريبت الـ deploy بيحط `file`)، و`config:cache`، و`APP_DEBUG`، و`TELESCOPE_ENABLED`. ودي بتحدد خطورة تسريب الكاش والـ traversal.
6. **التسعير:** أنهي نموذج معتمد؟ (الـ seeder فيه 4 باقات، ووثيقة الـ SaaS 3، والدراسة/البروشور 6.) وهل الـ add-ons بتتفوتر من جوه النظام؟ وأنهي بوابة دفع؟
7. **سياسة إلغاء الفاتورة:** إذن صرف نقدي ولا رصيد للعميل (خصوصًا العميل النقدي)؟ وهل نمنع الإلغاء بعد وجود مرتجع؟
8. **الإنجليزي:** لكل مستخدم ولا لكل مستأجر؟ وهل هو مطلوب في الإصدار الأول؟
9. **الامتثال الضريبي:** هل السوق الأول محتاج VAT وتكامل الفاتورة الإلكترونية المصرية من البداية؟
10. **الاستضافة:** هنفضل على حساب Hostinger مشترك واحد ولا ننقل لبنية فيها staging وفصل بيئات وRedis قبل أول عميل مدفوع؟ وCREATE DATABASE ولا DBs متعملة مسبقًا؟
11. **Offline POS:** banner + منع البيع في الإصدار الأول، ولا طابور idempotent؟
12. **البرنشات:** إمتى ندمج إصلاحات `main` (`d08edc92` وPR #3) في `feature/multi-tenant`؟ ونقفل `feature/api-migration` اللي سكريبت الـ deploy بيعمله deploy؟
13. **الـ coffee blender:** add-on عام (recipes/BOM) ولا يتشال من المنتج العام؟
14. **التوثيق:** هل نعلّم `docs/01`–`05` إنها historical؟ ونقرر مصير `backend/docs` و`backend/e2e` وسويت `tests_e2e` بالـ Python؟

---

## 10. ملحق — ملفات الأبعاد التفصيلية

| # | الملف | المحتوى |
|---|---|---|
| 01 | [01-saas-tenancy.md](01-saas-tenancy.md) | الـ tenancy، والـ super-admin، والـ provisioning، والـ entitlements (53 finding) |
| 02 | [02-security.md](02-security.md) | الأمان والتحكم في الوصول (58) |
| 03 | [03-financial-integrity.md](03-financial-integrity.md) | سلامة الحسابات والمخزون (79) |
| 04 | [04-architecture.md](04-architecture.md) | معمار الـ backend والجودة والأداء (76) |
| 05 | [05-frontend.md](05-frontend.md) | Vue SPA وUX وRTL وPOS والأجهزة (83) |
| 06 | [06-i18n.md](06-i18n.md) | الترجمة ar/en (78) |
| 07 | [07-testing-qa.md](07-testing-qa.md) | الاختبارات والجودة (72) |
| 08 | [08-devops.md](08-devops.md) | DevOps والـ deploy ونضافة الريبو (67) |
| 09 | [09-product-docs.md](09-product-docs.md) | نطاق المنتج والتوثيق وخارطة الطريق (81) |
| — | [_critical-high.json](_critical-high.json) | ملخصات الـ leads + كل الـ critical/high (بصيغة machine-readable) |

> ⚠️ **تنبيه أمني عن ملفات الملحق نفسها:** ملفات الأبعاد و`_critical-high.json` فيها **أرقام موبايل الـ allowlist بالأرقام الصريحة**، وإشارات لباسورد افتراضي معروف. لازم تتحجب الأرقام دي قبل ما الفولدر ده يتعمله commit أو يتشارك (الأماكن متسجلة في تقرير التسليم).

---

## حالة Phase 0 (تحديث 2026-10-08، بعد Phase 0.5)

> التفاصيل في [سجل Phase 0](../../history/2026-10-08/01-phase0-security-hotfixes.md) و[سجل Phase 0.5](../../history/2026-10-08/04-phase0-5-fixups.md). الشغل **uncommitted** ولسه متعملهوش deploy، فـ"متصلح" هنا معناها متصلح في الكود وعليه تستات خضرا، **مش في الإنتاج**. الـ suite الكاملة: **591/591** (4724 assertion). الـ desktop: 62/62. Larastan: 0 أخطاء والـ baseline 537 مدخل. `code-reviewer`: البند الـ blocking اتقفل في الـ fixup. `security-auditor`: approve.

| الصف | الحالة | ملاحظة |
|---|---|---|
| 1 quick-login + قايمة المستخدمين | ✅ متصلح | Phase 0 شاله، وPhase 0.5 رجّعه **للاختبار فقط** (قرار CTO §13): flag default off، مش production، مش central، بيرفض `super_admin`، توكن بـ ability محدودة وTTL، throttle 5/د. مفتوح: `DenyQuickLoginToken` deny-list مش بيغطي عمليات الفروع/الإلغاء/المخزون لدور `admin`. التوكنات القديمة لسه محتاجة تتلغي بعد الـ deploy. |
| 2 allowlist الموبايلات | ✅ متصلح | `PlatformSuperAdmin` المصدر الوحيد، والواجهة بتعتمد على `is_super_admin` بس. الموبايلات اتشالت من الـ seeders و`SpaLayout.vue` وتستات/e2e الـ backend وقايمة الـ desktop، وعليها guard (`NoHardcodedRealPhonesTest`). لسه موجودة في `e2e/` على مستوى الـ root و`backend/tests/e2e/reports/` (برة الـ guard). |
| 3 دور `super_admin` في المستأجر | ✅ متصلح | seeder central/tenant منفصلين، و`tenants:seed` بقى يستخدم `TenantDatabaseSeeder`، وأمر الـ audit بيعد صفوف User بس. تنضيف الـ DBs الحالية لسه محتاج قرار. |
| 4 الـ control plane من سياق المستأجر | ✅ متصلح | `EnsureCentralContext`. |
| 5 الكاش المشترك | ✅ متصلح (حل سريع) | `TenantCache` ومفتاح Spatie لكل مستأجر. الحل الاستراتيجي (Redis + bootstrapper) لسه مفتوح. |
| 6 path traversal في الترجمات | ✅ متصلح | وعليه throttle 60/د. تدوير الأسرار ومراجعة الـ logs لسه **مفتوحين**. |
| 7 الطباعة والـ export من غير auth | ✅ متصلح | ومعاه `ReportStoreFilter`. الطباعة من الـ POS بقت `?autoprint=true`. |
| 8 الـ master key المركزي | ⛔ مفتوح | الـ fallback المركزي فضل (Sanctum بس، من غير plaintext) وعليه TODO لـ Phase 1. |
| 9 ضعف الـ tokens | 🟡 جزئي | اتشال `?api_token=` وfallback عمود `api_token`، وTelescope بقى signed link لمرة واحدة 60 ثانية، وفيه throttle على login/resolver. مفتوح: expiry لتوكنات Sanctum العادية. |
| 10 parse errors في الحذف | 🟡 جزئي | الـ parse error اتصلح في الكود والملفين رجعوا للتحليل. الـ lifecycle الآمن (cancel → retention → purge) لسه مفتوح، والحذف لسه فوري. |
| 12 الدفع الجزئي في الـ POS | ✅ متصلح | |
| 14 الـ split والمصاريف | ✅ متصلح | خيار A: الكاش يزيد والباقي من سطور الكاش ويتخزن في `change_amount`، غير الكاش ≤ المستحق، والواجهة بتحسب بـ `decimal.js`. 66 تست. مفتوح: `updateInvoice` مش بيطبّق العقد الجديد. |
| 15 الـ idempotency | ✅ متصلح | مفيش تست تزامن على MySQL، والـ fingerprint مش شامل العميل. |
| 24 scheduler Telegram | 🟡 جزئي | اتوقف بـ flag الـ default بتاعه off. الـ backup لكل مستأجر لسه مفتوح. |
| 27 Electron RCE | ✅ متصلح في الكود | الـ checklist اليدوية في Electron **ماتشغلتش**. |
| 30، 31 أسرار SSH/APP_KEY/MySQL | ⛔ مفتوح | التدوير على المالك. |
| 32 webhook الـ deploy | 🟡 جزئي | النسختين HMAC. `deploy.yml` لسه بيبعت الـ token القديم في الـ query، فالـ auto-deploy هيفشل بـ 405 لحد ما يتحدث (على المالك). |
| 33 dumps في `76f32ce0` | 🟡 جزئي | `.gitignore` اتحدث، بس الملفات لسه متتبعة. |
| 34 سكريبت الـ deploy المدمر | 🟡 جزئي | السطور 124 و129 في `deploy_root_baraa.py` لسه موجودة. |
| 35 سكريبتات `migrate:fresh` | ⛔ مفتوح | |
| 37 credentials افتراضية | 🟡 جزئي | الـ seeders والأمر `populate` بقوا من env أو باسورد عشوائي، وsuper admin واحد بس. سكريبتات `test_live_*` في الـ root لسه زي ما هي، وباسوردات الإنتاج محتاجة تتغير. |
