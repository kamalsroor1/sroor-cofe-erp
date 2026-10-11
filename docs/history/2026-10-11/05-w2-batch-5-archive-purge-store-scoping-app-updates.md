# سجل تعديل: Phase 1 — W2 الدفعة 5 (أرشفة ومسح المستأجر، عزل الفروع، تحقق إصدارات التطبيقات، تحديثات Android/Desktop، دمج PR #4 و#7)
* **التاريخ والوقت:** 2026-10-11 11:38 → 11:39 (الـ commits والدمج) + 14:55 (إعادة توليد الترجمات)
* **الدور المفعل:** غير موثّق لكل commit. المحتوى ops + backend + SPA + desktop.
* **الهدف:** حذف آمن للمستأجر (أرشفة بنسخة نهائية متحقق منها + مسح يدوي من الـ CLI)، تطبيق الفرع النشط على كل شاشات المال والمخزون، رفض أي إصدار تطبيق بدون checksum، تحديث حقيقي وآمن للتطبيقين، وإصلاح BUG-01 (انظر [06-bug-01-shift-cash-double-count.md](06-bug-01-shift-cash-double-count.md)).

## 0. الـ commits
| commit | الوصف | بنود الخطة |
|---|---|---|
| `f566e082` | نقل ملفات الإصدارات على مرحلتين في الـ deploy، منع تقديم `.apk/.exe/.ipa` من `public/`، runbook الأرشفة/المسح | APP-3 امتداد (ops)، OPS-9 (توثيق) |
| `7a8cee19` | أرشفة ومسح المستأجر، عزل الفروع، تحقق ملفات الإصدار، إصلاح كاش الوردية | **OPS-9، STOR-2، APP-3** (مذكورة)، BUG-01، `settings.manage` |
| `b726dcfa` | تحديثات تطبيق متحقق منها، صفحة تحميل يدوي، أمان تحديث الديسكتوب، واجهة الأرشفة | APP-3 امتداد، APP-7، APP-5 امتداد (pin الدرج)، OFFL-2 (spec شريط عدم الاتصال) — من `docs/07-operations/app-update-checklists.md` |
| `6905d1bd` | إعادة توليد ترجمات الواجهة | — |
| `f4c1c535` | **Merge PR #4** (`feature/ux-fixes-antigravity`): إصلاحات مراجعة الـ UX دفعة 1 و2 | من Antigravity — [07](07-antigravity-collaboration-setup.md) |
| `793c8896` | **Merge PR #7** (`feature/settings-ui-antigravity`): shell الإعدادات وتبويب الـ branding والوحدات من السيرفر | SETG-5، SETG-10، BRND-3/5 (واجهة) — من Antigravity |
| `0f55e048` | إعادة توليد الترجمات بعد الدمج | — |

## 1. الملفات المعدلة (الأهم)
* **OPS-9:** `[NEW]` `backend/app/Actions/Tenants/{RequestTenantArchive,ArchiveTenant,UnarchiveTenant,PurgeTenant,TakeTenantBackup,VerifyTenantBackup}Action.php`، `backend/app/Jobs/ArchiveTenantJob.php`، `backend/app/Console/Commands/{ArchiveTenant,PurgeTenant}Command.php`، `backend/app/Support/Tenancy/PurgeEligibility.php`، `backend/app/Http/Controllers/Api/Central/TenantArchiveController.php`، migrations `2026_10_10_500300_add_archive_and_purge_columns_to_tenants_table.php`، `500310_add_kind_to_tenant_backups_table.php`؛ `[MODIFIED]` `DeleteTenantAction.php` (لم يعد يمسح الـ DB).
* **STOR-2:** `[MODIFIED]` `ResolveActiveStore.php`، `ActiveStore.php`، controllers الـ POS/الفواتير/الورديات/اليومية/التقارير/الداشبورد/الخزينة/التحويلات/المرتجعات/المشتريات/المصروفات/المدفوعات، و7 policies؛ `[NEW]` `lang/{ar,en}/store_scope.php`.
* **APP-3:** `[NEW]` `backend/app/Support/AppReleases/{ReleaseBinaryInspector,ReleaseBinaryReport,ReleaseFileLocator,SemverCode}.php`، `backend/app/Console/Commands/RelocateAppReleasesCommand.php`، migration `2026_10_10_500400_add_release_integrity_columns_to_app_versions_table.php`؛ `[MODIFIED]` `CheckAppUpdateAction.php`، `CreateAppVersionAction.php`، `DownloadLatestApkAction.php`، `config/rate_limits.php`، `backend/public/.htaccess`، `scripts/ops/templates/nginx-site.conf.tmpl`، `scripts/ops/deploy.sh`.
* **BUG-01:** `[NEW]` `backend/app/Services/DailyCashFlowService.php`، tenant migration `2026_10_11_100000_add_store_id_to_payments_table.php`؛ `[MODIFIED]` `ShiftService.php`، `PaymentService.php`، `Payment.php` — التفاصيل في سجل 06.
* **SPA:** `[NEW]` `views/AppDownload/AppDownloadView.vue`، `Components/AppDownload/AppDownloadCard.vue`، `Components/SuperAdmin/TenantArchivePanel.vue`، `Composables/appUpdate/createAppUpdater.js`، `helpers/appUpdate.js`؛ `[MODIFIED]` `AppUpdateModal.vue`، `UploadApkModal.vue`، شاشات الـ tenants.
* **Desktop:** `[NEW]` `desktop/src/updater/{authenticode,updateState,updateVerify,versionCode}.js`، `desktop/src/config/updatePolicy.js`، `desktop/src/hardware/drawerKick.js`؛ `[MODIFIED]` `desktop/main.js`، `preload.js`، `nativeUpdater.js`، `cashDrawer.js` + 6 ملفات test.
* **E2E:** `[NEW]` `e2e/flows/offline-banner.spec.js`، `e2e/utils/offline-helper.js`.
* **Docs:** `[MODIFIED]` `docs/07-operations/{backup-restore,deploy-runbook,vps-runbook,android-update-checklist}.md`، `[NEW]` `docs/07-operations/app-update-checklists.md`.

## 2. القرارات التقنية
* **الحذف = أرشفة:** `DELETE /super-admin/tenants/{id}` بيعمل queue لأرشفة تاخد backup نهائي وتتحقق منه الأول؛ كاتب الحالة يرفض `archived` بدون backup نهائي حديث متحقق منه؛ إلغاء الأرشفة يرجّع الحالة السابقة.
* **المسح النهائي CLI فقط:** شروط الأهلية (مؤرشف 30 يوم، أو موقوف 90 يوم، وأبدًا لـ `violation`)، backup جديد اتعمله restore drill، كتابة الـ slug + تأكيد، قابل للاستكمال، وصف tombstone. لا يُمسح إلا اسم DB المستأجر نفسه. أرشيفات final/pre-purge لا تُحذف بالـ retention. `NoAutomaticPurgeTest` يمنع أي مسح تلقائي.
* **الفرع:** سجلات فرع آخر ترجع 404 بدون أي تغيير؛ الكتابة على مستند واحد تتطلب فرعًا محددًا؛ الفروع المغلقة ترفض الكتابة؛ فرع الـ checkout في الـ POS يأتي من الفرع النشط فقط.
* **الإصدارات:** الرفع يُتحقق منه كـ APK حقيقي (signing block) أو EXE (PE)؛ الملفات على قرص خاص؛ `check-update` لا يعرض إصدارًا بدون checksum؛ تحميل بإصدار محدد مع header للـ checksum و rate limit مخصص. النقل في الـ deploy على مرحلتين: نسخ + تحقق قبل التبديل، وحذف النسخ العامة القديمة بعد نجاح الـ health check فقط.
* **Android:** checksum إجباري، التحديث الاختياري يظهر مرة لكل تشغيل، الإجباري لا يُغلق، صفحة `/download` يدوية كبديل.
* **Desktop:** version code حقيقي من semver، فحص الحجم والـ checksum، سياسة Authenticode (**مُطفأة** لحد وجود شهادة — قرار [CTO-2026-10-08b])، حد لمحاولات التحديث، وعرض rollback لو البرنامج وقع عند الإقلاع. pin درج الكاش 2 أو 5 لكل جهاز.
* `/settings` و`POST /settings` بقوا يتطلبوا `settings.manage` بدل `roles.manage` (قرار مذكور في مراجعة PR #7).

## 3. التحقق والاختبار
* **CI على `0f55e048` (push بعد الدمج، 2026-10-11 11:56 UTC):** كل الـ jobs الخمسة خضراء.
  * `php` (Pint + Larastan + PHPUnit sqlite): **2831 passed، 1 skipped = 2832** (34282 assertion).
  * `mysql` (MySQL 8.4، group `mysql`): أخضر. `gitleaks`: لا تسريبات. `frontend`: JS unit 193/193 + build. `desktop`: ESLint + Prettier.
* لم يتم run منفصل على `b726dcfa` قبل الدمج على GitHub (أول run بعد الدفعة 4 هو `0f55e048`)؛ النتيجة أعلاه تغطي الدفعة 5 + PR #4 + PR #7 معًا.
* `desktop` npm test (`versionCode`، `authenticode`، `updateState`، `updater`، `drawerKick`، `settingsSanitizer`): الـ CI job لا يشغّلها؛ تشغيلها محليًا **غير موثّق**.
* Playwright (`offline-banner.spec.js` وغيره): **غير موثّق**.
* **لم يُختبر:** تحديث فعلي على جهاز Android أو Windows، فتح الدرج على طابعة حقيقية، `tenants:purge` على DB حقيقية، الأرشفة مع Google Drive حقيقي.

## 4. ملاحظات / ديون تقنية
* **STOR-2 مخطط لـ W3** واتنفّذ هنا مبكرًا؛ الواجهة (STOR-4) لسه issue مفتوحة (#10).
* قبل أول deploy فيه migration `500400`: فحص تكرار `(platform, version_code)` في `app_versions` (موثّق في `deploy-runbook.md` 1d). ونافذة الـ deploy مع أعمدة `tenants` الجديدة خطر موثّق (1c).
* PR #9 (E2E + docs من Antigravity) لسه draft بتغييرات مطلوبة، ومش مدموج.
* بوابة SEC-1 (نهاية W2) **لم تُنفَّذ** حسب الأدلة المتاحة.
