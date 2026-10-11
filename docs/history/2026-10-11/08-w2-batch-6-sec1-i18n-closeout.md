# سجل تعديل: Phase 1 — W2 الدفعة 6 (بوابة SEC-1 وإصلاحاتها، تنضيف i18n، صفحة إعدادات المنصة، قفل W2)
* **التاريخ والوقت:** 2026-10-11 (الوقت الدقيق لكل lane غير موثّق)
* **الدور المفعل:** `security-auditor` (المراجعة)، lane SEC-1 (6A) للإصلاحات، lane i18n، lane صفحة إعدادات المنصة، و`docs-historian` (السجلات). توزيع الـ agents داخل كل lane غير موثّق.
* **الهدف:** تنفيذ بوابة SEC-1 آخر W2 وقفل نتائجها اللي مكانها W2، توحيد النصوص في ملفات الترجمة، صفحة إعدادات المنصة للـ super-admin، وشيل اسم «سرور كوفي» من الـ splash والـ manifest.

> **الحالة وقت الكتابة:** كل شغل الدفعة **uncommitted** على `feature/multi-tenant` (فوق `0f55e048`). الـ commit ونتيجة CI بيتسجلوا في [summary.md](summary.md) §2.

## 1. الملفات المعدلة

### 1.1 SEC-1 — المراجعة
* `[NEW]` `docs/reviews/2026-10-11-sec-1-security-design-review.md` — المراجعة على `0f55e048`: 1 Critical، 8 Medium، 15 Low، وشروط تصميم لمهام W3.

### 1.2 SEC-1 — الإصلاحات (lane 6A)
| البند | الملفات | اللي اتغيّر |
|---|---|---|
| **M1** API المحل بيرد من غير محل | `[MODIFIED]` `app/Http/Middleware/ResolveApiTenancy.php`، `app/Actions/Auth/ApiLoginAction.php`، `docs/07-operations/vps-runbook.md` | ثابت `CENTRAL_SAFE_ROUTES` (الـ admin-host allowlist + endpoints التحديث العامة + `api.auth.options`)؛ أي route تاني من غير tenant مُهيّأ = 404. `ApiLoginAction` بيرفض الدخول من غير tenant بنفس رسالة `auth.failed` (دفاع إضافي). runbook §11.1: خطوة لمرة واحدة لمسح `personal_access_tokens` في الـ DB **المركزي** بعد النشر — بيد الـ CTO، **لم تُنفَّذ** |
| **M3** حركة الصنف وتفاصيله بتكشف الفروع | `[MODIFIED]` `routes/api.php`، `ActiveStore.php`، `ItemController.php`، `GetItemMovementsAction.php` | `store.active` على `GET /items/{id}` و`/items/{id}/movements`؛ `ActiveStore::readableStoreIds()` لفلترة `storeStocks` بالفروع المسموحة |
| **M4** سند يسدد مستند فرع/عميل تاني | `[MODIFIED]` `app/Services/PaymentService.php` | جوه الـ transaction وقبل أي تعديل: الفاتورة/المشتريات لازم تكون مقروءة للمستخدم (`ActiveStore::canRead`، وإلا 404) وتخص نفس العميل/المورد (وإلا 422) |
| **M5** الكاشير يصرف لمورد | `[MODIFIED]` `StoreSupplierPaymentVoucherRequest.php`، `StoreCustomerPaymentReceiptRequest.php` | سند الصرف = `suppliers.manage` بس؛ سند القبض = `customers.manage` أو `pos.access`. `daily_journal.view` (صلاحية قراءة) مابقتش تكفي |
| **M6** `items.view_cost` مش متطبقة | `[NEW]` `app/Support/CostVisibility.php`؛ `[MODIFIED]` `ItemResource.php`، `POSItemResource.php`، `GetItemMovementsAction.php`، `GetPOSBootstrapDataAction.php`، `GetDashboardOverviewAction.php`، `StoreStockResource.php`، `StoreController.php` | التكلفة وما يتشتق منها `null`/مخفية من غير `items.view_cost`؛ أرباح وهامش ومديونيات الداشبورد بـ `reports.view` بس + `can_view_financials`؛ `/stores/stocks` بقى يتطلب `items.view` |
| **M7** routes الـ web القديمة | `[MODIFIED]` `routes/tenant.php` (−258 سطر تقريبًا)، `routes/web.php`، `POSController.php` | `tenant.php` بقى صفحات الـ SPA shell وصفحات الطباعة Blade بس. اتشال: `POST /login` و`/tenant/logout` بالـ session، كل نسخ POST/PUT/DELETE المكررة من الـ API، تبديل الفرع بالـ session، `/theme-toggle`، وroutes لـ methods مش موجودة (`invoices/{id}/edit`، `purchases/create`، `returns/create`) |
| **L1** عرض أي فرع | `StoreController::show` | `StorePolicy::view`، وإلا 404 |
| **L2** `id === 1` كأدمن | `StoreController::index` | `ActiveStore::canViewAll`؛ قائمة المستخدمين بس لمن يقدر ينشئ فرع |
| **L3** فاتورة الخلّاط | `routes/api.php`، `CoffeeBlenderController.php`، `CreateBlenderInvoiceRequest.php` | `store.active:concrete`؛ الفرع من `ActiveStore` بس، و`store_id` في الـ body بيتحقق منه (403) وبيتجاهل |
| **L4** رسايل الأخطاء الخام | `[NEW]` `app/Support/SafeJsonError.php`؛ controllers متعددة (`Expense`، `Payment`، `Item`، `Trash`، `User`، `Profile`، `Shift`، `POSController`…) | 422/403/HTTP بتعدّي زي ما هي؛ 404 مترجمة؛ أي حاجة تانية `report()` + رسالة مترجمة عامة |
| **L5** رسالة spatie في 403 | `bootstrap/app.php` | `__('auth.unauthorized')` بس، من غير اسم الدور/الصلاحية |
| **L6** Telescope | `TelescopeServiceProvider.php` | إخفاء `code`، `recovery_code`، حقول كلمة السر، `telegram_bot_token`، `token`، وheader `x-api-token` |
| **L7** معادلات في CSV | `ExportActivityLogsCsvAction.php` | أي خلية بتبدأ بـ `= + - @` أو tab/CR بتتسبق بـ `'` |
| **L8** route الـ impersonation القديم | `routes/tenant.php` | شيل `/impersonate/{token}` و`/impersonate/leave` (stancl)؛ `ImpersonationLeaveRedirectTest` اتعدّل |
| **L11** إنشاء محل | `routes/central.php` | `RequireRecentTwoFactor` على `POST /tenants` |
| **L12** الأدوار تدّي نفسها أدمن | `StoreUserRequest.php`، `UpdateUserAction.php`، `DeleteUserAction.php`، `ToggleUserActiveAction.php` | غير الأدمن مايقدرش ينشئ/يرقّي أدمن، ولا يعدّل دور/كلمة سر/تفعيل أدمن، ولا يغيّر دوره هو؛ الفحص على الصف بعد `lockForUpdate()` (403) |
| **L13** العميل النقدي قبل الـ authorization | `[NEW]` `app/Http/Requests/Concerns/ResolvesWalkInCustomer.php`، `app/Actions/Customers/ResolveWalkInCustomerAction.php`؛ `[MODIFIED]` `StorePOSInvoiceRequest.php` | إنشاء/جلب العميل النقدي اتنقل من `prepareForValidation()` لـ `passedValidation()`، فطلب مرفوض مايكتبش صف عميل |

* `[NEW]` tests: `tests/Feature/Api/CentralHostTenantApiRefusalTest.php`، `ItemCostAndStoreScopeApiTest.php`، `PaymentVoucherSecurityApiTest.php`، `tests/Feature/Platform/ResponseHygieneTest.php`.
* `[MODIFIED]` tests: `ActivityLogApiTest`، `CoffeeBlenderApiTest`، `PosApiTest`، `StoresApiTest`، `SuperAdminTenantsApiTest`، `UsersApiTest`، `CentralRouteSecurityGateTest`، `ExampleTest`، `InvoicePrintRoutesTest`، `MultiStorePhase2Test`، `ImpersonationLeaveRedirectTest`، `ProvisionTenantJobTest`، `TenantProvisioningGuardPinningTest`.

### 1.3 i18n
* `[MODIFIED]` `backend/lang/{ar,en}/{auth,common,contacts,dashboard,expenses,inventory,invoices,nav,pos,purchases,reports,returns,super,treasury}.php` — مفاتيح جديدة بنفس الشكل في اللغتين.
* `[MODIFIED]` حوالي 150 نص ثابت اتحوّل لمفاتيح، منها: `Layouts/SpaLayout.vue`، `Components/Navigation/{DesktopSidebar,DesktopTabsBar,MobileBottomNav}.vue` (`nav.layout.*`)، `router/index.js` (`title` ← `titleKey` تحت `nav.page_titles.*`)، `helpers/alert.js` (`common.confirm_dialog.*`)، `Composables/useInvoiceShow.js` (نص المشاركة)، `views/Invoices/InvoicesView.vue`، `views/POS/PosView.vue`، `stores/tabs.js`، و`?: 'نص عربي'` fallbacks في controllers زي `StoreController` و`CoffeeBlenderController`، وعناوين تقارير الطباعة في `routes/tenant.php`، واسم ملف الصورة في `print-a4.blade.php`.
* إصلاح bug المفاتيح الناقصة: بلّغ عنه الـ lane؛ تفاصيله (أنهي مفاتيح وأنهي شاشات) **غير موثّقة** في الأدلة المتاحة.
* `[MODIFIED]` `backend/resources/js/helpers/defaultTranslations.{js,json}` — متولّدة بـ `php artisan lang:export`، مش متعدلة باليد.

### 1.4 صفحة إعدادات المنصة (BRND-2 واجهة) وشيل branding «سرور كوفي»
* `[NEW]` `views/SuperAdmin/SuperAdminSettingsView.vue` (route `/super-admin/settings`)، `Composables/usePlatformSettings.js`، `helpers/centralPermissions.js` (`super_admin.settings.view` / `.manage` لإظهار الواجهة بس؛ الـ API هو اللي بيطبّق)، و`Components/SuperAdmin/PlatformSettings/{PlatformIdentitySection,PlatformAppearanceSection,PlatformAssetsSection,PlatformAssetUploader,PlatformSupportSection,PlatformReadOnlyNotice,PlatformSettingsSaveBar,PlatformSettingsSkeleton,PlatformSettingsLinkCard}.vue`.
* `[DELETED]` `Components/SuperAdmin/SuperAdminPlatformSettingsCard.vue` — اتشال من الداشبورد لصالح الصفحة المستقلة؛ `[MODIFIED]` `SuperAdminDashboardView.vue`، `useSuperAdminDashboard.js`، `SuperAdminLayout.vue`.
* `[MODIFIED]` `resources/views/app.blade.php` — العنوان واسم الـ splash من `PlatformBranding` (الـ shell بيترندر قبل الـ tenancy)، أيقونة store محايدة بدل فنجان القهوة، سطر فرعي من `common.splash.subtitle`، والـ manifest بقى `url('/app.webmanifest')`.
* `[MODIFIED]` `routes/web.php` — نفس الـ closure الديناميكي على `/manifest.json` و`/app.webmanifest` (`web_manifest`)، لأن `public/manifest.json` ملف static السيرفر بيقدّمه قبل Laravel.
* `[MODIFIED]` `backend/public/manifest.json` — اسم ووصف محايدين (`Retail ERP`)؛ متساب كنسخة static لـ Capacitor.

### 1.5 الـ stack المحلي ونظافة الواجهة
* تشغيل الـ stack المحلي بـ `scripts/local/setup-local.ps1` (مش ملف جديد؛ من الدفعة 3). ملف `backend/public/hot` قديم اتلقى ومتشال (كان بيخلي الصفحات تطلب Vite dev server مش شغال). مش ملف متتبع (اتشال من التتبع في الدفعة 3).
* `[MODIFIED]` `Composables/useTheme.js` — شيل `axios.post('/theme-toggle')`، الـ route اتشال في M7 والنداء كان ميت.

## 2. القرارات التقنية
* **M1 بـ allowlist مش denylist:** أي route جديد في المجموعة دي 404 افتراضيًا من غير tenant. البديل المرفوض: فحص في كل controller.
* **M4 جوه الـ transaction:** الفحص على المستند اللي اتقرا للتعديل نفسه، مش قبل الـ transaction، عشان مايبقاش فيه فجوة بين الفحص والكتابة. فرع تاني = 404 (قرار CTO Q13)، عميل/مورد تاني = 422.
* **M6 أثر مالي على العرض بس:** مفيش تغيير في الحسابات أو القيود؛ الحقول بتترجع `null` لمن ماعندوش الصلاحية. الأدمن بيعدّي عن طريق `Gate::before`.
* **M7 شيل مش تقوية:** الـ routes دي كانت نسخة session من الـ API من غير صيانة. صفحات الطباعة Blade فضلت (متابعة: token/signed URL).
* **L13:** `passedValidation()` بدل `prepareForValidation()` عشان الكتابة تحصل بعد `authorize()` والـ rules.
* **الـ manifest على مسار جديد** بدل حذف `public/manifest.json`، لأن Capacitor بينسخه كـ web asset.
* بنود SEC-1 اللي مكانها W3 (M2 = IDEN-3.5، M8 = IDEN-2.4 + CSP) و C1 (تدوير الأسرار، قرار الـ CTO) **ما اتلمستش** في الدفعة دي.

## 3. التحقق والاختبار
> الأرقام دي اللي بلّغت بيها الـ lanes؛ ماشفتش مخرجات الأوامر نفسها.

* **lane SEC-1 — runs مستهدفة، كلها خضراء:**
  * `php artisan test --filter='Store|Item|Payment|Supplier|Customer|Dashboard'`: **515/515**
  * `--filter=Tenant`: **981/981**
  * `--filter=Login`: **124/124**
  * `--filter=Pos`: **178/178**
  * `--filter=User`: **180/180**
* **lane i18n:** PHPUnit **417/417**، JS unit **193/193**.
* **lane صفحة إعدادات المنصة:** PHPUnit **156/156**، JS unit **193/193**.
* **الـ suite الكامل (`php artisan test`):** سيُحدَّث بعد تشغيل الـ suite الكامل.
* CI على commit الدفعة 6: قيد التشغيل ([summary.md](summary.md) §2).
* **لم يُختبر:** Playwright E2E لصفحة إعدادات المنصة أو الـ splash أو الـ manifest؛ أي طلب على سيرفر حقيقي؛ خطوة مسح `personal_access_tokens` المركزية (موثّقة، ما اتنفذتش). Pint / Larastan / ESLint / Prettier على ملفات الدفعة: **غير موثّق** لحد ما CI يخلص.

## 4. ملاحظات / ديون تقنية
* **مفتوح من SEC-1:** C1 (تدوير الأسرار، بيد الـ CTO، مانع لأي cut-over)؛ M2 = IDEN-3.5 وM8 = IDEN-2.4 + CSP للمحلات (أول W3)؛ L9، L10، L14، L15 متابعة.
* **متابعات اتأجلت:**
  * قرار: الـ storekeeper يشوف التكلفة المقترحة (cost prefill) في الاستلام ولا لأ بعد M6.
  * `POST /coffee-blender/calculate` لسه بيرجّع التكلفة من غير `items.view_cost`.
  * صفحات الطباعة Blade محتاجة مصادقة بـ token أو signed URL.
  * `TreasuryService::getBalances` مش مقيّد بالفرع.
  * نصوص ثابتة قديمة لسه موجودة في الملفات اللي ما اتلمستش (منها `ShiftService::closeShift()` — [06](06-bug-01-shift-cash-double-count.md) §4).
* **Antigravity:** PR #9 لسه draft؛ issues #10، #11، #12 مفتوحة. issue #11 (شاشة إعدادات المنصة) بتتداخل مع صفحة الدفعة دي — نطاقها محتاج يتراجع.
* ملفات `backend/public/build/assets/*` ظاهرة كـ deleted في الشجرة؛ السبب وهل المفروض تدخل الـ commit **غير موثّق** — يتراجع قبل الـ staging.
* بعد النشر: خطوة `vps-runbook.md` §11.1 (مسح `personal_access_tokens` المركزية) مع backup قبلها.
* صفحات `docs/pages/` (super-admin settings، الداشبورد) لم تُحدَّث في الدفعة دي؛ مغطاة جزئيًا في issue #12.
