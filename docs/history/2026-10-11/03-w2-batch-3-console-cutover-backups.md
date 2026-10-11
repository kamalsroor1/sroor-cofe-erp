# سجل تعديل: Phase 1 — W2 الدفعة 3 (قطع لوحة المنصة عن المسار القديم، النسخ الاحتياطي المشفّر، الـ admin host، واجهة الـ console)
* **التاريخ والوقت:** 2026-10-10 04:24 (الـ commits) + 09:38 (إصلاح CI)
* **الدور المفعل:** غير موثّق لكل commit. الـ runbooks بتشير لـ lanes داخلية (`lane 3H`، `lane 3L` في `docs/07-operations/local-testing.md`).
* **الهدف:** إيقاف مسار الـ super-admin القديم (web guard + master key)، step-up على كل write خطر، audit لكل write في لوحة المنصة، backups مشفّرة لكل مستأجر، وواجهة console مبنية على `centralApi`.

## 0. الـ commits
| commit | الوصف | بنود الخطة (مستنتجة) |
|---|---|---|
| `d20a19cf` | عُدّة اختبار محلي بالدومينات (`scripts/local/*`)، مفاتيح env مركزية إلزامية، توثيق الـ backup | OPS-1 امتداد، توثيق OPS-5 |
| `64a21979` | `docs(ai)`: `centralApi` إلزامي وممنوع `v-html` ببيانات المستأجر في شاشات الـ console (`.claude/rules/frontend-vue.md` + `AGENTS.md`) | — |
| `a3937899` | console cutover hardening، backups المستأجرين، تغطية الـ audit، hosts محلية | IDEN-1.4، IDEN-1.8، IDEN-1.11، OPS-5، امتداد IDEN-4.6، جزء من BRND-2؛ وبوابات Horizon/Telescope (IDEN-1.7، مخطط لـ W3) |
| `8e620016` | مودال حالة المستأجر على قواعد الـ lifecycle، الـ central hosts من meta السيرفر، دخول E2E على مستأجر `demo` | IDEN-1.9، واجهة IDEN-3.3 |
| `dd791ef9` | إعادة توليد ترجمات الواجهة | — |
| `cbd6589e` | `fix(ci)`: test بوابة Horizon بدون redis، وإعادة تسمية ثابت bearer وهمي في E2E | إصلاح CI |

## 1. الملفات المعدلة (الأهم)
* `[MODIFIED]` `backend/app/Actions/Auth/{ApiLoginAction,LoginAction,SuperAdminLoginAction}.php` — شيل fallback الـ master-key في دخول المستأجر؛ صفوف الـ super-admin القديمة المرحَّلة بتفقد أدوارها وتتعطل.
* `[MODIFIED]` `backend/app/Policies/TenantPolicy.php` (مركزي فقط)، `backend/app/Http/Middleware/{EnsureCentralContext,ResolveApiTenancy,StoreScope,ApiTokenAuth}.php`، `[NEW]` `AdminSecurityHeaders.php`، `backend/app/Support/PlatformHosts.php`.
* `[MODIFIED]` `backend/app/Providers/{Horizon,Telescope}ServiceProvider.php` — guard `central_web` + صلاحية المراقبة، ومثبّتة على الـ admin host؛ `[NEW]` `IssueTelescopeLinkAction`/`ConsumeTelescopeLinkAction` مع audit.
* `[NEW]` `backend/app/Actions/SuperAdmin/{UpdatePlatformSettings,GetPlatformSystemUnits,UpdatePlatformSystemUnits}Action.php` — إعدادات المنصة في `platform_settings` عبر Actions.
* `[NEW]` `backend/app/Rules/TenantDatabaseName.php` — اسم DB المستأجر: prefix المستأجر، مش المركزي، unique.
* `[NEW]` `TenantRateLimitOverride` (model + migration `2026_10_10_100710` + controller + Action) — رفع مؤقت لحدود مستأجر (قرار W1 Q1).
* `[MODIFIED]` `backend/app/Console/Commands/{BackupTenants,RestoreTenantBackup}Command.php`، `[NEW]` `backend/app/Models/TenantBackup.php`، migration `2026_10_10_500200_create_tenant_backups_table.php`، `backend/app/Health/Checks/{BackupFreshness,BackupArchivePassword,DiskSpace}Check.php`، `backend/config/backup.php`.
* `[NEW]` `backend/app/Support/Media/{ImageSanitizer,SanitizedImage,BrandAssetSpec}.php` — تجهيز للوجوهات في الدفعة 4 (magic bytes، re-encode، بدون SVG).
* `[NEW]` `backend/resources/js/Services/centralApi.js`، `stores/centralAuth.js`، `helpers/platformHosts.js`، `Composables/useTenantStatusForm.js`، `Components/SuperAdmin/{Central*,StepUpPrompt,TenantStatusModal}.vue`، views الـ forgot/reset password.
* `[NEW]` `scripts/local/{add-hosts.ps1,setup-local.ps1,reset-local.ps1,provision-local-tenant.php,hosts-entries.txt}`، `backend/.env.local-test.example`، `[MODIFIED]` `docs/07-operations/{backup-restore,deploy-runbook,local-testing,secrets,vps-runbook}.md`؛ `[DELETED]` من التتبع `backend/public/hot`.
* `[NEW]` `e2e/utils/e2e-user.js` + specs الـ super-admin (معلّمة `fixme` لحد ما الـ admin host يتاح في E2E).

## 2. القرارات التقنية
* **قطع المسار القديم نهائيًا** بدل إبقائه كـ fallback: أي حساب super-admin قديم في جدول `users` المستأجر بيتعطل بعد الترحيل.
* **Step-up 2FA** على: حالة المستأجر/features/units، الباقات، إعدادات المنصة، إصدارات التطبيقات. الدخول المركزي محدود per email+IP.
* **مفيش fallback لـ store 1:** `ActiveStore` الافتراضي أو 403 `store_access_denied`؛ `StoreScope` لا يعمل خارج سياق المستأجر.
* **Backups:** أرشيفات مشفّرة لـ Google Drive؛ الـ manifest محسوب من الـ dump؛ كلمة سر الأرشيف إلزامية خارج local/testing؛ الاستعادة ترفض أرشيف غير متحقق أو غير مشفّر، وتحتاج حساب restore مخصص في production.
* الـ central hosts بتتقري من السيرفر (meta tags) مش متكتبة في الـ SPA.

## 3. التحقق والاختبار
* **CI على `dd791ef9`:** **فشل** — `php`: 1 failed (`HorizonCentralWebGuardTest`، كان محتاج redis)، 2338 passed، 1 skipped؛ `gitleaks`: ثابت bearer وهمي في `e2e/flows/central-login.spec.js` اتمسك بقاعدة `sroor-hardcoded-password` (قيمة test). `mysql`، `frontend`، `desktop`: أخضر.
* **CI على `cbd6589e` (بعد الإصلاح):** كل الـ jobs خضراء. `php`: **2339 passed، 1 skipped = 2340** (28415 assertion). JS unit 150/150. `mysql` أخضر (أول مرة في W2).
* Playwright: specs الـ super-admin معلّمة `fixme`؛ تشغيل E2E فعلي لهذه الدفعة **غير موثّق**.
* Google Drive الحقيقي و restore drill: **لم يُنفَّذا** (`backup-restore.md` §2 و§6).

## 4. ملاحظات / ديون تقنية
* بوابات Horizon/Telescope (IDEN-1.7) اتعملت هنا رغم إنها مخططة لـ W3 — لازم تتعلّم في حالة الخطة.
* `DatabaseSeeder` لسه بيعمل super-admin في `users` (المسار القديم)؛ الـ setup المحلي بيستخدم `central:create-super-admin` بدلًا منه (`local-testing.md` §7).
