# سجل تعديل: Phase 1 — W2 الدفعة 4 (provisioning في الـ queue، branding المستأجر والمنصة، formatters والوحدات من الإعدادات)
* **التاريخ والوقت:** 2026-10-10 10:53 → 11:20 (أوقات الـ commits)
* **الدور المفعل:** غير موثّق لكل commit. المحتوى ops + backend + SPA.
* **الهدف:** إنشاء DB المستأجر بـ job في الـ queue بدل الطلب المتزامن، branding حقيقي للمستأجر (لوجو فاتح/غامق، رأس/تذييل الإيصال، لون) وللمنصة، وتوحيد عرض التواريخ/الأرقام/الفلوس/الوحدات في الواجهة.

## 0. الـ commits
| commit | الوصف | بنود الخطة |
|---|---|---|
| `c65f8964` | queue الـ provisioning، secret حساب الـ provisioner، ملاحظات runbook لتنظيف البيانات، عقد backend لتبويب الـ branding | OPS-2 (ops)، تجهيز SETG-5/BRND-5 للواجهة |
| `a754f58e` | provisioning في الـ queue + branding المستأجر والمنصة | **OPS-2، BRND-2، BRND-3، BRND-5** (مذكورة في الـ commit) |
| `3ae75611` | formatters حسب اللغة، عرض الفلوس بخانات العملة، الوحدات من الإعدادات | SETG-6، SETG-13 (واجهة)، SETG-10 (واجهة) — مستنتجة |
| `edde1ca6` | إعادة توليد ترجمات الواجهة | — |
| `0c9dbac8` | `fix(ci)`: إعادة تسمية ثوابت fixtures في tests الـ provisioning اتمسكت من gitleaks | إصلاح CI |

## 1. الملفات المعدلة (الأهم)
* `[NEW]` `backend/app/Jobs/ProvisionTenantJob.php`، `backend/app/Actions/Tenants/{ProvisionTenant,RetryTenantProvisioning}Action.php`، `backend/app/Services/Tenancy/ProvisionerMySQLDatabaseManager.php`، `backend/app/Enums/TenantProvisioningStatus.php`، `backend/app/Support/Tenancy/{ProvisioningErrorCode,ProvisioningFailure,TenantHostResolver}.php`، `backend/app/Http/Controllers/Api/Central/TenantProvisioningController.php`.
* `[NEW]` `backend/app/Console/Tenancy/{Migrate,Rollback,Seed,RunFor}ProvisionedTenants.php` + `DefaultsToProvisionedTenants.php` — أوامر `tenants:*` تشتغل على المستأجرين الجاهزين افتراضيًا.
* `[NEW]` migrations مركزية `2026_10_10_500000_add_provisioning_columns_to_tenants_table.php`، `500100_clean_tenant_data_virtual_column_duplicates.php`، `400200_move_legacy_global_system_units_to_platform_settings.php`؛ tenant `2026_10_10_400000_create_tenant_brand_profiles_table.php`.
* `[NEW]` `backend/app/Http/Controllers/Api/{BrandingController,TenantLogoController,TenantBrandAssetController}.php`، `backend/app/Http/Controllers/Api/Central/{PlatformSettingsController,PlatformUnitsController}.php`، `backend/app/Services/Branding/TenantBranding.php`، `backend/app/Models/TenantBrandProfile.php`، Actions رفع/حذف أصول المستأجر والمنصة.
* `[MODIFIED]` `backend/resources/views/layouts/{print-a4,print-thermal,print-daily-journal-a4}.blade.php` — الطباعة من branding المستأجر.
* `[NEW]` `backend/resources/js/helpers/formatters.js`، `Composables/{useFormatters,useMoney,useUnits}.js`، `[MODIFIED]` `eslint.config.js` (قاعدة تمنع `toLocale*('ar')` الخام)، و14 ملف Vue/Composable.
* `[NEW]` tests منها: `ProvisionTenantJobTest`، `ProvisionWithNonRootAccountTest`، `ProvisioningReadyGuardTest`، `TenantDatabasePasswordEncryptionTest`، `TenantVirtualColumnTest`، `BrandingApiTest`، `TenantLogoRouteTest`، `TenantPrintBrandingTest`، `PlatformSettingsApiTest`، `HarnessAdoptionTest`، وJS: `formatters`، `money-display`، `no-hardcoded-units`، `no-raw-ar-locale`.
* `[MODIFIED]` `.github/workflows/release.yml`، `scripts/ops/templates/production.env.example`، `docs/07-operations/{deploy-runbook,vps-runbook}.md`.

## 2. القرارات التقنية
* **Provisioning بعد الـ commit:** صف المستأجر والدومينات والاشتراك والـ audit في transaction مركزية واحدة، والـ DB بيتعمل في `ProvisionTenantJob` بعد الـ commit (unique، encrypted، idempotent، retries بـ backoff، فك قفل الـ overlap عند الفشل). البديل المرفوض: إنشاء الـ DB جوه الطلب (لا يمكن rollback لـ `CREATE DATABASE`).
* **حساب provisioner منفصل** بمستخدم MySQL لكل مستأجر (grants مـ escaped)؛ الـ DB أو المستخدم بيتعاد استخدامه أو يتمسح **فقط** لو هو اللي الـ job ده عمله، بالاسم.
* **الأسرار:** كلمات سر DB المستأجر مشفّرة at rest (الصفوف القديمة plaintext لسه مقروءة)؛ رسائل الفشل لا تنسخ أي secret.
* **مستأجر غير جاهز:** 503 على الطلبات، و409 من الـ resolver وعمليات الـ super-admin؛ endpoint retry للحالات `failed` أو المتعطلة.
* **اللوجوهات:** عبر medialibrary لكل مستأجر، رفع مُعقّم (`ImageSanitizer`)، route عام للوجو على host المستأجر فقط، و`/branding` عام بـ allowlist للحقول.
* **العرض:** أرقام لاتينية مع `ar-EG` / `en-GB` وتوقيت المستأجر؛ الفلوس تتقرب half-up على `system.currency_decimals` عبر `decimal.js`؛ الوحدات من `system.inventory_units` مع إبقاء وحدة الصنف القديمة عند التعديل.

## 3. التحقق والاختبار
* **CI على `edde1ca6`:** **فشل** في `gitleaks` بس (ثابتين في `ProvisionTenantJobTest.php` و`TenantDatabasePasswordEncryptionTest.php` اتمسكوا بقاعدة `sroor-hardcoded-password`، قيم test). `php` كان أخضر: 2540 passed، 1 skipped.
* **CI على `0c9dbac8` (بعد الإصلاح):** كل الـ jobs خضراء. `php`: **2540 passed، 1 skipped = 2541** (30135 assertion). JS unit 172/172. `mysql` أخضر.
* Provisioning على MySQL حقيقي بحساب provisioner غير root خارج CI: **غير موثّق**.

## 4. ملاحظات / ديون تقنية
* migration `500100` بتنضف `tenants.data`؛ الـ runbook بيطلب **backup لجدول `tenants` قبلها** في production.
* production لازم يكون فيه حساب الـ provisioner ومستخدمي DB لكل مستأجر و queue `provisioning` مخصص (Horizon supervisor) — موثّق في `vps-runbook.md`، ولم يُنفَّذ على سيرفر.
* محليًا الـ job بيشتغل inline (`sync`).
