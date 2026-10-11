# سجل تعديل: Phase 1 — W2 الدفعة 2 (pipeline النشر، 2FA المركزي، محرك الـ entitlements، الصلاحيات، الفرع النشط، التقريب half-up)
* **التاريخ والوقت:** 2026-10-10 00:52 → 01:01 (أوقات الـ commits)
* **الدور المفعل:** غير موثّق لكل commit. المحتوى ops + backend + جزء صغير SPA.
* **الهدف:** إكمال الهوية المركزية (2FA، step-up، استعادة كلمة السر، ترحيل الـ super-admins)، محرك الـ entitlements ودفتر الـ credits، توحيد الصلاحيات، التحقق من الفرع اللي بيبعته العميل، وقواعد اليوم التجاري والتقريب.

> مكتوب لاحقًا (2026-10-11) من `git show --stat` ونتائج CI. ربط البنود مستنتج إلا لو مذكور.

## 0. الـ commits
| commit | الوصف | بنود الخطة (مستنتجة) |
|---|---|---|
| `990a2871` | release workflow + `scripts/ops/deploy.sh` بـ rollback تلقائي، PHP/MySQL 8.4، grants append-only | OPS-3، امتداد OPS-1 (قرار W1 Q6) |
| `ea337f60` | TOTP 2FA إلزامي، step-up، استعادة كلمة السر، أوامر ترحيل/إنشاء super-admin | IDEN-1.12، IDEN-1.6 |
| `5ab7b833` | محرك الـ entitlements، دفتر الـ credits، retention للـ audit المركزي مع إعادة محاولة الكتابة | ENTI-2.2، ENTI-1.10، جزء من IDEN-1.15 |
| `36170742` | زرع كل صلاحية مستخدمة، مطابقة policies/routes/حراس الـ SPA، صلاحية قفل الوردية | QA-2 (+ قرار ترحيل المحل Q10) |
| `2b13685f` | التحقق من `X-Store-Id` مقابل فروع المستخدم، والتعافي في الـ SPA من فرع اتسحب | STOR-1 |
| `315a945c` | `business_day_cutoff`، تقريب half-up، allowlist العملات، وحدات المستأجر، `user_id` صريح على المستندات | امتداد SETG-2، SETG-13، امتداد SETG-1، جزء backend من SETG-10 |
| `7352c8dd` | `docs(ai)`: قاعدة التقريب half-up في `.claude/rules/money-stock-integrity.md` | SETG-13 |
| `9d486424` | إعادة توليد ترجمات الواجهة | — |

## 1. الملفات المعدلة (الأهم)
* `[NEW]` `.github/workflows/release.yml`، `scripts/ops/{deploy,check-env,render-env,verify,sync-central-grants}.sh`، `scripts/ops/lib/mysql-grants.sh`، `scripts/ops/steps/{30-php,40-mysql,41-mysql-users}.sh`، `scripts/ops/tests/*` — `[MODIFIED]` `docs/07-operations/{deploy-runbook,secrets,vps-runbook}.md`.
* `[NEW]` `backend/app/Actions/Central/TwoFactor/*`، `PasswordReset/*`، `SuperAdmins/*`، `backend/app/Http/Middleware/RequireRecentTwoFactor.php`، `backend/app/Providers/CentralFortifyServiceProvider.php`، `backend/config/fortify.php`، `backend/app/Console/Commands/{CreateCentralSuperAdmin,MigrateSuperAdmins}Command.php`، migrations `2026_10_10_100600`، `100610`.
* `[NEW]` `backend/app/Services/Entitlements/TenantEntitlementService.php`، `backend/app/Contracts/TenantFeatureManagerInterface.php`، `backend/app/DTOs/Entitlements/EntitlementsDTO.php`، `backend/app/Services/Billing/CreditBalanceService.php`، `backend/app/Models/TenantCreditLedgerEntry.php`، `backend/app/Jobs/PersistCentralAuditLogJob.php`، migrations `2026_10_10_200700`…`200720`.
* `[NEW]` tenant migrations `2026_10_10_070000_seed_qa2_missing_permissions.php`، `070010_revoke_item_edit_permissions_from_storekeeper.php`؛ `[MODIFIED]` 9 policies، `PermissionsSeeder.php`، `routes/{api,tenant}.php`، `resources/js/router/index.js`؛ `[NEW]` `tests/Feature/Architecture/PermissionsParityTest.php`.
* `[NEW]` `backend/app/Http/Middleware/ResolveActiveStore.php`، `backend/app/Support/ClientStoreGuard.php`، `backend/resources/js/helpers/storeAccessRecovery.js`.
* `[NEW]` `backend/app/Support/Money/Decimal.php`، `backend/resources/js/helpers/decimal.js`؛ `[MODIFIED]` `backend/app/Support/TenantClock.php` وحوالي 60 Action/Controller/Service/Model لاستخدام اليوم التجاري و`user_id` صريح؛ tenant migrations `2026_10_10_310200`، `310210` (`user_id` nullable).
* tests جديدة منها: `CentralTwoFactorApiTest`، `CentralTwoFactorHardeningApiTest`، `CentralPasswordResetApiTest`، `MigrateSuperAdminsCommandTest`، `TenantEntitlementServiceTest`، `CreditBalanceConcurrencyTest`، `CreditLedgerAppendOnlyTest`، `CentralAuditRetentionTest`، `ClientStoreAccessGuardTest`، `ResolveActiveStoreTest`، `StoreHeaderTrustTest`، `BusinessDayCutoffTest`، `CheckoutHalfUpRoundingTest`.

## 2. القرارات التقنية
* **النشر:** workflow منفصل للـ release ولا يلمس `deploy.yml` بتاع `main`؛ rollback تلقائي لو فشل الـ health check بعد التبديل.
* **الـ audit والـ credits append-only:** مستخدم `app` في production مالوش `UPDATE`/`DELETE` على جداول الدفاتر (`CENTRAL_APPEND_ONLY_TABLES`، موثّق في `vps-runbook.md`).
* **step-up:** العمليات الخطرة تتطلب 2FA حديث على الـ token (`two_factor_verified_at`)؛ التطبيق على كل الـ writes الخطرة اكتمل في الدفعة 3.
* **الفرع:** الـ `X-Store-Id` بقى يتحقق منه مقابل `user->stores()` بدل الثقة فيه؛ الـ SPA بيتعافى لو الفرع اتسحب من المستخدم.
* **المال:** تقريب half-up موحّد عبر `Decimal` (PHP) و`decimal.js` (واجهة). أثر مالي: إجماليات الـ checkout بتتقرب half-up على خانات العملة.
* **اليوم التجاري:** `business_day_cutoff` بيحدد تاريخ المستند (مبيعات بعد منتصف الليل تتحسب لليوم السابق لو قبل الـ cutoff).

## 3. التحقق والاختبار
* **CI على `9d486424` (push، 2026-10-09 22:01 UTC):**
  * `php` (Pint + Larastan + PHPUnit sqlite): أخضر — **1981 passed، 1 skipped** (24555 assertion).
  * `frontend`: أخضر (JS unit 137/137). `desktop`: أخضر. `gitleaks`: أخضر.
  * `mysql`: **فشل** — 2 tests (`CentralUserModelTest`، `BillingInvoicesSchemaTest`: rollback الـ migrations فشل لأن `billing_invoices` عليه FK من جدول تاني، `SQLSTATE[HY000]` 3730).
* الدفعة **لم تكن خضراء على CI**؛ `mysql` بقى أخضر في run الدفعة 3 (`dd791ef9`)، والـ commit المحدد للإصلاح **غير موثّق**.
* `scripts/ops/tests/run-tests.sh`: بيتشغل في job الـ `gitleaks` (38/38 في runs لاحقة)؛ نتيجته على هذا الـ commit تحديدًا غير متحققة.
* الـ deploy الحقيقي على VPS: **لم يُنفَّذ** (مفيش VPS جديد بعد).

## 4. ملاحظات / ديون تقنية
* IDEN-1.15: الـ retention وإعادة المحاولة اتعملوا؛ هل النقل الكامل لـ `spatie/laravel-activitylog` اكتمل **غير متحقق** من هذا السجل.
* `user_id` بقى nullable على المستندات (actor صريح)؛ أي تقرير بيفترض وجوده لازم يتعامل مع `NULL`.
