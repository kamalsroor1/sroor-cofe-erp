# سجل تعديل: Phase 0.5 — إصلاحات ما بعد Phase 0 (split payment، auth، leftovers، quality gate)
* **التاريخ والوقت:** 2026-10-08 05:50
* **الدور المفعل:** backend-architect / frontend-vue / i18n-guardian / qa-tester / code-reviewer / security-auditor / docs-historian (3 tracks متوازية + quality gate + مراجعتين + fixup)
* **الهدف:** قفل البند الـ blocking من [سجل Phase 0](01-phase0-security-hotfixes.md) (عقد الـ split payment)، وتنفيذ قرارات الـ CTO في [product-overview](../../01-overview/product-overview.md) (§13 quick-login للاختبار فقط، وخيار A للدفع المقسم)، وتنضيف البقايا اللي Phase 0 سابها، وتمرير كل التغييرات من بوابة الجودة (Pint/Larastan/ESLint/Prettier).

> **الحالة:** كل الشغل **uncommitted** على `feature/multi-tenant`. مفيش push ولا deploy. والـ suite الكاملة خضرا (591/591).

## 1. الملفات المعدلة

### Track SP — عقد الـ split payment (قرار CTO: خيار A)
القاعدة: الكاش يقدر يزيد عن الصافي والباقي بيتخصم من سطور الكاش، وغير الكاش (فيزا/محفظة) ميزيدش عن المستحق.
* `[NEW]` `backend/database/migrations/tenant/2026_10_08_000002_add_change_amount_to_invoices_table.php` — `decimal('change_amount', 12, 3)->default(0)` بعد `remaining_amount`، بـ `hasColumn` guard و`down()` حقيقي. migration مستأجر.
* `[MODIFIED]` `backend/app/Models/Invoice.php` — `change_amount` في `$fillable` + cast `decimal:3`. وفي الـ fixup: `@property` لكل الأعمدة (الفلوس `string`)، وreturn types/generics لـ `customer`/`user`/`store`/`items`/`additionalExpenses`.
* `[MODIFIED]` `backend/app/Http/Resources/InvoiceResource.php` — `change_amount` بيرجع string (`"100.000"`)، مش float زي الحقول القديمة جنبه.
* `[MODIFIED]` `backend/app/Services/InvoiceService.php` — method خاص جديد `resolveCheckoutPayments()` بدل بلوكات S4/S5. حساب بس (bcmath scale 3) جوه الـ `DB::transaction` الموجود:
  * آجل + `payments[]` ← 422 (`credit_invoice_cannot_have_payments`).
  * جزئي: لازم `0 < paid < net`، ومفيش باقي.
  * كاش/مدفوع بالكامل: غير الكاش > net ← 422 `invoices.split_payment_non_cash_exceeds_due`؛ المجموع < net ← 422 `invoices.split_payment_below_net`؛ غير كده الباقي = المجموع − net ويتخصم من آخر سطر كاش، والسطر اللي يوصل 0.000 يتشال. و`LogicException` guard لو مجموع السندات ≠ net.
* `[MODIFIED]` `backend/lang/{ar,en}/invoices.php` — مفاتيح `split_payment_non_cash_exceeds_due` و`split_payment_below_net` و`payment_note_split` و`payment_note_on_issue`، واتشال المفتاح غير المستخدم `invalid_expense_paid_by` (F3b).
* `[NEW]` `backend/tests/Feature/Api/PosCheckoutIntegrityApiTest.php` — 66 تست (كان untracked من Phase 0 واتوسع). حالة "split فوق الـ net" اتحولت من 422 لنجاح (تغيير عقد مقصود، مش إضعاف تست).
* `[NEW]` `backend/resources/js/helpers/decimal.js` — حساب عشري دقيق بـ BigInt (بالألف)، truncation زي bcmath، و`dPercent` بيطابق `bcdiv(bcmul(x, pct, 4), '100', 3)`. من غير `parseFloat`/`toFixed`.
* `[NEW]` `backend/resources/js/helpers/posCheckout.js` + `backend/resources/js/Composables/usePosCheckout.js` — حساب الـ net زي السيرفر، وبناء الـ payload كـ strings بـ 3 خانات، ومبقاش بيفرض `payment_type`.
* `[MODIFIED]` `backend/resources/js/Components/POS/POSMultiPaymentModal.vue` — كل الحساب بـ `decimal.js`، prop `paymentType` (cash/partial)، رسالة inline لما غير الكاش يزيد، وصف "الباقي" (`pos.change_due_label`).
* `[MODIFIED]` `backend/resources/js/views/POS/PosView.vue` — قسم الـ checkout بس: الباقي اللي بيظهر بعد البيع = `change_amount` من السيرفر. وweb print fallback بقى `?autoprint=true` (SP-8). الملف بقى 949 سطر (legacy).
* `[MODIFIED]` `POSSuccessModal.vue`، `POSCheckoutPanel.vue`، `POSCartTable.vue` — توصيل الـ props الجديدة.
* `[MODIFIED]` `backend/resources/js/Composables/useNativeBridge.js` — fallback الطباعة بقى `/invoices/{id}/print?autoprint=true`.
* `[MODIFIED]` `backend/lang/{ar,en}/pos.php` — `pos.split_total_below_net` (جديد ومحدش بيستخدمه لسه) وتنضيف المفاتيح المكررة.
* `[NEW]` `e2e/flows/decimal-helper.spec.js`، `e2e/flows/pos-checkout-payload.spec.js`، `e2e/flows/pos-split-checkout-flow.spec.js` — و`[NEW]` `e2e/flows/invoice-print-flow.spec.js` اتحدث للـ `?autoprint=true`.

### Track AUTH — Auth hardening + quick-login للاختبار فقط
* `[NEW]` `backend/app/Support/QuickLoginGate.php`، `backend/app/Http/Middleware/EnsureQuickLoginAllowed.php`، `DenyQuickLoginToken.php`، `backend/app/Http/Requests/Auth/QuickLoginRequest.php`، `backend/app/DTOs/Auth/QuickLoginDTO.php`، `backend/app/Actions/Auth/QuickLoginAction.php`، `ListQuickLoginUsersAction.php`، `BuildAuthPayload.php`، `backend/app/Http/Resources/QuickLoginUserResource.php` — quick-login **رجع** بس بشروط (AUTH-1a): flag `QUICK_LOGIN_ENABLED` (default off) + مش production + tenancy initialized. الراوتات مش بتتسجل أصلًا لو الشروط مش متحققة، وفيه check تاني runtime (404). بيرفض `super_admin` والمستخدم غير النشط، والـ lookup بالـ id بس، والتوكن ability `quick-login` بس وعليه TTL (default 480 دقيقة)، وقايمة المستخدمين id+name بس.
* `[MODIFIED]` `backend/config/auth.php`، `backend/.env.example` — بلوك `quick_login` ومفاتيح env فاضية.
* `[MODIFIED]` `backend/app/Providers/AppServiceProvider.php` — rate limiters: `auth-login` (20/د لكل IP + 10/د لكل identifier+IP)، `quick-login` (5/د لكل tenant+IP)، `public-config` (60/د)، `public-translations` (60/د)، `tenant-resolve` (10/د). كلها 429 JSON مترجم.
* `[MODIFIED]` `backend/routes/api.php` — ربط الـ throttles وراوتات الـ quick-login و`POST /api/v1/super-admin/telescope-link` (`throttle:10,1`).
* `[MODIFIED]` `backend/app/Http/Middleware/ApiTokenAuth.php` (AUTH-3) — التوكن من `Authorization: Bearer` أو `X-API-TOKEN` بس. اتشال `?api_token=` وfallback عمود `users.api_token`. التوكن المنتهي ← 401 `auth.session_expired` ويتمسح. الـ fallback المركزي فضل (Sanctum بس) وعليه TODO لـ Phase 1.
* `[NEW]` `backend/app/Actions/Auth/IssueTelescopeLinkAction.php`، `ConsumeTelescopeLinkAction.php`، `backend/app/Http/Controllers/Api/V1/SuperAdmin/TelescopeLinkController.php`، `backend/app/Http/Controllers/Auth/TelescopeAccessController.php` — (AUTH-4) signed URL صالح 60 ثانية ولمرة واحدة (`Cache::add`)، مركزي فقط. الـ closure في `routes/web.php` اتشال (ده كمان بيسمح بـ `route:cache`).
* `[MODIFIED]` `backend/app/Providers/TelescopeServiceProvider.php`، `backend/routes/web.php`، `backend/lang/{ar,en}/super.php`.
* `[MODIFIED]` `backend/resources/js/stores/auth.js`، `Layouts/SpaLayout.vue`، `Components/Navigation/DesktopSidebar.vue` — (AUTH-5a) الـ super-admin في الواجهة = `user.is_super_admin === true` بس. `SpaLayout` كان بيدي صلاحية لأي إيميل فيه "admin".
* `[MODIFIED]` `backend/resources/js/Layouts/SuperAdminLayout.vue` — (AUTH-5b) زرار Telescope بيطلب signed link بدل ما يحط الـ bearer token في الـ URL.
* `[MODIFIED]` `backend/resources/js/views/Auth/WorkspaceConnectView.vue` — (AUTH-5c) مبقاش بيخزن `tenant_users` وبيمسح القديم، ورسالة 429 مترجمة.
* `[NEW]` `backend/resources/js/Composables/Auth/useQuickLogin.js`، `Components/Auth/QuickLoginPanel.vue`، `Components/Auth/LoginMethodTabs.vue` + `[MODIFIED]` `views/Auth/LoginView.vue` — (AUTH-1b) الـ tab بيظهر بس لو `GET /auth/options` رجّع `quick_login === true`. الباسورد هو الافتراضي دايمًا، والقايمة في الذاكرة بس.
* `[MODIFIED]` `ApiLoginAction.php`، `ApiLogoutAction.php`، `LoginAction.php`، `ApiMeAction.php`، `GetSystemContextAction.php`، `stores/appConfig.js`، `lang/{ar,en}/{auth,common}.php` — (AUTH-6) حوالي 25 نص hardcoded اتحولوا لمفاتيح.
* `[NEW]` `QuickLoginApiTest.php`، `TelescopeAccessTest.php`، `e2e/flows/quick-login-flow.spec.js` + `[MODIFIED]` `AuthApiTest.php`، `CentralTenantResolverApiTest.php`، `SystemContextApiTest.php`.

### Track F — Leftovers من Phase 0
* `[MODIFIED]` `backend/database/seeders/DatabaseSeeder.php` — (F1a) super admin واحد بس، الهوية من `SEED_SUPER_ADMIN_*`، والباسورد من env أو `Str::password(16)` بيتطبع مرة واحدة لو اتعمل جديد بس. `firstOrCreate` مش بيكتب فوق مستخدم موجود.
* `[MODIFIED]` `TenantSampleSeeder.php`، `RealisticEnterpriseDataSeeder.php`، `RichDemoDataSeeder.php`، `StoreSystemMigrationSeeder.php`، `PopulateRealisticTenantDataCommand.php`، `[NEW]` `lang/{ar,en}/console.php` — مفيش باسوردات literal ولا موبايلات حقيقية.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` — (F1b) اتشال fallback الموبايل الـ hardcoded (كان بيتشحن في الـ bundle).
* `[NEW]` `desktop/src/config/supportContact.js`، `desktop/test/supportContact.test.js` + `[MODIFIED]` `desktop/src/menu/appMenu.js`، `desktop/src/config/settingsStore.js` — (F1c) رقم واتساب الدعم من `SROOR_SUPPORT_WHATSAPP` أو setting `supportWhatsapp` (مش قابل للكتابة من الـ renderer)، والبند بيختفي لو مفيش رقم صالح.
* `[MODIFIED]` `backend/tests/TestCase.php` (`ADMIN_PHONE = '01000000500'`) و43 ملف تست + 6 ملفات e2e تحت `backend/` + `backend/eslint.config.js` — (F1d) استبدال كل الموبايلات الحقيقية بقيم وهمية. `[NEW]` `tests/Unit/NoHardcodedRealPhonesTest.php` + `tests/Concerns/DetectsRealPhoneNumbers.php` (guard).
* `[NEW]` `backend/database/seeders/TenantDatabaseSeeder.php` + `[MODIFIED]` `backend/config/tenancy.php` — (F2) `tenants:seed` بقى يشغّل seeder آمن للمستأجر (`PermissionsSeeder` بس) بدل الـ central `DatabaseSeeder`.
* `[MODIFIED]` `backend/app/Console/Commands/AuditTenantSuperAdminRolesCommand.php` — (F3a) بيعد صفوف `model_has_roles` اللي `model_type` بتاعها User بس.
* `[MODIFIED]` `desktop/main.js` — (F4) `network:ping` بيستخدم `http` لـ origins الـ `http:` وبيمرر `urlPolicyOptions`، و`res.resume()`. Prettier عمل reformat للملف كله.
* `[MODIFIED]` `backend/app/Actions/AppVersions/DownloadLatestApkAction.php` + `lang/{ar,en}/app_update.php` — (F5) اتشال الـ fallback لملفات على الديسك. بيخدم الملف بس لو فيه `AppVersion` نشط لنفس المنصة وملفه موجود، وإلا 404 بـ `app_update.file_not_available`. يعني `ios` مبقاش ياخد APK.

### Quality gate + fixup
* `[MODIFIED]` 56 ملف PHP بـ `pint --dirty` (cosmetic: spacing/imports)، منهم `AppUpdateController`، `InvoiceController`، `PosController`، 11 Form Request، `UserResource`، `config/services.php`، `PermissionsSeeder`، `routes/tenant.php`، `public/update_webhook.php` (whitespace بس) و38 ملف تست.
* `[MODIFIED]` `SpaLayout.vue` — شيل dead code كان عامل 4 أخطاء ESLint قديمة (`versionData`، `wasCollapsedBeforePos`، `handleItemHover`/`handleItemLeave`، `hoveredTooltip`). السلوك ماتغيرش.
* `[NEW]` `backend/resources/js/helpers/uuid.js` + 7 ملفات desktop — Prettier بس.
* `[MODIFIED]` `backend/app/Models/InvoiceItem.php`، `backend/app/Models/Tenant.php` — (fixup) typing عشان ملفات Phase 0 الجديدة (`InvoicePrintController`، `EnsureCentralContext`) ماتتجمدش في الـ baseline. `Tenant::domains()` بقى typed override لـ stancl بنفس الـ query.
* `[NEW]` `backend/phpstan-baseline.neon` — اتعمله regenerate مرتين (حذف بس).

## 2. القرارات التقنية
* **Split payment خيار A (قرار CTO):** السيرفر هو مصدر الحقيقة. السندات بتتسجل بالـ net بالظبط، والباقي بيتخزن في `change_amount` ومش بيدخل الخزينة. البديل (الـ client يرفض أي overpay) اترفض لأنه بيكسر بيع الكاش العادي بالباقي.
* **الحساب في الواجهة بـ BigInt** بدل مكتبة npm، عشان مفيش dependency جديدة ولازم يطابق truncation الـ bcmath بالظبط. اتراجع على PHP bcmath فعلًا في 5 حالات.
* **quick-login رجع** رغم إن Phase 0 شاله، لأن قرار الـ CTO (§13) إنه يفضل للاختبار فقط. الحماية في 3 طبقات (تسجيل الراوت، middleware runtime، `authorize()`) عشان `route:cache` القديم ميفتحوش.
* **`auth-login` per-identifier = 10 مش 6** عن قصد: بـ 6 الـ 429 كان هيسبق الـ 422 بتاع عداد الفشل الموجود في `ApiLoginRequest`.
* **Telescope:** signed URL + nonce لمرة واحدة بدل token في الـ query، لأن الـ query بيتسرب في الـ logs والـ Referer.
* **Seeders:** `Env::get` بدل `env()` في الـ seeder (مع تعليق) عشان قيم وقت الـ seed تتقري من الـ process حتى مع config cache. مفيش suppression.
* **`TestCase::ADMIN_PHONE = 01000000500`** مش `...001` عشان `users.phone` unique وفيه تستات بتستخدم `01000000000-003`.
* **Larastan:** الحل كان typing الموديلات مش إضافة للـ baseline، حسب `.claude/rules/backend-architecture.md`. `Invoice::invoice_date` فضل `Carbon|null` عشان non-null كان هيطلّع 3 أخطاء في كود legacy.
* **Tenancy:** `change_amount` و`TenantDatabaseSeeder` على DB المستأجر. Telescope link و`AppVersion` مركزي. مفيش تغيير في isolation.

## 3. التحقق والاختبار
* **Full PHPUnit (`php artisan test`)، آخر 3 تشغيلات (gate، fixup، rerun):** **591/591 نجحوا، 4724 assertion** (حوالي 470–510 ثانية). التشغيلات الأقدم أثناء الشغل كانت حمرا (42 ثم 24 ثم 10 ثم 7 فشل) بسبب تستات اتكتبت قبل إصلاح tracks تانية، وكلها اتقفلت.
* `--filter=PosCheckoutIntegrityApiTest`: 66/66، 651 assertion. والـ suites المجاورة (Invoice/Pos/Payment/Returns/Shift/Treasury/Concurrency/InvoicePrint/LangKeyParity…): 116/116.
* Auth (QuickLogin/Telescope/AuthApi/Resolver/SystemContext): 75/75، 501 assertion. `TelescopeAccessTest` لوحده 11/11 (كان 10 منهم فاشلين قبل AUTH-4).
* Leftovers (Phones/SeederSecurity/TenantSeeder/AuditCommand/AppUpdate): 47/47، 144 assertion. `NoHardcodedRealPhonesTest` 12/12.
* `security-auditor` شغّل 172 تست أمان: كلهم نجحوا (1106 assertion).
* **Desktop:** `npm test` 62/62.
* **Static:** `pint --test` نجح على 156 ملف PHP متغير. `php -l` نظيف. Larastan level 5: **0 أخطاء**. الـ baseline: 805 خطأ/616 مدخل ← 800/611 (gate) ← **537 مدخل / 698 خطأ** حاليًا (fixup، حذف بس). تقرير الـ fixup بدأ من 606 مدخل؛ الفرق بين 611 و606 جه من tracks تانية وغير موثّق بالتفصيل. ملفات `InvoicePrintController` و`EnsureCentralContext` ملهمش أي مدخل.
* **ESLint:** backend 0 أخطاء، تحذير legacy واحد (`vue/component-definition-name-casing` في `PosView.vue:170`). desktop 0 أخطاء. **Prettier check:** نظيف في الحزمتين.
* **`npm run build`:** نجح أكتر من مرة (آخرها في الـ gate)، تحذير الـ chunk size القديم بس.
* **Playwright:**
  * `invoice-print-flow.spec.js`: 5/5 على desktop وmobile وtablet.
  * `quick-login-flow.spec.js`: 6 نجحوا و3 skipped (حالة الـ login الحقيقي محتاجة الـ flag) على الـ 3 projects.
  * `decimal-helper.spec.js` 6/6 و`pos-checkout-payload.spec.js` 11/11 (Node من غير متصفح).
  * `pos-split-checkout-flow.spec.js`: **3/3 فشلوا** لأن الـ SPA المحلي مش بيعدي الـ splash (نفس الفشل في `pos-full-page-audit` و`auth-setup`)، مشكلة بيئة مش الكود.
* **مالم يُختبر:**
  * مفيش فحص بصري للـ POS modal/success modal (ar/en، dark/light، الموبايل). LoginView اتشاف headless بالعربي على 360 و1280 بس.
  * Electron ماتشغلش: قايمة الدعم وping على `http` localhost.
  * `tenants:migrate` محليًا فشل لأن DB المستأجر المحلي مش موجودة، فالـ migration الجديدة اتجربت في تستات sqlite بس.
  * `composer audit`/`npm audit` ماتشغلوش (محتاجين network). `composer` مش على الـ PATH، فـ `analyse:baseline` اتشغّل كأمر phpstan مباشرة.

## 4. ملاحظات / ديون تقنية
* **المراجعات:** `code-reviewer` رجّع `changes_required` ببند blocking واحد (ملفات Phase 0 الجديدة في الـ baseline)، والـ fixup قفله. `security-auditor` رجّع `approve` (0 critical/high).
* **Should fix (مفتوح):**
  * `DenyQuickLoginToken` deny-list: بيغطي users/roles/settings/trash بس. توكن quick-login لـ `admin` لسه يقدر يعمل/يمسح فروع، يلغي فواتير، يعدّل مخزون. الاقتراح: رفض دور `admin` في الـ quick-login، أو deny-by-default.
  * موبايل حقيقي لسه hardcoded في e2e على مستوى الـ root (`e2e/auth/login.setup.js`، `e2e/flows/login-flow.spec.js`، `e2e/flows/users-full-page-audit.spec.js`) وفي `backend/tests/e2e/reports/` المتتبع. الـ guard مش بيفحص الأماكن دي.
  * `QuickLoginGate::tokenTtlMinutes()` ملوش حد أعلى.
  * `checkoutFingerprint` في `PosView.vue` مش شامل العميل، فإعادة المحاولة بعد تغيير العميل بس بترجع 422 idempotency.
* **ديون قديمة اتلاحظت:** float في عرض الـ POS (`POSCartTable`، `POSCheckoutPanel`، `addToCart`)، `fmod((float))`/`number_format((float))` وعربي hardcoded في `InvoiceService`، قفل الأصناف بترتيب الطلب مش بالـ id (خطر deadlock)، `store_id` في طلبات POS متحقق بـ `exists` بس، `items.*.quantity` بـ `numeric` (‏`1e3` ← 500)، `InvoiceResource` لسه بيرجع الفلوس float، `updateInvoice` مش بيطبّق عقد الـ split، مفيش throttle على تحميل الـ APK، `decimal.js` بيقبل exponent بلا حد، receipt الطباعة الـ desktop من غير escaping (أثر محدود). `PosView.vue` (949 سطر) و`LoginView.vue` (407) لسه أكبر من اللازم.
* **إجراءات المالك (ماتغيرتش من Phase 0):** تدوير الأسرار، `deploy.yml:49` (فيه token قديم، (redacted)) لازم يبعت POST موقّع وإلا الـ auto-deploy هيفشل بـ 405، تنضيف `76f32ce0`، وTrustProxies على السيرفر عشان الـ rate limiters متبقاش bucket واحد، وexpiry لـ Sanctum في الإنتاج، والـ seeders بتطبع باسوردات مولّدة فلازم تبعد عن logs الـ CI.
* `phpstan.neon` و`phpstan-baseline.neon` لسه untracked.
