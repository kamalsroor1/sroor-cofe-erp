# سجل تعديل: Phase 1 — Wave 1 (الأساس المستقل)
* **التاريخ والوقت:** 2026-10-09 (الوقت الدقيق غير موثّق)
* **الدور المفعل:** backend-architect / frontend-vue / qa-tester / code-reviewer / security-auditor / docs-historian، على 6 lanes متوازية في نفس الـ working tree
* **الهدف:** تنفيذ مهام W1 من [phase-1-plan.md](../../05-planning/phase-1-plan.md) §3.2/§4: الهوية المركزية والـ throttling، schema الفوترة المركزية، إعدادات المستأجر والـ branding، الباكدجات، بنية الاختبار وCI، وتطبيقات Android/Electron.

> **الحالة:** كل الشغل **uncommitted** على `feature/multi-tenant`. مفيش commit ولا push ولا deploy، ومفيش migration اتشغّلت على أي DB غير DB الاختبار.
> **ملحوظة عن التاريخ:** اسم المجلد `2026-10-09` محدد من الـ coordinator (نفس تاريخ مراجعة الـ UX في `docs/04-ux-ui/ux-review-2026-10-09/`).
> **حدود هذا السجل:** مكتوب من تقارير الـ lanes الست (A–F) ومراجعاتها. مهام W1 التالية **ملفاتها موجودة في الشجرة لكن مفيش تقرير lane عنها في البيانات**، فحالتها **غير موثّقة** هنا: `OFFL-1` و`OPS-1` و`POSB-2` (التفاصيل في §4). وتقرير lane F (APP) وصل **مقطوعًا** في نص مراجعة APP-3، فنتيجة الـ fixup بتاعها غير معروفة.

## 1. الملفات المعدلة (حسب الـ lane)

### Lane A — الهوية (IDEN-4.1، 4.6، 1.1، 1.5، 3.1)
* `[NEW]` `backend/app/Support/QuickLogin.php` — `enabled()`، `allowed()` (الـ flag + بيئة `local`/`testing` فقط، staging وproduction مقفولين)، `tokenTtlMinutes()` (افتراضي 480)، `guardAgainstProduction()` (RuntimeException لو الـ flag شغال على production خارج الـ console).
* `[MODIFIED]` `backend/app/Support/QuickLoginGate.php`، `backend/config/auth.php`، `[NEW]` `backend/config/sanctum.php` — `sanctum.guard=[]` (Bearer فقط)، `expiration=null`، `auth.tokens.tenant_ttl_minutes` (افتراضي 43200)، guards `central` و`central_web`، ومفيش guard `super_admin`.
* `[NEW]` `backend/config/rate_limits.php`، `backend/app/Support/RateLimitKey.php`، `[MODIFIED]` `backend/app/Providers/AppServiceProvider.php` (`registerRateLimiters()`) — limiters: `tenant-login`، `central-login`، `public-api`، `tenant-resolve`، `quick-login`. اتشالت الأسماء القديمة `auth-login`/`public-config`/`public-translations`.
* `[MODIFIED]` `backend/app/Http/Requests/Auth/ApiLoginRequest.php` — عدّاد الإخفاقات بقى فيه الـ tenant (كان بيقفل نفس الـ login في كل المحلات).
* `[MODIFIED]` `backend/bootstrap/app.php` — `prependToPriorityList` بحيث `ResolveApiTenancy` يشتغل قبل الـ throttle والـ bindings (كانت الـ route-model binding بتشتغل على الـ central DB).
* `[NEW]` `backend/app/Http/Middleware/ThrottleTenantMisses.php` + `[MODIFIED]` `backend/routes/api.php` — (fixup المراجعة) حد per-IP على الـ 404 بتاعة workspace code غير موجود، قبل `ResolveApiTenancy`.
* `[NEW]` `backend/app/Models/CentralPersonalAccessToken.php`، `[MODIFIED]` `backend/app/Models/CentralUser.php` — `CentralUser` مستقل (مش بيورث `User`)، جدول `central_users`، connection مركزي مثبّت، guard `central`، token افتراضي 240 دقيقة وability `central:*`.
* `[NEW]` migrations مركزية: `2026_10_10_100000_create_central_users_table.php`، `2026_10_10_100010_create_central_personal_access_tokens_table.php`، `2026_10_10_100500_create_central_audit_logs_table.php`.
* `[NEW]` `backend/database/factories/CentralUserFactory.php`، `backend/config/central.php`.
* `[MODIFIED]` `backend/app/Http/Middleware/ApiTokenAuth.php`، `backend/app/Console/Commands/AuditTenantSuperAdminRolesCommand.php`.
* `[NEW]` `backend/app/Models/CentralAuditLog.php`، `Models/Builders/CentralAuditLogBuilder.php`، `Services/CentralAuditLogger.php`، `Enums/CentralAuditEvent.php`، `Exceptions/CentralAuditLogImmutableException.php`، `lang/{ar,en}/central_audit.php` — audit مركزي append-only، بيشيل الأسرار قبل الحفظ.
* `[NEW]` `backend/app/Enums/TenantStatus.php` (7 حالات بدون `expired`)، `TenantLifecycleActor.php`، `TenantAccessLevel.php`، `backend/app/Support/Tenancy/*` (Policy + Decision + Snapshot + Step + TrialExtensionDecision)، `backend/config/tenant_lifecycle.php`، `lang/{ar,en}/subscription.php`.
* `[NEW]`/`[MODIFIED]` تستات: `tests/Unit/Support/QuickLoginTest.php`، `QuickLoginProductionGuardTest.php`، `tests/Feature/Api/LoginThrottleApiTest.php`، `AuthApiTest.php`، `SystemContextApiTest.php`، `tests/Unit/CentralUserModelTest.php`، `CentralAuditLoggerTest.php`، `tests/Unit/Tenancy/TenantLifecyclePolicyTest.php`، `TenantLifecycleConfigTest.php`.

### Lane B — الباقات والفوترة المركزية (ENTI-1.1…1.7)
* `[NEW]` `backend/app/Enums/Billing/*` (9 enums + `Concerns/BillingEnum`، `HasTranslatedLabel`) و`lang/{ar,en}/billing.php` (53 مفتاح، parity كاملة). منفصلة عن `App\Enums\PaymentMethod` بتاع الـ POS.
* `[NEW]` `backend/app/Models/Concerns/UsesCentralConnection.php` — trait بيثبّت `tenancy.database.central_connection`.
* `[NEW]` migration `2026_10_10_200100_update_plans_table_for_billing.php` — أسعار `DECIMAL(12,3)`، الحدود nullable (NULL = غير محدود، والقيم all-nines القديمة اتحولت NULL)، أعمدة `max_warehouses`، `max_vans`، `trial_days`، `is_public`، `founder_price_*`، `name_key`.
* `[MODIFIED]` `Models/Plan.php`، `PlanFeature.php`، `Tenant.php`، `Http/Requests/UpdatePlanRequest.php`، `Http/Resources/PlanResource.php`.
* `[NEW]` migration `2026_10_10_200200_update_subscriptions_table_for_billing.php` — `status`/`billing_cycle` من ENUM لـ string(20)، `amount` لـ `DECIMAL(12,3)`، `currency`، `price_locked`، `is_founder`، `founder_price_until`. `[MODIFIED]` `Models/Subscription.php`، `Services/TenantProvisionerService.php`.
* `[NEW]` `2026_10_10_200300_create_addons_table.php`، `200310_create_plan_addon_table.php`، `200320_create_subscription_addons_table.php`، `Models/Addon.php`، `PlanAddon.php`، `SubscriptionAddon.php`، `Exceptions/Billing/AddonPricingException.php`.
* `[NEW]` `2026_10_10_200500_create_billing_sequences_table.php`، `200510_create_billing_invoices_table.php`، `200520_create_billing_payments_table.php`، `Models/BillingSequence.php`، `BillingInvoice.php`، `BillingPayment.php`، `Services/Billing/BillingSequenceService.php`، `Exceptions/Billing/BillingSequenceException.php`، `config/billing.php`.
* `[NEW]` `Services/Billing/FounderPricingService.php`، `Exceptions/Billing/FounderPricingException.php` — أول 50 عميل دافع، عدّاد مقفول على صف `founder_slot` في `billing_sequences`.
* `[NEW]` تستات `tests/Feature/Billing/*` (BillingEnums، PlansSchemaMigration، SubscriptionsSchemaMigration، AddonModel، AddonPricing، SubscriptionAddonModel، BillingSequenceService، BillingInvoicesSchema، FounderPricingService).

### Lane C — الـ core والإعدادات والـ branding (CORE-1، SETG-1/2/3/7، BRND-1)
* `[MODIFIED]` `backend/app/Support/TenantCache.php` — scopes صريحة: `keyFor/versionFor/bumpFor` للمستأجر و`centralKey/centralVersion/centralBump` للمركز. مفاتيح المركز بقت `c:{key}` بدل `t:central:{key}`. `[MODIFIED]` `.claude/rules/multi-tenancy.md` (سطر واحد).
* `[NEW]` `backend/app/Services/Settings/TenantSettings.php` — عملة (allowlist 20 كود)، منطقة زمنية، لغة افتراضية، أرقام `western`. `[MODIFIED]` `UpdateSettingsRequest.php`، `SettingController.php`، `lang/{ar,en}/settings.php`.
* `[NEW]` `backend/app/Support/TenantClock.php` — "اليوم" حسب منطقة المستأجر في التقارير والـ dashboard واليومية وأرقام الورديات. `[MODIFIED]` `ReportFilterDTO.php`، `ReportController.php`، `DailyJournalController.php`، `GetDailyJournalAction.php`، `GetShiftZReportAction.php`، `GetDashboard*Action.php` (3)، `DashboardAnalyticsService.php`، `ShiftService.php`.
* `[NEW]` tenant migration `2026_10_10_310000_add_locale_to_users_table.php`، `Support/RequestLocaleResolver.php`، `Http/Middleware/SetRequestLocale.php`، `Providers/LocalizationServiceProvider.php`. `[MODIFIED]` `bootstrap/providers.php`، `Models/User.php`، `UpdateProfileRequest.php`، `UpdateProfileAction.php`، `UserResource.php`، `lang/en/validation.php`.
* `[NEW]` `Services/Settings/SettingSecrets.php`، `Http/Requests/Settings/SendTestTelegramRequest.php`، `DTOs/Settings/TelegramTestDTO.php`، `Actions/Settings/SendTestTelegramAction.php`، tenant migration `2026_10_10_310100_seed_settings_manage_permission.php`. `[MODIFIED]` `UpdateSettingsAction.php`، `TelegramService.php`، `GetSystemContextAction.php`، `routes/tenant.php` (حذف 6 routes إعدادات قديمة مكسورة)، `PermissionsSeeder.php`.
* `[MODIFIED]` `resources/js/helpers/companyInfo.js` (جديد)، `Composables/useInvoiceShow.js`، `Components/Invoices/Show/InvoiceShowA4Document.vue` — شيل أرقام قانونية وهمية.
* `[NEW]` central migrations `2026_10_10_400000_create_platform_settings_table.php`، `400010_move_legacy_platform_settings_to_platform_settings_table.php`، `Enums/PlatformSettingKey.php`، `Models/PlatformSetting.php`، `DTOs/Branding/PlatformBrandingDTO.php`، `Services/Branding/PlatformBranding.php`، `config/branding.php`. `[MODIFIED]` `AppServiceProvider.php` (override `app.name`/mail from).
* `[NEW]` تستات: `TenantCacheScopeTest`، `TenantSettingsApiTest`، `TenantTimezoneReportTest`، `LocaleResolutionTest`، `RequestLocaleResolverTest`، `SettingsSecretsApiTest`، `InvoiceLegalInfoSettingsApiTest`، `SettingsManagePermissionTest`، `RouteActionsExistTest`، `tests/Feature/Branding/*` (3)، `tests/js/companyInfo.test.js`.

### Lane D — الباكدجات (PKG-1، PKG-2)
* `[MODIFIED]` `backend/composer.json`، `composer.lock`، `package.json`، `package-lock.json` — pennant 1.26.0، fortify 1.41.0 (مستبعد من auto-discovery هو و`laravel/passkeys`)، horizon 5.50.0، laravel-backup 10.3.3، activitylog 4.12.3 (5.x محتاج PHP 8.4)، medialibrary 11.23.9، laravel-health 1.40.2، sentry-laravel 4.28.0، `@vueuse/core` 15.0.0. ومعاها `laravel/framework` 13.25 ← 13.35 و`@capacitor/android`/`cli` 8.5.3 وaxios وvue (security bumps).
* `[NEW]` `config/pennant.php`، `config/sentry.php`، `config/media-library.php`؛ `[MODIFIED]` `config/filesystems.php` (disks `central_public`/`central_private` + link `public/central-assets`)، `docs/03-architecture/package-adoption-plan.md`.
* `[NEW]` `Models/CentralMedia.php`، `Models/Concerns/InteractsWithCentralMedia.php`، migrations `2026_10_10_050000_create_media_table.php` (central + tenant).
* `[MODIFIED]` `backend/phpstan.neon` — إضافة `tests/Fixtures/` لمسارات التحليل (غالبًا PKG-2، المالك غير مؤكد في البيانات).
* `[NEW]` `tests/Feature/Platform/PackageAdoptionTest.php`، `tests/Feature/Tenancy/MediaConnectionRoutingTest.php`، `tests/Fixtures/Media/*` (2).

### Lane E — الجودة وCI (IDEN-4.8، QA-1، OPS-4)
* `[NEW]` `backend/tests/TenantTestCase.php`، `tests/Concerns/InteractsWithTenants.php`، `tests/Feature/Tenancy/TenantHarnessSmokeTest.php` — harness مستأجرين حقيقي (sqlite + MySQL)، بدون لمس `tests/TestCase.php`.
* `[MODIFIED]` `.github/workflows/ci.yml` — job `mysql` (MySQL 8.4 بـ `docker run`، key عشوائي وقت التشغيل) وjob `gitleaks` (8.30.1 بتحقق SHA-256، `--redact`، `--ignore-gitleaks-allow`).
* `[NEW]` `backend/phpunit.mysql.xml` (بدون `APP_KEY` بعد الـ fixup)، `tests/mysql-bootstrap.php`، `tests/Feature/Concurrency/MysqlConcurrencyTest.php`، `tests/Support/concurrency-worker.php`.
* `[NEW]` `.gitleaks.toml`، `.gitleaksignore` (fingerprints فقط)، `scripts/ops/render-env.sh`، `scripts/ops/tests/render-env-test.sh`، `scripts/ops/tests/gitleaks-selftest.sh`، `tests/Unit/EnvExampleFilesHaveNoSecretsTest.php`، `docs/07-operations/secrets.md`.

### Lane F — التطبيقات (APP-2، APP-3، APP-5)
* `[MODIFIED]` `backend/capacitor.config.json` — `webDir: "capacitor-www"` (كان `public`). `[NEW]` `backend/scripts/capacitor/{webAssets,build-web,check-apk}.mjs` + `capacitor-web.test.mjs`. `[MODIFIED]` `.gitignore` (`/backend/capacitor-www/`)، `backend/package.json` (scripts `cap:*`/`test:scripts`).
* `[NEW]` `resources/js/helpers/appUpdate.js`، `Composables/appUpdate/createAppUpdater.js`، `scripts/app-update/appUpdate.test.mjs`، `android/.../AppUpdaterPlugin.java`، `docs/07-operations/android-update-checklist.md`. `[MODIFIED]` `Composables/useAppUpdate.js`، `Components/AppUpdateModal.vue`، `android/.../MainActivity.java`، `lang/{ar,en}/app_update.php`. `[DELETED]` `Composables/useAppUpdater.js`، `Components/Common/AppUpdateModal.vue` (المسار القديم اللي فيه الـ loop والتقدم الوهمي).
* `[MODIFIED]` `desktop/package.json` + lock (Electron ^44.7.0، electron-builder ^26، شيل `electron-store`)، `desktop/main.js`، `src/hardware/printerManager.js`، `cashDrawer.js`. `[NEW]` `desktop/src/hardware/{escpos,rawPrinter,printFallback,drawerKick,printJob}.js` + 6 تستات في `desktop/test/`.
* `[MODIFIED]` `Composables/useBiometricAuth.js`، `[NEW]` `Composables/biometric/createBiometricAuth.js`، `tests/js/biometricAuth.test.js`، `lang/{ar,en}/auth.php`، `docs/07-operations/desktop-print-drawer-checklist.md`.

## 2. القرارات التقنية
* **CentralUser مستقل (IDEN-1.1) في W1 وتحويل الـ auth في W2:** ده سبّب 5 تستات حمرا لـ lanes تانية. اتحلّت بالـ fixups من غير فتح مسار auth جديد مؤقت: `PlansSchemaMigrationTest` بقى يستخدم `App\Models\User` المركزي بدور `super_admin` (نفس `SuperAdminApiTest`)، وassertion `PlatformSuperAdmin::check(CentralUser)` اتشال من الـ harness smoke ويرجع في تستات IDEN-1.2. **الـ super-admins في production لسه `App\Models\User` قديم، ومفيش تغيير سلوك.**
* **ترتيب الـ middleware (IDEN-4.6):** tenancy قبل الـ throttle عشان مفاتيح الـ limiters تبقى per-tenant. ده فتح oracle لتخمين workspace codes بلا حد (مراجعة: MEDIUM blocking). اتقفل بـ `ThrottleTenantMisses` (bucket `tenant-miss|<ip>`، نفس ميزانية `tenant_resolve` = 10/دقيقة)، والـ 429 بيطبق على أي طلب فيه tenant من نفس الـ IP عشان الرد ميكشفش وجود الكود. التكلفة: محل وراء NAT واحد بجهاز على كود غلط ممكن ياخد 429 لمدة دقيقة.
* **IDEN-1.5 بدون spatie/activitylog:** وقت التنفيذ الباكدج مكانش متثبت (PKG-1 شغال). اتعمل الـ fallback بنفس أسماء أعمدة activitylog عشان النقل بعدين من غير data migration (`TODO(CTO)`). ملحوظة: الخطة بتسمح بالـ fallback بس لو الباكدج مش داعم Laravel 13، وPKG-1 ثبّت 4.12.3 بعدها.
* **الفوترة كلها central:** كل الـ models الجديدة بـ `UsesCentralConnection` ومتختبرة جوه tenant context. الفلوس `DECIMAL(12,3)` + `decimal:3` + bcmath. `BillingSequenceService` بيرفض يشتغل برّه transaction على الـ central connection، و`lockForUpdate` على `(key, period)`، والـ rollback بيرجّع الرقم (gap-free). `billing_invoices`/`billing_payments` بدون FK على `tenant_id` عشان السجل المحاسبي يعيش بعد حذف المستأجر.
* **SETG-2 والمنطقة الزمنية:** الـ storage فضل على `app.timezone` = `Africa/Cairo` (مش UTC) عشان مفيش داتا تتحرك. المراجعة لقت إن جانب الكتابة لسه بيختم التواريخ بتوقيت السيرفر (`now()->toDateString()` في POS/Invoice/Payment/Return/...) فمستأجر في دبي هيشوف بيع 00:30 في تقرير امبارح. الـ fixup: `TenantSettings::selectableTimezones()` بيرجع `app.timezone` بس (`TODO(CTO)`)، وأي منطقة تانية = 422. جانب القراءة (`TenantClock`) جاهز للتوسعة.
* **SETG-3 أولوية اللغة:** user ← `X-Locale` (ar|en فقط) ← default المستأجر ← `ar`. ده بيخالف ترتيب الخطة (user ← tenant ← ar)، ومعلَّم `TODO(CTO)`.
* **SETG-7 (مش في §4 من الخطة، من tenant-settings-catalog §6.2):** `telegram_bot_token` بقى write-only (`""` + `has_telegram_bot_token`)، نص الـ exception مبقاش بيرجع للعميل، و`settings.manage` permission جديدة.
* **PKG-1 و Q-O2:** Sentry بدل Nightwatch (Nightwatch محتاج agent دائم مينفعش على Hostinger). Fortify مستبعد من auto-discovery عشان ميفتحش `/login`/`/register` على الـ web.
* **PKG-2:** الـ media الافتراضي يتبع الـ default connection (tenant جوه طلب مستأجر)، و`CentralMedia` مثبت مركزيًا ومسجّل الـ observer بنفسه (اتأكد بـ mutation check).
* **APP-2:** الـ APK القديم (`public/app.apk`) كان فيه `update_webhook.php` و`index.php` وAPKs متداخلة (104 entry ممنوعة). `webDir` منفصل بـ allowlist من 6 ملفات. محتوى الـ webhook ما اتطبعش.
* **APP-3:** تحميل حقيقي + تحقق SHA-256 من `app_versions` + تحقق شهادة التوقيع (تقبل rotation، ترفض مفتاح مختلف أو signer غير مقروء). اتشال الـ fake version bump اللي كان سبب الـ loop.
* **OPS-4:** مفيش أي قيمة سر في `.gitleaksignore` أو `secrets.md`؛ كل الـ scans بـ `--redact`. الـ `APP_KEY` الحرفي اتشال من `phpunit.mysql.xml` وبقى بيتولد وقت التشغيل بدل ما يتعمله baseline.

## 3. التحقق والاختبار
الأوامر من `backend/` إلا لو اتذكر غير كده. الأرقام زي ما الـ lanes والمراجعين بلّغوها.

* **Full suite — أعلى تشغيلات متسجلة:**
  * PKG-2: `php artisan test` ← 1279 تست، 1274 ناجح، 5 skipped، 0 فشل (exit 0).
  * مراجعة PKG: 1282 تست، 1277 ناجح، 5 skipped، 0 فشل.
  * BRND-1: 1271 تست، 1266 ناجح، 5 skipped، 0 فشل.
  * SETG-7: 1242 تست، 1235 ناجح، 2 errors من lanes تانية في نص التعديل (`ThrottleTenantMisses` لسه مش موجود، data provider في `PackageAdoptionTest`).
  * تشغيلات أقدم في نفس اليوم كان فيها 5 فشل (4 `PlansSchemaMigrationTest` + 1 `TenantHarnessSmokeTest`)، و819 error مؤقت وقت `composer install` بتاع PKG-1.
  * **غير موثّق:** تشغيل full suite واحد بعد **كل** الـ fixups (A وB وC وE وF) مع بعض. fixup lane A بيقول "the full suite passes" من غير أرقام. **ده لازم يتعمل قبل أي commit.**
  * `--parallel` مش متاح (paratest مش متثبت)، فالتشغيل serial حوالي 12–13 دقيقة.
* **Targeted (عينات):**
  * Lane A: `QuickLogin*` 65/65؛ مع Auth/Telescope/SystemContext/CentralTenantResolver 75/75؛ `CentralUserModelTest` 10/10؛ `CentralAuditLoggerTest` 11/11؛ `tests/Unit/Tenancy` 187 (147 تركيبة من/إلى/actor)؛ فلتر المراجعة 404 تست، 403 ناجح، 1 فشل (الـ harness، اتحل بعدين في lane E). fixup ThrottleTenantMisses: 6 حالات جديدة في `LoginThrottleApiTest` (5 من 18 كانت حمرا قبل الإصلاح).
  * Lane B: `tests/Feature/Billing` ← 170 ناجح، 4 فشل، 2 skipped قبل الـ fixup؛ الـ fixup صلّح التستات الأربعة (الأرقام بعد الـ fixup غير موثّقة).
  * Lane C: 12 class تستات الـ lane ← 138/138؛ `LangKeyParityTest` ناجح؛ `node --test tests/js/companyInfo.test.js` 5/5.
  * Lane D: `PackageAdoptionTest|MediaConnectionRoutingTest` 25/25؛ `composer audit` نظيف؛ `npm audit --omit=dev` 3 moderate (`uuid` عبر `@capacitor/cli`).
  * Lane E: `TenantHarnessSmokeTest` 14/14 (sqlite، بعد الـ fixup)؛ `render-env-test.sh` 14/14؛ `gitleaks-selftest.sh` 16/16 (محليًا عند الـ lane)؛ `scripts/ops/tests/run-tests.sh` 136/136.
  * Lane F: `npm run test:scripts` 28/28؛ `desktop: npm test` 107/107؛ `biometricAuth.test.js` 13/13؛ `AppUpdateApiTest` 12/12؛ `npm run build` ناجح.
* **Quality gate:** `php -l` نظيف، Pint `--test` ناجح، PHPStan صفر أخطاء على ملفات كل lane، ومفيش إضافات في `phpstan-baseline.neon` ولا `@phpstan-ignore`. الخطأين الوحيدين: مفتاح مكرر قديم `custom_color_title` في `lang/{ar,en}/settings.php` (موجود في HEAD، و`lang/` برّه مسارات phpstan)، و`env()` قديم في `SystemContextApiTest.php:167`.
* **ESLint:** lane F 0 errors؛ مراجعة lane C 0 errors على ملفات SETG-7 (الـ lane نفسه مقدرش يشغّله وقتها لأن `@eslint/js` كان ناقص).
* **MySQL:**
  * `MysqlConcurrencyTest` 3/3 على MySQL 8.4.3 مؤقت محلي.
  * مجموعة `mysql` كاملة: 56 تست، 48 ناجح، 7 فشل، 1 error. منهم مشكلتين حقيقيتين بتظهر على MySQL بس: `AddonModelTest` (ترتيب مفاتيح JSON) و`SubscriptionsSchemaMigrationTest` (الـ `down()` بيحذف index محتاجه FK، error 1553). **حالة إصلاحهم غير موثّقة.**
  * سرعة الـ harness على MySQL Windows: 2.02–7.5 ث لكل مستأجر مقابل حد 1.5 ث. نتيجته على Linux CI لسه متتأكدتش.
  * اختبارات الـ row-lock في ENTI-1.6/1.7 اتعملها skip محليًا لأن مفيش MySQL، ومستنية الـ CI.
* **ما لم يُختبر:**
  * تشغيل فعلي لـ jobs الـ `mysql` و`gitleaks` على GitHub، ومعاه الـ PR التجريبي بسر مزروع.
  * `assembleDebug`/Gradle (اتعمل `javac` بـ stubs بس) وأي تجربة على جهاز Android.
  * التطبيق المكتبي على Electron 44 (الـ binary ما اتنزلش بسبب سياسة install scripts)، وأي طابعة أو درج حقيقي.
  * فحص بصري للـ modal (ar/en، dark/light، المقاسات).
  * `cap sync` + بناء APK بعد ترقية Capacitor 8.5.3.

## 4. ملاحظات / ديون تقنية
* **مفتوح بعد المراجعة (غير معروف إن كان اتحل):**
  * **APP-3 (blocking):** الـ APKs المتثبتة من قبل (versionCode 3 / 1.0.2 وأقدم) معندهاش plugin `AppUpdater`، والـ JS الجديد بيوصلها من السيرفر. مع forced update الـ modal مش هيتقفل وكل محاولة هتفشل برسالة شبكة مضللة، يعني الـ POS هيتقفل. الإصلاح المقترح: `Capacitor.isPluginAvailable('AppUpdater')` + fallback للتحميل القديم + خطوة في `android-update-checklist.md`. **بيانات lane F مقطوعة، ونتيجة الـ fixup غير موثّقة.**
* **مهام W1 من غير تقرير في البيانات** (ملفاتها في الشجرة):
  * `OFFL-1`: `useConnectivity.js`، `helpers/connectivity.js`، `Components/Layout/OfflineBanner.vue`، `lang/{ar,en}/connectivity.php`، `tests/js/connectivity.test.js`، تعديل `Services/api.js` و`LangKeyParityTest.php`.
  * `OPS-1`: `scripts/ops/**` (provision/steps/templates/verify)، `docs/07-operations/vps-runbook.md`.
  * `POSB-2`: `Services/Pos/ScaleBarcode*`، `StorePosSettings*`، tenant migrations `600000`/`600001`، تعديلات `Item*` و`GetPOSBootstrapDataAction`، و`lang/{ar,en}/pos.php`.
  * فيه كمان تعديلات UI كتير في `resources/js/Components/**` و`views/**` من غير lane في البيانات، ومعاها مراجعة UX (`docs/04-ux-ui/ux-review-2026-10-09/`، قراءة فقط).
* **متابعات من المراجعات (non-blocking):**
  * IDEN-4.2: شرط تسجيل routes الـ quick-login في `routes/api.php` يبقى `QuickLogin::allowed()`.
  * IDEN-1.2: guard `central` (driver sanctum) عمره ما هيعمل authenticate لـ `CentralUser`، فممنوع استخدام `auth:central`. وكمان لازم يثبّت `PermissionRegistrar`/موديلات spatie على الـ central connection.
  * `CentralAuditLogger`: الـ audit مش بيعمل rollback مع transaction المستأجر، والـ `LoginFailed` لازم يتكتب برّه الـ transaction.
  * `config/tenant_lifecycle.php`: المفتاح `sweep.max_catch_up_steps` مش بيتقري (فيه constant = 4 بدله). وOPS-12 لازم يملا `status_changed_at`.
  * `FounderPricingService`: القراءات بعد قفل العداد بتستخدم snapshot قديم على REPEATABLE READ، فمستأجر واحد ممكن ياخد slotين لو اشتراكين اتفعّلوا في نفس اللحظة (slot رقم 51 مستحيل يتوزّع). الحل قراءات locking.
  * `billing_payments.gateway_reference` unique على كل البوابات؛ الأفضل يبقى unique على `(gateway, gateway_reference)`. ده محتاج موافقة لأنه بيغيّر معيار القبول.
  * `SubscriptionAddon.tenant_id` لازم يتنسخ من الاشتراك ويتمنع ييجي من الـ input.
  * `UsesCentralConnection` بيرجع لـ `database.default` لو الـ config فاضي؛ الأفضل يرمي exception.
  * واجهة الـ super-admin (`EditPlanModal.vue`، `PlanCard.vue`) مش بتعرف NULL = غير محدود، فخطة Enterprise هتبان فاضية ومش هتتحفظ. لازم يتعالج قبل ما migration الـ W1 توصل staging.
  * `GetSystemContextAction.php:129` لسه بيقرا `platform_name` من tenant `Setting` قبل `PlatformBranding`.
  * SETG-2: باقي استخدامات "اليوم" بتوقيت السيرفر في `ReportPrintController`، `TreasuryController`، `ReorderAssistantService`، `TelegramService`.
  * `TelegramService` (MEDIUM، قديم): بيرجع لبوت المنصة لو المستأجر معندوش token، و`/settings/telegram/test` من غير throttle. والـ token متخزن plaintext.
  * `InvoiceShowA4Document.vue` و`useInvoiceShow.js` ملفات ساخنة تبع SETG-6/BRND-12، فلازم يعملوا rebase على تعديل SETG-7.
  * `tests/js/*.test.js` مش متوصلة بأي npm script أو CI.
  * PKG:
    * `storage:link` لازم يتشغل تاني في الـ deploy عشان `central-assets`.
    * Horizon routes متسجلة على كل الـ hosts بالـ gate الافتراضي (local بس).
    * activitylog من غير config منشور، فلازم يتثبت على الـ central connection.
    * `backup:run` بالـ config الافتراضي هيضم `.env`.
    * أسماء ملفات الـ media لازم تتولد server-side (`usingFileName(Str::uuid()...)`).
    * التعليق اليتيم بتاع Horizon في `.env.example`.
  * OPS-4/QA-1:
    * `render-env-test.sh` معتمد على ملفات OPS-1، فلازم ينزلوا في نفس الـ push.
    * `render-env.sh` بيستبدل أي key موجود في environment الـ runner.
    * `EnvExampleFilesHaveNoSecretsTest` بيفحص الملفات untracked كمان.
    * `markTestSkipped` في `MysqlConcurrencyTest` بيخالف `testing.md`؛ الأفضل group `mysql-only`.
    * تنضيف ملفات sqlite المؤقتة في `%TEMP%`.
    * سطور `ci.yml` 327/359/370 اتجمعت في سطر واحد.
  * APP-5: npm audit فيه 8 moderate (سلسلة electron-builder وقت البناء بس).
* **ديون قديمة اتلاحظت:**
  * `SuperAdminApiController::updatePlan` بيرجّع `$e->getMessage()` ونص عربي hardcoded.
  * `SettingController::index` فيه permission check inline وقيم افتراضية عربية.
  * `ShiftService::nextShiftNumber` بيرتب lexically.
* **أمان (لم تُنفَّذ أي إجراءات على السيرفر):**
  * الـ APKs الموزعة فيها `update_webhook.php`، فـ webhook token الـ deploy يتعامل على إنه مسرّب.
  * 91 finding تاريخي في gitleaks (redacted) محتاجين rotation حسب `docs/07-operations/secrets.md` §5.
* **commits:** الشجرة فيها ملفات مشتركة بين lanes (`.env.example`، `AppServiceProvider.php`، `config/auth.php`، `routes/api.php`، `ci.yml`، `backend/package.json`، `lang/*/settings.php`). تقسيمها per-lane محتاج staging على مستوى الـ hunk (`git apply --cached` من patch)، لأن `git add -p` تفاعلي.
