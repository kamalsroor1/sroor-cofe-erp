# سجل تعديل: Phase 1 — W2 الدفعة 1 (المصادقة المركزية، دورة حياة المستأجر، كتالوج الباقات، Quick keys، ترحيل الـ suite جزء A)
* **التاريخ والوقت:** 2026-10-09 13:08 → 16:09 (أوقات الـ commits)
* **الدور المفعل:** غير موثّق لكل commit (الـ commits لا تذكر الـ agent). المحتوى backend + tests.
* **الهدف:** أول دفعة من مهام W2 في [phase-1-plan.md](../../05-planning/phase-1-plan.md) §3.2: صلاحيات ومصادقة مركزية بجانب المسار القديم، أعمدة ودورة حياة حالة المستأجر، كتالوج الباقات المعتمد، quick keys لكل فرع، وبداية ترحيل الـ Feature suite إلى الـ tenant harness.

> **السجل مكتوب لاحقًا (2026-10-11)** من `git log` / `git show --stat` ونتائج CI على GitHub. ربط كل commit ببنود الخطة مستنتج من محتوى الـ commit ووصف البند في §4، إلا لو الـ commit ذكر الـ ID صراحة.

## 0. الـ commits
| commit | الوصف | بنود الخطة (مستنتجة) |
|---|---|---|
| `b0d8888c` | pre-seed أحداث `CentralAuditEvent` لـ W2 | تجهيز لـ IDEN-1.x |
| `b4525de8` | `docs(ai)`: مزامنة قائمة الـ agents والـ skills، وحذف skill الـ Livewire | — |
| `9269f470` | صلاحيات مركزية + API دخول الـ super-admin بجانب المسار القديم | IDEN-1.2، IDEN-1.3، بداية IDEN-1.4 (`routes/central.php`) |
| `1586ace8` | أعمدة دورة حياة المستأجر، انتقالات الحالة، أخطاء الـ entitlements | IDEN-3.2، IDEN-3.3، ENTI-2.1 |
| `67cfe41c` | كتالوج الباقات المعتمد، `gateway_reference` لكل بوابة، FK للـ add-on مقيّد بالمستأجر | ENTI-1.8، ENTI-1.9، امتداد ENTI-1.x (قرارات W1 Q3/Q4) |
| `86c449b5` | API الـ quick keys لكل فرع + إضافتها لـ bootstrap الـ POS | POSB-6 |
| `3566bf3e` | ترحيل suites «chunk A» إلى الـ tenant harness مع offsets للـ IDs لكل مستأجر | QA-4 (جزء A)، امتداد IDEN-4.8 |
| `450cd1f1` | إعادة توليد ترجمات الواجهة | — |

## 1. الملفات المعدلة (الأهم)
* `[NEW]` `backend/app/Enums/CentralPermission.php`، `backend/app/Http/Middleware/AuthenticateCentral.php`، `backend/app/Http/Controllers/Api/Central/CentralAuthController.php`، `backend/app/Actions/Central/{Login,Logout}CentralUserAction.php`، `GetCentralProfileAction.php`، `backend/app/DTOs/Central/CentralLogin{DTO,Result}.php`، `backend/app/Http/Requests/Central/CentralLoginRequest.php`، `backend/app/Http/Resources/Central/*` — مصادقة الـ `CentralUser` بـ Bearer على guard `central`.
* `[NEW]` `backend/routes/central.php` — routes المنصة بجانب المسار القديم (المسار القديم اتشال في الدفعة 3).
* `[MODIFIED]` `backend/app/Support/PlatformSuperAdmin.php`، `backend/database/seeders/CentralPermissionsSeeder.php`، `backend/bootstrap/app.php`.
* `[NEW]` `backend/app/Actions/Tenants/TransitionTenantStatusAction.php`، `RevokeTenantTokensAction.php`، `backend/app/Models/TenantLifecycleEvent.php`، `backend/app/Support/Tenancy/{TenantStatusTransition,TenantStatusTransitioned,TenantSuspensionReason,EndsTenantImpersonationSessions}.php`، `backend/app/Exceptions/TenantLifecycleException.php`.
* `[NEW]` migrations مركزية `2026_10_10_110000_update_tenants_table_for_lifecycle.php`، `2026_10_10_110010_create_tenant_lifecycle_events_table.php`.
* `[NEW]` `backend/app/Exceptions/Entitlements/{PlanLimitExceeded,FeatureUnavailable}Exception.php` — 403 بـ `error_code`.
* `[NEW]` `backend/database/seeders/Catalog/{PlanCatalog,AddonCatalog,FeatureCatalog,CatalogWriter}.php` + migrations `2026_10_10_200540`…`200610` — كتالوج معتمد، seeder لا يدهس تعديلات الـ super-admin.
* `[NEW]` `backend/app/Models/PosQuickKey.php`، `PosQuickKeyController.php`، `{Get,Replace}PosQuickKeysAction.php`، `QuickKeyDTO.php`، migration tenant `2026_10_10_600100_create_pos_quick_keys_table.php`.
* `[MODIFIED]` `backend/tests/Concerns/InteractsWithTenants.php` + 23 ملف test اتحولوا للـ harness.
* `[NEW]` tests: `CentralAuthApiTest`، `CentralTokenIsolationApiTest`، `TransitionTenantStatusActionTest`، `TenantLifecycleSchemaMigrationTest`، `PlansAndFeaturesSeederTest`، `FounderPricingMysqlConcurrencyTest`، `SubscriptionAddonTenantForeignKeyTest`، `PosQuickKeysApiTest`، `PosBootstrapQueryCountTest`، `TenantHarnessSmokeTest`، وغيرها.

## 2. القرارات التقنية
* المصادقة المركزية اتعملت **بجانب** المسار القديم (مش بدله) عشان التحويل يتم على مراحل؛ القطع النهائي في الدفعة 3.
* تغيير حالة المستأجر بقى state machine واحد (`TransitionTenantStatusAction`) مع سجل أحداث، وإلغاء tokens المستأجر عند الإيقاف.
* الكتالوج في classes بدل seeder مكتوب يدوي، وبيكمّل المفاتيح الناقصة بس.
* `gateway_reference` بقى unique لكل بوابة، والـ add-on بقى مربوط بنفس مستأجر الاشتراك عن طريق FK مركّب.

## 3. التحقق والاختبار
* **CI على `450cd1f1` (push، 2026-10-09):**
  * `php` (Pint + Larastan + PHPUnit sqlite): أخضر — **1612 passed، 1 skipped** (22349 assertion).
  * `frontend`: أخضر (JS unit tests 78/78). `desktop`: أخضر.
  * `mysql`: **فشل** — 3 tests في `MysqlConcurrencyTest` (`SQLSTATE[23000]` 1452، FK).
  * `gitleaks`: **فشل** — قاعدة `sroor-hardcoded-password` على ثابت داخل `CentralAuthApiTest.php` (قيمة test، مش secret حقيقي).
* الدفعة **لم تكن خضراء على CI**. `gitleaks` بقى أخضر في run الدفعة 2، و`mysql` بقى أخضر في run الدفعة 3؛ الـ commit المحدد اللي صلّح كل واحد **غير موثّق**.
* تشغيل محلي للـ suite، أو مراجعة `code-reviewer`/`security-auditor` لهذه الدفعة: **غير موثّق**.

## 4. ملاحظات / ديون تقنية
* المسار القديم للـ super-admin فضل شغال لحد الدفعة 3.
* QA-4 هنا جزء A بس؛ باقي الترحيل في دفعات لاحقة (الـ `HarnessAdoptionTest` في الدفعة 4).
