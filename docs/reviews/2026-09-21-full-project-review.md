# المراجعة الشاملة للمشروع — 2026-09-21

> **النطاق:** المشروع بالكامل (Laravel backend، Vue SPA، Capacitor، Electron، الاختبارات، CI/CD، الريبو، الوثائق).
> **زاوية التقييم:** المنتج **ليس نظام محمصة بن**، بل **SaaS متعدد العملاء (multi-tenant)** لإدارة المحلات والفروع — بيع قطاعي، جملة، وبيع بالوزن (كيلو) — مع سوبر أدمن يدير العملاء والخطط والاشتراكات.
> **المنهجية:** 8 مراجعات متخصصة متوازية (قراءة فقط) + تحقق يدوي من أخطر النتائج. لم يُعدَّل أي كود، ولم يُلمس الـ production، ولا تحتوي هذه الوثيقة على أي قيمة سرية (المواقع فقط).
> **الفرع:** `feature/multi-tenant` — كل المشاكل المذكورة موجودة مسبقًا في الكود.

---

## 1. الخلاصة التنفيذية

**الحكم: النظام غير جاهز حاليًا لاستضافة عملاء متعددين يدفعون اشتراكات.** الأساس الهندسي جيد (DECIMAL(12,3) + bcmath، transactions و locks في المسارات الأساسية، نموذج فروع سليم، SPA نظيف من Livewire/Inertia)، لكن هناك أربع فجوات تمنع الإطلاق التجاري:

1. **ثغرات أمنية حرجة** تسمح لأي شخص مجهول بالدخول كأدمن على أي tenant، ولأي مستخدم عادي بالتحول إلى سوبر أدمن للمنصة.
2. **طبقة الـ SaaS شكلية:** الإيقاف، انتهاء الاشتراك، حدود الخطة، والـ features كلها مخزنة في الجداول لكن لا يفرضها أي كود على الـ API.
3. **أرقام الخزنة والورديات والأرباح غير موثوقة** (حساب مزدوج، مرتجعات بلا عكس، مدفوعات تبقى بعد الإلغاء) رغم أن حساب الكميات سليم.
4. **نموذج الأصناف ما زال "وحدة واحدة + سعر واحد"** — لا وحدات بتحويلات، لا قوائم أسعار حقيقية، لا باركود ميزان، ولا ضريبة؛ وواجهة إدخال الوزن في الـ POS غير موصّلة أصلًا.

### بطاقة التقييم (تقدير المراجع)

| المحور | التقييم | ملخص |
|---|---|---|
| الأمان | 🔴 2/10 | 6 حرجة، 5 عالية، 8 متوسطة، 6 منخفضة |
| طبقة SaaS (tenants / خطط / اشتراكات) | 🔴 3/10 | البيانات موجودة، الفرض غائب، ملفان لا يعملان |
| السلامة المالية والمخزنية | 🟠 5/10 | الأساس ممتاز، دفاتر الخزنة/الوردية/الأرباح بها أخطاء مؤكدة |
| ملاءمة المنتج (قطاعي / جملة / كيلو) | 🟠 4/10 | الكسور مدعومة في الـ backend فقط |
| الـ Frontend | 🟡 6/10 | بنية جيدة، `PosView` و`SpaLayout` ضخمان، ~3,900 سطر ميت |
| الترجمة (i18n) | 🟠 4/10 | الإنجليزية غير قابلة للوصول، 80 مفتاح ناقص |
| الاختبارات | 🟠 4/10 | 305/306 ناجح لكن بلا عزل ولا تزامن ولا rollback فعلي |
| DevOps ونظافة الريبو | 🔴 2/10 | أسرار في الريبو، deploy يعدّل بيانات حية، لا backup للـ tenants |

---

## 2. إجراءات فورية (قبل أي شيء آخر)

| # | الإجراء | السبب |
|---|---|---|
| 1 | **لا تشغّل `deploy_root_baraa.py`** حتى يُصلح `.gitignore` | السطر 30 ينفذ `git add .` و`backups/` غير مُتجاهَل → سيرفع dumps قاعدة البيانات الحية + 43 سكربت بكلمات سر إلى GitHub. كما أنه يعمل reset للسيرفر على `origin/feature/api-migration` المتأخر 7 commits عن الفرع الحالي (ينشر كودًا قديمًا) ويشغّل seeders و`tenant:populate-realistic-data` على tenant حي في كل مرة |
| 2 | **تدوير الأسرار الأربعة:** كلمة سر SSH، كلمة سر قاعدة البيانات، token الـ webhook، و`APP_KEY` (مع `APP_PREVIOUS_KEYS`) | موجودة في ملفات tracked وفي تاريخ git: `deploy_root_baraa.py:12,85,99`، `.github/workflows/deploy.yml:49`، `update_webhook.php:4`، `backend/public/update_webhook.php:4`، و49 سكربت tracked في الـ root |
| 3 | **حذف `update_webhook.php` من السيرفر ومن `public/`** | endpoint عام ينفذ `git reset --hard` + `migrate --force` بـ token في الـ URL ويعيد مخرجات الـ shell |
| 4 | **حذف `/auth/quick-login` و`/auth/workspace-users`** | دخول بدون كلمة مرور (تفاصيل في القسم 3) |
| 5 | **حذف أرقام التليفون الثابتة من `Gate::before`** ومنع دور `super_admin` داخل قواعد الـ tenants | تصعيد صلاحيات إلى سوبر أدمن المنصة |
| 6 | **إضافة متغيرات Telegram وهمية في `phpunit.xml` + `Http::preventStrayRequests()`** | تشغيل الاختبارات بدونها يرسل تنبيهات عجز وردية وهمية إلى شات Telegram الحقيقي (انظر القسم 11) |

---

## 3. الأمان

### حرجة (Critical)

| # | المشكلة | الموقع | السيناريو |
|---|---|---|---|
| C1 | **دخول بدون كلمة مرور لأي مستخدم في أي tenant** ✅ تم التحقق يدويًا | `backend/routes/api.php:28-29`، `app/Actions/Auth/ApiQuickLoginAction.php:26-55` | مجهول يرسل `X-Tenant: <slug>` ثم `POST /auth/quick-login {"login":"1"}` → token بصلاحيات `['*']` لمستخدم id=1 (الأدمن). و`/auth/workspace-users` يعرض قائمة المستخدمين للزوار. لا throttle ولا PIN. والـ SPA يجعل هذا المسار هو الافتراضي (`LoginView.vue` → `loginMode='quick'`)، واختبار `AuthApiTest.php:296` يثبّته كسلوك صحيح |
| C2 | **أرقام تليفون ثابتة في الكود = سوبر أدمن** ✅ تم التحقق يدويًا | `app/Providers/AppServiceProvider.php:38,49` + `TelescopeServiceProvider.php:82`، `routes/web.php:38`، `ToggleTenantStatusRequest.php:13`، `OverrideTenantFeatureRequest.php:13` | أي كاشير يغيّر رقمه من `PUT /profile` إلى الرقم السحري → كل `can()` تنجح → يصل إلى `/super-admin/*` (حذف tenant، رفع إصدار تطبيق إجباري لكل العملاء) |
| C3 | **أدمن الـ tenant يستطيع إسناد دور `super_admin`** | `StoreUserRequest.php:23`، `UpdateUserRequest.php:26`، `PermissionsSeeder.php:70-77` | الدور يُزرع في كل قاعدة tenant والـ validation هو `exists:roles,name` فقط؛ الإخفاء في الواجهة فقط |
| C4 | **صفحات طباعة الفواتير بدون مصادقة** | `routes/tenant.php:60-74` | `GET /invoices/1..N/print/a4` على أي دومين tenant يكشف اسم العميل وتليفونه ورصيده والأسعار |
| C5 | **أسرار الإنتاج في ملفات tracked وفي تاريخ git** | انظر القسم 2 | أي شخص لديه قراءة للريبو يحصل على shell على السيرفر وبالتالي كل قواعد الـ tenants. كل السكربتات تستخدم `AutoAddPolicy` (لا تحقق من host key) |
| C6 | **الـ deploy webhook نقطة تنفيذ كود عامة** | `backend/public/update_webhook.php:4-38` | token ثابت في query string، مقارنة غير constant-time، بلا توقيع ولا IP allowlist |

### عالية (High)

- **H1 — `X-Store-Id` لا يُتحقق منه أبدًا:** `StoreAccess`/`StoreScope` معرّفان كـ alias في `bootstrap/app.php:27-28` لكن غير مطبّقين على أي من الـ 140 route في `api/v1`. `ApiTokenAuth.php:83-86` يثق في الـ header، و`show/cancel/update/destroy` تستخدم `findOrFail($id)` مجردة (`InvoiceController.php:122`، `ExpenseController.php:166`، `PurchaseController.php:114`…). كاشير فرع A يقرأ ويعدّل بيانات فرع B. `TreasuryController.php:34` يرجع صامتًا للفرع 1.
- **H2 — الإيقاف وانتهاء الاشتراك غير مفروضين:** `ResolveApiTenancy.php` لا يفحص `status` ولا `subscription_ends_at`؛ والـ tokens لا تنتهي أبدًا.
- **H3 — Electron يسمح بتحميل وتشغيل أي ملف تنفيذي:** `desktop/preload.js:39-40` + `src/updater/nativeUpdater.js:69-102` (يقبل `http:`، بلا توقيع ولا hash، ثم `spawn(exe,['/S'])`). رابط `sroor://connect?tenant=<قيمة خبيثة>` غير مُتحقَّق منه (`main.js:26-45`)، و`will-navigate` فارغ. أي XSS = تنفيذ كود على جهاز الكاشير.
- **H4 — قناة التحديثات ناقل برمجيات خبيثة** بمجرد استغلال C2/C3: `StoreAppVersionRequest.php:24` بلا فحص mime أو توقيع.
- **H5 — الـ cache غير معزول بين الـ tenants:** `config/tenancy.php:36` (الـ bootstrapper معطّل)، والمفاتيح `erp_pnl_{store}_{from}_{to}` في `ProfitLossService.php:29` و`InventoryAnalyticsService.php:29` بلا tenant id → تقرير أرباح tenant يظهر لآخر 15 دقيقة، وكذلك cache صلاحيات spatie. و`clearCache()` يمسح مفتاحًا لا يُكتب أبدًا.

### متوسطة ومنخفضة (مختصر)

- `ApiTokenAuth` بديل عن `auth:sanctum`: لا انتهاء صلاحية، الـ token يُنسخ نصًا صريحًا إلى `users.api_token`، ويُقبل في `?api_token=`.
- جسر الأدمن المركزي يطابق بالتليفون وينشئ أدمن ظل داخل الـ tenant بلا audit (`ApiLoginAction.php:37-59`).
- Telescope: الـ token في الـ URL، والبوابة تقبل دور `admin` وأي إيميل على دومين الشركة، ومفعّل افتراضيًا في الإنتاج (`config/telescope.php:19`).
- لا rate limiting على الـ routes العامة (resolver، تحميل APK بحجم 150MB+)، والـ resolver يكشف وجود الـ tenant.
- routes تصدير وطباعة بلا مصادقة في `routes/web.php:56-65,130-252,278-282`، وتبديل فرع بلا مصادقة `:258-268`.
- كلمات سر قواعد الـ tenants مخزنة نصًا في `tenants.data` وتُعاد في الاستجابة (`SuperAdminApiController.php:102`).
- `composer.json`: `sanctum` و`telescope` بإصدار `"*"`، والـ CI يشغّل `composer update`.
- كلمة مرور `min:6`، أنماط fail-open (`if ($user && !…)`، `?? true`، `return true` في `authorize()`)، 21 موضع يعيد `getMessage()` للعميل، CSV formula injection في `ExportService.php:29-32`، `cleartext: true` و`allowBackup="true"` في Android، لا يوجد `config/cors.php`.

### ما تم فحصه وهو سليم

token من tenant A لا يعمل على tenant B · لا `$guarded = []` ولا `$request->all()` · لا مدخلات مستخدم في `whereRaw/orderByRaw/DB::raw` · `v-html` على الـ paginator فقط · `contextIsolation: true` و`nodeIntegration: false` في Electron · `AppVersion/CentralUser/Tenant/Domain` مثبتة على الاتصال المركزي · login عليه throttle.

---

## 4. طبقة الـ SaaS (العملاء، الخطط، الاشتراكات)

| القدرة | الحالة | الدليل |
|---|---|---|
| الجداول المركزية (tenants, domains, plans, subscriptions, app_versions) | جزئي | لا جداول مدفوعات/فواتير اشتراك/استخدام/إعدادات منصة |
| تثبيت الاتصال المركزي للموديلات | جزئي | `Plan`، `PlanFeature`، `Subscription` بلا `getConnectionName()` → "table not found" عند وصول `X-Tenant` |
| إنشاء tenant (provisioning) | جزئي | متزامن داخل HTTP request، بلا rollback، و`SafeMySQLDatabaseManager.php:21-24` يعيد `true` حتى لو فشل `CREATE DATABASE` |
| تسجيل ذاتي للعملاء | مفقود | المسار الوحيد: `POST /super-admin/tenants` |
| إيقاف tenant | معطوب | الفحص في workspace resolver فقط |
| **حذف tenant** | **معطوب** ✅ تحقق يدوي | `app/Actions/Tenants/DeleteTenantAction.php:12` — كل متغيرات `$` ممسوحة (parse error) |
| **تعديل إعدادات قاعدة tenant** | **معطوب** | `UpdateTenantDatabaseConfigAction.php:11` — نفس التلف |
| حدود الخطة (users / stores / items / invoices) | مخزنة فقط | `Tenant::checkLimit()` بلا أي مستدعٍ، ويقرأ عمود `tenant_id` غير موجود |
| Feature flags لكل خطة | مخزنة فقط | `TenantFeatureManager::isFeatureEnabled()` بلا مستدعٍ؛ `useModules.js` يقرأ `modules.json` ثابتًا وقت الـ build؛ `FeatureGate.vue` غير مستورد في أي مكان |
| التجديد / الانتهاء / فترة السماح / المدفوعات / بوابة دفع | مفقود | `toggle-status` مع `extend_days` يغيّر التاريخ بلا سجل دفع؛ MRR يجمع صفوفًا لا تنتهي ويُعاد كـ `float` |
| Impersonation | غير موصّل | `ImpersonateTenantAction.php` بلا route |
| routes السوبر أدمن | خطر | داخل مجموعة `ResolveApiTenancy` (`routes/api.php:196`) → قابلة للوصول من سياق tenant |
| `routes/tenant.php` | معظمه ميت | يحجبه الـ catch-all في `routes/web.php:337`؛ والباقي بلا auth |
| الجدولة والـ jobs | غير tenant-aware | `routes/console.php:25-38` يشغّل `notify:*` و`backup:telegram` مرة واحدة في السياق المركزي؛ الـ 4 Jobs لا تُستدعى أبدًا؛ الـ queue فعليًا `sync` |
| النسخ الاحتياطي | مفقود فعليًا | `DatabaseBackupService` ينسخ الاتصال الحالي فقط (المركزي)، بـ `addslashes`، يحمّل الملف كله في الذاكرة، ويرسله إلى Telegram. لا استرجاع |
| نموذج الفروع | **سليم** | `stores`، `store_stocks` (unique store+item + `custom_selling_price`)، `store_user`، التحويلات |
| إدارة إصدارات التطبيق | سليم | `SuperAdminAppVersionController` |
| `env()` خارج `config/` | خطأ إنتاجي | `TenantResource.php:18`، `TenantProvisionerService.php:58`، `routes/tenant.php:44` — مع `config:cache` ترجع للافتراضي (`localhost`) |

**قابلية التوسع:** `ProfitLossService.php:59-64` يحمّل كل الفواتير المؤكدة بأصنافها في الذاكرة؛ الفهارس كلها أحادية العمود (ينقص `invoices(store_id,status,invoice_date)`، `stock_movements(item_id,store_id,created_at)`)؛ `->unique()->index()` ينشئ فهارس مكررة؛ تشغيل migrations عبر HTTP بلا تتبع إصدار schema لكل tenant.

**الطبقات:** 98 Action و53 Request و28 DTO لكن 3 Filters فقط. أسوأ المخالفين: `ReportPrintController.php` (597 سطر)، `SuperAdminApiController.php` (435)، `InvoiceService.php` (716)، `TreasuryService.php` (525)، `PurchaseService.php` (500)، `routes/web.php` (340 سطر بها closures بمنطق أعمال).

---

## 5. السلامة المالية والمخزنية

**الحكم:** حساب الكميات سليم في معظمه؛ **أرقام الخزنة والورديات والأرباح وأرصدة العملاء/الموردين لا يُعتمد عليها** حتى إصلاح ما يلي.

### حرجة

1. **الوردية تحسب المبيعات النقدية مرتين** ✅ تحقق يدوي — `app/Services/ShiftService.php:81-111`: يجمع `invoices.paid_amount` ثم يجمع كل مدفوعات العملاء النقدية (وهي نفس صفوف `PAY-INV-*` التي تنشئها الفاتورة). افتتاحي 200 + بيعة نقدية 100 ⇒ المتوقع 400 بدل 300 ⇒ عجز وهمي في كل قفل. ويتجاهل `payment_method` (بيعة فيزا تُحسب كاش في الدرج).
2. **حذف المرتجع لا يعكس شيئًا** ✅ تحقق يدوي — `app/Actions/Returns/DeleteReturnAction.php:14-18`: soft delete فقط، بلا مخزون ولا رصيد ولا transaction؛ و`RestoreTrashRecordAction.php:28` يستعيد بلا إعادة تطبيق.
3. **المرتجعات بلا سقف وغير مربوطة بالفاتورة** — `StoreReturnRequest.php:18-29`: `invoice_id` غير موجود في الـ rules فيسقط دائمًا؛ لا مقارنة بالكمية المباعة؛ السعر من العميل. مرتجع 100 كجم بسعر 1000 لصنف لم يُشترَ ⇒ مخزون +100 ورصيد دائن 100,000. وإلغاء فاتورة بعد مرتجع جزئي يعيد الكمية كاملة (بيع 10، مرتجع 4، إلغاء ⇒ +14).
4. **الدفع المقسّم من الـ POS يضيع** — `PosView.vue:707-724` يرسل `payments[]` و`expenses` إلى `/invoices` لكن `StoreSalesInvoiceRequest.php:40-56` لا يعرّفهما. 60 كاش + 40 فيزا تُسجل 100 بطريقة واحدة، ورسوم التوصيل لا تُحفظ.
5. **cache الأرباح غير معزول** (انظر H5).

### عالية

- **مصاريف الشراء المدفوعة من الخزنة تفسد دفترين** — `PurchaseService.php:170-180`: صف `PAY-EXP-*` يحمل `supplier_id` ⇒ رصيد المورد ينقص خطأً، والخزنة تسجل 200 خروج مقابل 100.
- **تقرير الأرباح (API) يتجاهل المرتجعات** — `GetProfitLossReportAction.php:21-55`؛ بينما تقرير الطباعة يطرحها ⇒ التقريران مختلفان.
- **تقرير الطباعة يحسب التكلفة من WAC الحالي لا من لقطة البيع** — `ProfitLossService.php:74,93` ⇒ الربح التاريخي يتغير بعد كل شراء.
- **خطأ `'0.000' ?:` يفسد متوسط التكلفة** — `PurchaseService.php:120,353`، `StockService.php:201-203`: النص `'0.000'` قيمته truthy في PHP فلا يعمل الـ fallback. صنف بتكلفة 0 + رصيد افتتاحي 100×50 + شراء 100×60 ⇒ WAC = 30 بدل 55.
- **البيع من فرع بلا صف `StoreStock` يتجاوز فحص الفرع** — `StockService.php:57-74`.
- **المدفوع غير متوافق مع الصافي** — `InvoiceService.php:191-252`: `paid_amount` قد يتجاوز الصافي؛ `bank_transfer` يُعامل كآجل؛ و**`unit_price` موثوق من العميل بالكامل** — لا فحص `min_selling_price` في أي مكان (الكاشير يبيع بصفر).
- **إلغاء الفاتورة يُبقي مدفوعاتها** — `InvoiceService.php:309-316`؛ ولا يوجد مفهوم سند رد نقدية، بينما الوردية تطرح كل المرتجعات كنقدية حتى لو على فاتورة آجلة.
- **لا idempotency** في إنشاء الفواتير/المشتريات/المدفوعات؛ و`submitInvoice` في `PosView.vue:700-743` لا يفحص `isSubmitting` عند الدخول (F9 مرتين = فاتورتان).
- **فتح/قفل الوردية بلا lock** — `ShiftService.php:37-60,159-177`؛ ونداء Telegram داخل الـ transaction.
- **أرصدة الخزنة تخلط النطاقات** — `payments` بلا `store_id` ⇒ رصيد الفرع لا معنى له مع تعدد الفروع (`TreasuryService.php:34-91`).

### متوسطة

أرقام المستندات بلا lock (`count()+1` و`uniqid()`) · ترتيب locks غير ثابت (deadlock بين `[A,B]` و`[B,A]`) · floats في `POSInvoiceDTO.php:17-18,39-40` (`(string)(float)0.00001` = `"1.0E-5"` ⇒ bcmath يرمي 500) · خصم الشراء لا يُوزَّع على التكلفة · `payment_method` غير مُتحقَّق منه في `StorePurchaseRequest` · تحصيلات الحساب لا تسدد الفواتير (`CollectCustomerPaymentAction.php:25-31`) · تسوية المخزون قد تجعل رصيد الفرع سالبًا · المصروفات قابلة للتعديل بعد قفل الوردية.

### كامن (غير مستدعى حاليًا — يُصلح قبل التوصيل)

`InvoiceService::updateInvoice` (يحذف الحركات والمدفوعات نهائيًا)، `deleteInvoice`، `TreasuryService::transfer` (فحص الرصيد خارج الـ transaction).

### ما تم التحقق أنه صحيح

كل الأعمدة `DECIMAL(12,3)` والـ casts `decimal:3` · الحساب بـ bcmath بمقياس 3 · السيرفر يعيد حساب الإجماليات ولا يثق في إجماليات العميل · إنشاء/إلغاء/شراء/تحويل داخل `DB::transaction` واحدة · `Item` و`StoreStock` والعميل والمورد مقفولة قبل التعديل · الأرصدة تُحسب من المستندات (تصحح نفسها) · التكلفة تُلتقط في `invoice_items.cost_price` · unique indexes على أرقام المستندات و`(store_id,item_id)` · soft deletes على الجداول المالية.

---

## 6. ملاءمة المنتج: قطاعي / جملة / كيلو / فروع

> `docs/generalization-review-log.md` يذكر أن التعميم "COMPLETED 100%" — هذا غير دقيق؛ ما تم هو تنظيف معظم نصوص `lang/*` فقط.

### مصفوفة الملاءمة

| القدرة | الحالة | ملاحظة |
|---|---|---|
| دقة الكمية 3 خانات + bcmath | ✅ مدعوم | `FractionalWeightSaleTest` |
| **إدخال الوزن في الـ POS** | ❌ **غير موصّل** ✅ تحقق يدوي | `POSWeightPickerModal.vue` و`POSNumpad.vue` لا يستوردهما أي ملف؛ `POSCartTable.vue:97,213` بـ `step="1"`؛ والعرض يقص إلى خانتين (0.125 كجم تظهر 0.13) |
| تمييز صنف الوزن عن القطعة | ⚠️ هش | مطابقة نصية لاسم الوحدة بالعربي (`InvoiceService.php:65-70`، `POSItemCard.vue:22`) |
| جدول وحدات + تحويلات (قطعة/كرتونة، كجم/جم) | ❌ | `items.unit` نص حر، افتراضيه `'كجم'` |
| البيع بالمبلغ ("هات بـ 50 جنيه") | ❌ | — |
| باركود الميزان (EAN-13 بادئة 2x) | ❌ | مذكور في نص الخطة فقط |
| باركود لكل وحدة / متعدد | ❌ | `items.code` هو المعرّف الوحيد؛ مسح الباركود في الـ POS يبحث بـ `includes` في أول 300 صنف ⇒ قد يضيف صنفًا خاطئًا |
| سعر قطاعي / جملة | ⚠️ جزئي | سعر الجملة = alias لـ `min_selling_price` (تعارض)، ومطبق في الواجهة فقط |
| نص جملة / قوائم أسعار / مجموعات عملاء / حد أدنى للكمية | ❌ | — |
| سعر لكل فرع | ✅ | `store_stocks.custom_selling_price` |
| خصم سطر / فاتورة | ✅ | — |
| عروض وكوبونات | ❌ | — |
| **ضريبة / VAT / فاتورة إلكترونية مصرية** | ❌ | لا أعمدة ضريبة؛ و`useInvoiceShow.js:33` يطبع رقمًا ضريبيًا وهميًا `987-654-321` |
| تصنيفات متداخلة / ماركات / variants / صلاحية ودفعات / serial | ❌ | التصنيفات مسطحة |
| أصناف خدمية (غير مخزنية) | ❌ | كل بيع يخصم مخزونًا |
| أصناف مركبة / BOM | ⚠️ | موديول الـ blender فقط (بلا وصفات محفوظة ولا خطوة إنتاج) |
| حد ائتمان العميل | ❌ حقل وهمي | يظهر في `CustomerResource` والترجمة بلا عمود |
| ديون وسداد وكشف حساب | ✅ | — |
| موردون / مشتريات / مرتجع شراء / تكاليف إضافية | ✅ | — |
| عروض أسعار / أوامر بيع / توصيل / مندوبون وعمولات | ❌ | — |
| مخزون لكل فرع + تحويلات | ✅ | خطوة واحدة (بلا حالة "في الطريق") |
| خزنة لكل فرع | ⚠️ | `payments` بلا `store_id` |
| طلبات معلّقة في الـ POS | ⚠️ | في `localStorage` لكل جهاز، ومفتاحها لكل tenant فقط (سلة فرع A تظهر لكاشير فرع B) |
| دفع متعدد | ⚠️ | الواجهة موجودة لكن الـ backend يرميه (انظر القسم 5) |
| طباعة حرارية 80/58 + A4، درج النقدية (Electron) | ✅ | — |
| مرتجع من الـ POS / شاشة عميل / وضع offline | ❌ | `sw.js` يخزن assets فقط |
| استيراد Excel | ❌ | — |
| نوع النشاط / معالج إعداد / مصطلحات قابلة للتخصيص | ❌ | — |
| عملة / منطقة زمنية / تنسيق أرقام لكل tenant | ❌ | EGP و`Africa/Cairo` عامّان؛ ~127 موضع "ج.م" ثابت |
| شعار لكل tenant | ❌ | الشعارات ملفات ثابتة مشتركة؛ `logo_file` المرفوع يُتحقق منه ثم يُهمل |

### بقايا "البن"

- **منطق:** `CalculateBlendCostAction.php:61-66` — سعر الحبهان ثابت (1.5/2.5 ج.م للجرام) بحساب float ومكرر في `useCoffeeBlender.js:90-99`؛ `CreateBlenderInvoiceAction.php:36-44` يكتب نصوص تحميص/طحن عربية ثابتة في ملاحظات الفاتورة، وسعر الحبهان يُعرض ولا يُضاف للفاتورة؛ قيم التحميص والطحن المخزنة في قاعدة البيانات نصوص عربية.
- **ظاهر للعميل:** أيقونة `Coffee` هي شعار التطبيق في ~10 أماكن **بما فيها فاتورة A4 المطبوعة**؛ أيقونة التصنيف الافتراضية `☕`؛ `public/manifest.json` و`app.blade.php:97` باسم المنتج القديم؛ `modules.json` "Coffee Blends"؛ وصف الخطط يذكر "المطاحن"؛ عنوان مجموعة الصلاحيات "خامات البن"؛ إشعارات تجريبية وهمية بأسماء أصناف بن في `SpaLayout.vue:622-626` تظهر في الإنتاج؛ رسالة اختبار Telegram.
- **الأغلفة:** `appId = com.sroor.cofe.erp` و`server.url` مثبت على دومين tenant واحد (`capacitor.config.json`)؛ `desktop/package.json`؛ بروتوكول `sroor://`؛ الدومين المركزي مكتوب ثابتًا في ~15 ملف.

**التوصية:** تحويل الـ blender إلى موديول اختياري عام "تجميع / BOM" خلف `blender.access` (جداول `recipes` و`recipe_components` + `loss_percent` + `attributes` JSON)، وتصبح خصائص البن preset لنوع نشاط "محمصة".

**مخاطر إعادة التسمية:** تغيير `applicationId` في Android أو `appId` في Electron = تطبيق مختلف (لا ترقية في المكان) — يُؤجَّل لإصدار مخطط. `tenant_sroor` قاعدة عميل حقيقي — لا تُعاد تسميتها. أسماء العرض والأيقونات والنصوص آمنة للتغيير الآن.

---

## 7. الـ Frontend

**الحجم:** 342 ملف، ~43.5 ألف سطر؛ 40 view و240 component و47 composable و3 stores.

### أهم المشاكل

- **H1 — حلقة redirect عند انتهاء الـ token:** `Services/api.js:58-72` يمسح `localStorage` ولا يلمس Pinia، فيعيد الـ router المستخدم للـ dashboard ⇒ 401 ⇒ تكرار.
- **H2 — لا معالجة لحالات 402/429/5xx/offline:** كل composable ينفذ `catch { console.error }` والفشل يظهر كـ "لا توجد بيانات".
- **H3 — تبديل الفرع أو الخروج يُبقي البيانات القديمة:** `SpaLayout.vue:659-664` يحدّث `localStorage` فقط؛ لا `$reset` في أي مكان.
- **H9 — حساب الـ POS بـ `parseFloat`** ويرسل قيمًا غير مقربة (`0.30000000000000004`)؛ و`store_id: activeStore?.id || 1` (`PosView.vue:708`) رجوع صامت للفرع 1.
- **H10 — `backend/public/hot` موجود في git:** إن وصل للسيرفر سيُخرج `@vite` وسوم dev server وتصبح الصفحة بيضاء. أنماط `.gitignore` في الـ root لا تطابق `backend/public/...`، ولذلك 95 ملف build متتبَّع (18,916 blob في التاريخ ≈ 319MB).
- **M1:** 488KB JS في أول تحميل معظمها ترجمات (اللغتان معًا، ومكررة 3 مرات: bundle + Blade + `/system/context`).
- **M2:** تبويبات سطح المكتب لا تحفظ الحالة (لا `<KeepAlive>`)، و`refreshTab` يذهب لـ route `/redirect` غير موجود.
- **M4:** 45 import يستخدم `../services/api` والمجلد اسمه `Services/` — يعمل على Windows ويفشل على Linux CI.
- **M5 — ~3,900 سطر ميت:** `PosView.legacy.grid.vue` (1,452 سطر)، 8 ملفات `Report*`، 5 `Settings/*Tab`، 9 مكونات POS غير مستخدمة، `posService.js`، و`sw.js` ما زال يشير إلى `/livewire/`.
- **M6/M7:** 6 مكونات مكررة (مكتبتا date picker تُشحنان معًا)، و20 جدولًا مكتوبًا يدويًا بدل `DataTable`.
- **M10:** ~30 موضع يستخدم `toISOString().split('T')[0]` — تاريخ UTC، فبين 12 و3 صباحًا بتوقيت القاهرة يظهر تاريخ الأمس.
- **M11:** لا `role="dialog"` ولا focus trap، 4 `aria-label` على 381 زر، 334 استخدام لخط ≤10px، و`user-scalable=no`.
- **L1:** رابط السوبر أدمن يظهر بشرط `email.includes('admin')` (`SpaLayout.vue:633`). **L2:** الدخول البيومتري يخزن كلمة المرور نصًا في الـ Keystore.

### أكبر الملفات

`PosView.vue` 839 · `SpaLayout.vue` 735 · `DesktopSidebar.vue` 500 · `LoginView.vue` 474 · `router/index.js` 472 · `DataTable.vue` 420 · `ItemsView.vue` 312 · `InvoicesTable.vue` 303.

### الجيد

كل الـ routes lazy-loaded وترتيب الـ guards صحيح · axios instance واحد يحقن token و`X-Store-Id` و`X-Tenant` و`X-Locale` · لا Options API ولا بقايا Inertia/Livewire في `resources/js` · 24 من 40 view ≤100 سطر · 757 استخدام لمتغيرات الثيم و15 سطر فقط `bg-white` بلا `dark:` · skeletons في 61 ملف و`EmptyState` في 27 · utilities منطقية للـ RTL هي الغالبة · أيقونات lucide tree-shaken · بحث الـ POS بـ debounce و`AbortController`.

---

## 8. الترجمة (i18n)

| المقياس | القيمة |
|---|---|
| ملفات lang | 25 ar / 25 en — لا ملف ناقص |
| المفاتيح | ar 2,779 · en 2,730 |
| **مفاتيح مستخدمة وغير معرّفة** | **80** (المستخدم يرى اسم المفتاح الخام) |
| مفاتيح مكررة داخل نفس الملف | 54 (22 بقيم متعارضة — PHP يأخذ الأخير بصمت) |
| مفاتيح يتيمة (تقدير) | ~700 (25%) — `nav.php` 52% يتيم بينما `useNavigation.js` يكتب 34 عنوانًا ثابتًا |
| نصوص عربية ثابتة في PHP | ~1,030 (`ReportPrintController` 213، `TelegramService` 111 — كلاهما صفر `__()`) |
| أسطر عربية ثابتة في Vue/JS | ~410 |
| `__('key') ?: 'نص'` (الـ fallback لا يعمل أبدًا) | 79 |

**الأخطر:** الإنجليزية موجودة في الملفات لكن **لا يمكن لأي مستخدم الوصول إليها** — الـ backend لا ينادي `App::setLocale` أبدًا، لا يوجد مبدّل لغة، `LoginView.vue:419` يفرض `'ar'`، `dir="rtl"` ثابت 30 مرة في 27 ملف، و`$t` غير reactive (يقرأ `window.spaTranslations`). والعملة ترجمة (`common.currency`) لا إعداد tenant — مستخدمة 266 مرة ومضمّنة داخل ~18 قيمة ترجمة. لا pluralization إطلاقًا.

**ملاحظة:** `auth.phone_placeholder` في اللغتين يبدو رقم تليفون حقيقي — يُستبدل بنمط وهمي.

---

## 9. الاختبارات والجودة

**نتيجة التشغيل:** 306 اختبار — **305 نجح، 1 فشل**، 1,389 assertion، ~309 ثانية. الفاشل `AppUpdateApiTest::test_download_apk_throws_404…` (test rot: ملفات APK غير متتبعة في `public/`؛ لكنه يكشف خطأ حقيقيًا — `platform=ios` يستلم APK أندرويد).

**النتيجة الخضراء مضللة:**

- **لا اختبار عزل واحد** (tenant أو فرع) في كل الـ suite ⇒ لا controller يحصل على تقييم A.
- **لا اختبار rollback يعمل:** في `InvoiceServiceTest.php:124-130` و`ConcurrencyTest.php:100-121` الـ assertions موضوعة بعد النداء الذي يرمي exception، فـ `expectException` ينهي الاختبار قبلها — كود ميت.
- **لا اختبار تزامن:** `ConcurrencyTest` نداءان متتاليان في عملية واحدة، وSQLite يتجاهل `lockForUpdate()` أصلًا — ومع ذلك `.claude/rules/testing.md` يذكره كنموذج يُحتذى.
- **صفر اختبارات:** FIFO، تعديل الفاتورة، خصم النسبة، حد الائتمان، تحويلات الخزنة، النقدية المتوقعة للوردية بعد مبيعات فعلية، فتح وردية مزدوج، حدود الخطط، انتهاء الاشتراك.
- **Controllers بلا اختبارات:** `SuperAdminAppVersionController`، `ExportController`، `POSController` (legacy).
- **بطء:** ~1 ثانية/اختبار لأن `TestCase::setUp` يعيد 35 migration في كل اختبار، و29 ملفًا تعيدها مرة ثانية، و28 ملفًا تعيد زرع 62 صلاحية.
- **`UserFactory` هو الـ factory الوحيد** لـ 32 موديل.
- **ليست in-memory بالكامل:** اختبار الـ provisioning يكتب `database/tenant_<slug>.sqlite`؛ و`TenantDeleted → DeleteDatabase` غير مزيّف — اختبار حذف مستقبلي بـ id حقيقي سيحذف قاعدة dev.

**E2E:** 40 spec و77 اختبار، لا شيء يشير للإنتاج. لكن: 179 `waitForTimeout`، لا `data-testid`، و`e2e/auth/login.setup.js:21-68` يبتلع كل فشل ويكتب storage state بديلًا (أخضر زائف)، ولا spec يكمل عملية بيع فعلية.

**CI (`.github/workflows/deploy.yml`):** يعمل من الـ root بينما التطبيق في `backend/` ⇒ **لا يمكن أن ينجح**؛ `composer update` بدل `install`؛ لا Pint ولا PHPStan ولا ESLint ولا build؛ لا trigger على الـ PR؛ `secrets.*` مستخدم صفر مرة. ومسار الـ deploy المعتمد لا يشغّل الاختبارات أصلًا.

**خطة الاختبارات المقترحة (~235 اختبار):** P0 شبكة أمان (5) + أمان المصادقة (12) + harness عزل بـ tenant-ين (15) + مصفوفة عزل الفروع بـ DataProvider (40) → P1 rollback (20) + تزامن حقيقي على MySQL (8) + عمق الفواتير والـ POS (25) + الورديات والخزنة (15) → P2/P3 الباقي.

---

## 10. DevOps ونظافة الريبو

| البند | القيمة |
|---|---|
| ملفات tracked / commits / tags | 2,193 / 745 / **لا يوجد** |
| حجم الـ pack | 323 MiB (معظمه `public/build`) |
| commits بعنوان `chore(release): bump version` | 122 من 745 |
| سكربتات root بها credential حي | 49 tracked + 43 untracked |
| `main...feature/multi-tenant` | main متقدم 25 · الفرع متقدم 569 |
| الفرع الذي يتتبعه الإنتاج فعليًا | `feature/api-migration` |

- **~95 من 106 ملف في الـ root سكربتات one-off** تتصل بالإنتاج؛ مجموعة `fix_*` / `run_cost_sync` / `run_reconcile_fifo` / `update_invoices_total_cost` / `fix_duplicate_payments` تعدّل بيانات مالية حية بلا audit trail.
- **artifacts متتبعة:** `backend/public/build` (95 ملف)، `storage/framework/views/*` في الـ root (60 ملف Blade مترجم من عصر Livewire)، `backend/e2e/screenshots` (161 PNG، 34.5MB)، تقارير Playwright، `.env.e2e` به `APP_KEY` حقيقي.
- **`.gitignore` ينقصه:** `backups/`، `*.sql`، `*.sql.gz`، `__pycache__/`، `.env.*`، مخرجات Android، `desktop/dist/`؛ ولا يوجد `backend/.gitignore`.
- **`backend/.env.example` هو ملف Laravel الافتراضي** — ينقصه `CENTRAL_DOMAIN`، `TENANT_DB_*`، `TELEGRAM_*`، `TELESCOPE_ENABLED`، `PULSE_ENABLED`.
- **3 أنظمة إصدارات غير متزامنة:** `version.json` 1.0.135 · Android `versionCode 3` / 1.0.2 · `app_versions` 1.1.0 · Desktop 1.0.0. لا changelog. لا `signingConfig` لـ Android ولا code signing لـ Electron (`electron ^33` خارج الدعم).
- **4 أشجار E2E** (`e2e/`، `backend/e2e/`، `backend/tests/e2e/`، `tests_e2e/` Python) وملفا Playwright config.
- **الوثائق:** `README.md` هو boilerplate لارافيل؛ `docs/README.md` و`docs/01-overview/project-overview.md` و`docs/03-architecture/*` ما زالت تصف Livewire 4 + Alpine "بلا طبقة API" كتصميم حالي؛ `backend/docs/` (290 ملف) نسخة مكررة من `docs/` (256 متطابق)؛ `CLAUDE.md` و`.claude/` غير متتبعين في git؛ وثائق التشغيل مفقودة (onboarding tenant، runbook، backup/restore، release، مصفوفة الصلاحيات، API reference).

---

## 11. ملاحظات على عملية المراجعة نفسها (شفافية)

- **رسائل Telegram محتملة:** أحد الوكلاء شغّل `php artisan test` بدون تعطيل Telegram. لأن `backend/.env` المحلي به bot token حقيقي و`phpunit.xml` لا يتجاوزه، فاختبارا قفل الوردية (`ShiftApiTest` و`ShiftsAndDailyJournalApiTest`) يرسلان **تنبيه عجز وردية وهمي (وردية `SHF-260821-002`) إلى الشات الحقيقي**. إن وصلتك هذه التنبيهات اليوم فمصدرها الاختبارات لا نظام حي. التشغيل الثاني (وكيل QA) تم بقيم Telegram وهمية. هذا نفسه خلل في بيئة الاختبار يجب إصلاحه (القسم 2 بند 6).
- **آثار محلية لتشغيل الاختبارات:** تحديث `backend/database/tenant_wadi-elbon.sqlite` (artifact قديم) ووقت تعديل `database.sqlite` المحلية (452MB بسبب 343 ألف صف Telescope غير مُنظَّف)؛ الفحص بعدها `quick_check=ok` وأعداد الصفوف لم تتغير.
- **لم يُفحص:** قيم `.env` الإنتاجية (نتائج الـ cache/queue تفترض الافتراضيات)، عمق الأسرار في تاريخ git، أي فحص بصري في المتصفح، تشغيل E2E، `composer audit`، الكود الـ native لأندرويد، و`ReportPrintController` سطرًا بسطر.
- **ما تحققت منه يدويًا:** C1، C2، تلف `DeleteTenantAction`، الحساب المزدوج في الوردية، حذف المرتجع، وعدم توصيل واجهة الوزن في الـ POS. بقية النتائج من تقارير الوكلاء مع مواقع `file:line`.

---

## 12. خارطة الطريق

### المرحلة 0 — إيقاف النزيف (أيام)
1. `.gitignore` (`backups/`، `*.sql*`، `.env.*`، مسارات `backend/public/{hot,build}`) ونقل `backups/` والـ APK خارج الريبو.
2. تدوير الأسرار الأربعة + SSH بمفاتيح فقط + حذف `update_webhook.php`.
3. حذف `quick-login` و`workspace-users`؛ وضع routes الطباعة/التصدير/تبديل الفرع خلف auth أو حذفها.
4. حذف كل فحوص التليفون/الإيميل الثابتة؛ إزالة `super_admin` من `PermissionsSeeder` للـ tenants؛ whitelist للأدوار القابلة للإسناد؛ نقل `/super-admin/*` لمجموعة مركزية بـ guard على `CentralUser` ترفض الطلب إذا كان tenancy مفعّلًا.
5. إضافة tenant id لكل مفاتيح الـ cache (ومفتاح spatie لكل tenant).
6. استعادة الملفين التالفين + `php -l` / Pint في الـ CI.
7. عزل Telegram في الاختبارات.

### المرحلة 1 — صحة الـ tenancy والأرقام (1–3 أسابيع)
8. middleware `EnsureTenantIsActive` (إيقاف / انتهاء / سماح ⇒ 402/403) + أمر يومي لدورة حياة الاشتراك.
9. middleware `ResolveActiveStore` يتحقق من العضوية ويربط الفرع بالطلب؛ كل lookup بالـ id يُقيَّد بـ `store_id`.
10. `auth:sanctum` مع انتهاء صلاحية؛ حذف عمود `api_token` ومسار `?api_token=`.
11. إصلاحات مالية بالترتيب: حساب الوردية ← عكس/ربط/سقف المرتجعات ← عقد موحد لطلب الـ POS (`payments[]`، `additional_expenses`) ← عكس المدفوعات عند الإلغاء ← `store_id` على `payments` ← خطأ `'0.000' ?:` والـ WAC ← فحص `min_selling_price` بصلاحية override ← idempotency key ← تسلسل أرقام المستندات تحت lock ← ترتيب locks ثابت.
12. توحيد مصدر تقرير الأرباح (SQL aggregates على `invoice_items.cost_price` مع طرح المرتجعات).
13. تثبيت `Plan/PlanFeature/Subscription` على الاتصال المركزي + جدول `platform_settings` مركزي + استبدال `env()` بـ `config('tenancy.central_domain')`.
14. جدولة tenant-aware + queue حقيقي + provisioning في job بحالة (pending → ready/failed) وتنظيف عند الفشل.
15. Harness اختبار بـ tenant-ين + مصفوفة عزل الفروع + اختبارات rollback حقيقية.

### المرحلة 2 — جعله منتج تجزئة عامًا (أسابيع)
16. **الوحدات والتحويلات** (`units`، `item_units` بمعامل تحويل وباركود وسعر لكل وحدة؛ المخزون بالوحدة الأساسية) + توصيل واجهة الوزن وformatter بـ 3 خانات.
17. **قوائم أسعار حقيقية** (`price_lists`، `item_prices`، `customers.price_list_id`) مع `ResolveItemPriceAction` على السيرفر.
18. **الباركود** (`item_barcodes` + `ParseScaleBarcodeAction` + `GET /pos/lookup?code=` بمطابقة تامة) + **البيع بالمبلغ**.
19. **الضريبة / VAT** وأساس الفاتورة الإلكترونية المصرية.
20. **فرض حدود الخطة والـ features** (`PlanLimitGuard` في `CreateUser/Store/ItemAction`، middleware `feature:<key>`، وإرجاع الـ features الفعلية في `/system/context` ليقرأها `useModules`).
21. **الفوترة:** `subscription_payments`، `billing_invoices`، Action تجديد/ترقية في transaction مركزية، بوابة دفع (Paymob/Fawry) + تسجيل يدوي، MRR صحيح.
22. `tenants.business_type` + معالج إعداد يزرع الوحدات والتصنيفات وقوائم الأسعار والموديولات؛ الـ blender يصبح موديول BOM اختياريًا.
23. حد الائتمان، أنواع الأصناف (مخزني/خدمي/مركب)، تصنيفات متداخلة، استيراد Excel، تحويلات بخطوتين، مرتجع من الـ POS.

### المرحلة 3 — التلميع والتشغيل
24. White-label: شعار وعملة ومنطقة زمنية وتنسيق أرقام لكل tenant؛ manifest/splash ديناميكي؛ الدومين من config؛ إزالة أيقونة `Coffee`.
25. i18n: الـ 80 مفتاح الناقص ← middleware `SetLocale` + مبدّل لغة + `dir` ديناميكي ← نقل النصوص الثابتة (الـ navigation أولًا ليعيد استخدام مفاتيح `nav.*` اليتيمة).
26. Frontend: حذف ~3,900 سطر ميت ← تقسيم `PosView` إلى 5 composables ← `SpaLayout` و`LoginView` ← نقل الجداول الـ 20 إلى `DataTable` ← `resetSession()` ومعالجة أخطاء موحدة ← accessibility.
27. CI/CD حقيقي: PR workflow (Pint + PHPUnit + build) ← staging ← نشر بموافقة مع backup قبل `migrate` و`tenants:migrate` يفشل بصوت عالٍ + rollback.
28. نسخ احتياطي ليلي لكل tenant (`mysqldump --single-transaction` مشفّر إلى object storage مع retention) + تمرين استرجاع شهري.
29. تنظيف الريبو: حذف سكربتات الـ root (وتحويل المتكرر منها إلى artisan commands بـ `--dry-run`)، إيقاف تتبع `public/build`، ثم `git filter-repo` مرة واحدة عند دمج الفرع في `main`، وtags بـ SemVer موحد.
30. تحديث الوثائق: `README.md`، وثائق المعمارية، runbook، backup/restore، مصفوفة الصلاحيات؛ وتتبّع `CLAUDE.md` و`.claude/` في git.
