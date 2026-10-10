# Backup & restore runbook — النسخ الاحتياطي والاستعادة (OPS-5 / OPS-7)

> المهمة: Phase 1 · W2 · **OPS-5** (+ فحوص الـ health من OPS-7) في `docs/05-planning/phase-1-plan.md` §4.
> قرارات الـ CTO: **D3** Google Drive بـ OAuth من حساب الـ CTO (مجلد مخصص)، **D4** `BACKUP_ARCHIVE_PASSWORD` فاضي في الإنتاج = النسخ يرفض يشتغل + الـ deploy preflight يفشل + الـ health أحمر، والتطبيق نفسه يقوم عادي.
> الحالة: الكود والاختبارات جاهزين. **الإعداد على Google Cloud وأول drill حقيقي لم يُنفَّذا** (§2 و§6).

---

## 1. إيه اللي بيتعمل

| البند | التفاصيل |
|---|---|
| الأمر | `php artisan backup:tenants` (`App\Console\Commands\BackupTenantsCommand`) |
| بيعمل backup لـ | الـ DB المركزية (`central`) + **كل مستأجر حالته مش `archived`**. مستأجر `archived` بياخد backup بس لو اتطلب صراحةً بـ `--tenant=<id>` (الـ backup النهائي بتاع OPS-9) |
| الـ dump | MySQL: `mysqldump --single-transaction --skip-lock-tables --quick --no-tablespaces --hex-blob` عن طريق spatie/db-dumper، بحساب `sroor_backup` اللي صلاحياته قراءة بس (`DB_BACKUP_USERNAME`/`DB_BACKUP_PASSWORD`، الخيارات في `config/database.php` → `dump`). sqlite (dev/tests): `VACUUM INTO` |
| السياق | الـ dump بتاع كل مستأجر بيتعمل جوه `$tenant->run()` والـ tenancy بتتقفل في `finally`. الرفع والسجل من السياق المركزي بس |
| الأرشيف | zip واحد لكل DB: `database.sql` (أو `.sqlite`) + `manifest.json` (عدد صفوف كل جدول وقت الـ backup). **كل entry مشفّر AES-256** بـ `BACKUP_ARCHIVE_PASSWORD`، وبيتفتح تاني محليًا ويتقري لآخره (CRC) قبل الرفع |
| المكان | `<disk>:<BACKUP_PATH_PREFIX>/<subject>/<subject>-YYYY-mm-dd-HH-ii-ss.zip` (مثال: `sroor-backups/shop1/shop1-2026-10-11-01-30-02.zip`) |
| الـ disk | `google` على الـ VPS (`BACKUP_DISKS=google`)، `local` في dev/staging |
| السجل | جدول مركزي `tenant_backups` (`tenant_id` NULL = المركزي، `disk`، `path`، `sha256`، `size_bytes`، `created_at`، `verified_at`). `verified_at` بيتحط بس بعد ما النسخة المرفوعة تتنزّل تاني والـ sha256 يطابق |
| الاحتفاظ | **7 يومي / 4 أسبوعي / 3 شهري** لكل DB ولكل disk (أحدث نسخة من كل أسبوع ثم من كل شهر)، أحدث نسخة عمرها ما بتتمسح، وصف السجل بيتمسح مع الملف. بيتطبق بس على اللي نجح في نفس التشغيلة |
| الجدولة | `01:30` يوميًا (`routes/console.php`، `withoutOverlapping`) |
| ملفات المستخدمين | `backup:run --only-files` (spatie، `storage/` من غير `.env` والمفاتيح، `tests/Unit/BackupConfigTest.php`) الساعة `02:30` + `backup:clean` الساعة `03:00`، بنفس الباسورد ونفس الـ disks |
| الفشل | أي DB تفشل **ماتوقفش الباقي**. في الآخر: exit 1، سطر `error` في الـ log لكل DB، وإيميل واحد (`BackupFailedNotification`) لـ `BACKUP_NOTIFICATION_EMAIL` فيه كل DB فشلت والسبب |
| D4 | `BACKUP_ARCHIVE_PASSWORD` فاضي و`APP_ENV=production` ← الأمرين (`backup:tenants` و`backup:restore-tenant`) بيرفضوا قبل أي dump (رسالة مترجمة، exit 1، إيميل)، وspatie `backup:run` مالوش destination فبيفشل بصوت عالي. بره الإنتاج: تحذير والأرشيف بيطلع **من غير تشفير** |

**تنبيه Telegram:** `backup:telegram` اتشال نهائيًا (كان بيبعت dump مش مشفّر). تنبيه الفشل على Telegram للـ CTO جزء من OPS-10 (بوت المنصة، chat منفصل)؛ لحد ما يندمج، التنبيه = إيميل + log + الـ health check (§7).

---

## 2. إعداد Google Drive (بإيد الـ CTO، مرة واحدة) — **لم يُنفَّذ**

الـ service account مالوش مساحة على Drive، فبنستخدم **OAuth بحساب الـ CTO** (الـ Gmail اللي عليه باقة التخزين).

1. **Google Cloud project جديد** (مثلًا `sroor-backups`) ← *APIs & Services* ← *Enable APIs* ← **Google Drive API**.
2. **OAuth consent screen**: User type = *External*، اسم التطبيق، إيميل الدعم = إيميلك. الـ scope: **`https://www.googleapis.com/auth/drive.file`** بس (التطبيق يشوف الملفات اللي هو عملها بس، مش باقي الـ Drive). ضيف إيميلك كـ test user، وبعدين **Publish app ← In production**. في وضع *Testing* الـ refresh token بيموت بعد 7 أيام. `drive.file` scope «non-sensitive» فمش محتاج مراجعة من Google.
3. **Credentials ← Create OAuth client ID ← Web application**، و*Authorized redirect URI* = `https://developers.google.com/oauthplayground`. احتفظ بالـ Client ID والـ Client secret في password manager بس.
4. **الـ refresh token**: افتح <https://developers.google.com/oauthplayground> ← الترس ← *Use your own OAuth credentials* (حط الـ client ID/secret) ← في Step 1 اكتب الـ scope `https://www.googleapis.com/auth/drive.file` ← *Authorize APIs* بالـ Gmail بتاعك ← Step 2 *Exchange authorization code for tokens* ← انسخ الـ **refresh token** على طول لـ GitHub secret (خطوة 6). **ماتلصقوش في chat ولا في ملف ولا في commit.** بعدها شيل الـ redirect URI بتاع الـ Playground من الـ client لو حابب.
5. **المجلد**: مع `drive.file` **سيب `GOOGLE_DRIVE_FOLDER_ID` فاضي**: أول backup بيعمل مجلد `sroor-backups` (`BACKUP_PATH_PREFIX`) في My Drive، وده المجلد المخصص. مجلد معمول من واجهة Drive التطبيق مش هيشوفه بـ `drive.file`؛ استخدام folder ID من الواجهة محتاج scope `drive` الكامل (refresh token على الخادم يقرا الـ Drive كله) — **مش مستحسن**.
6. **GitHub ← Settings ← Environments ← `production`**:
   - Secrets: `GOOGLE_DRIVE_CLIENT_ID`، `GOOGLE_DRIVE_CLIENT_SECRET`، `GOOGLE_DRIVE_REFRESH_TOKEN`، `DB_BACKUP_PASSWORD` (نفس كلمة مرور `MYSQL_BACKUP_PASSWORD` اللي اتولدت على الخادم في `vps-runbook.md` §4.1)، و`BACKUP_ARCHIVE_PASSWORD` (موجود من OPS-1: `openssl rand -hex 32`، **واحفظ نسخة منه بره الخادم** — من غيره مفيش أي أرشيف يتفتح).
   - Variables: `BACKUP_NOTIFICATION_EMAIL` (عنوان تنبيهات التشغيل).
   - `release.yml` لازم يمرّرهم (التعديل المطلوب في تقرير الـ lane؛ الملف مش ضمن ملكية OPS-5).

**امتى الـ backups توقف لوحدها:** سحب الصلاحية من <https://myaccount.google.com/permissions>، تغيير باسورد الحساب (ممكن يلغي الـ tokens)، الـ consent screen رجعت *Testing*، token متستخدمش 6 شهور، أو المساحة خلصت. في كل الحالات: الـ upload بيفشل ← إيميل الفشل الساعة 01:30 + `BackupFreshness` أحمر في الـ health بعد 26 ساعة. العلاج: خطوة 4 من تاني وتحديث الـ secret ثم release (أو تعديل `shared/.env` + `php artisan config:cache`).

---

## 3. مفاتيح الـ `.env`

| المفتاح | الإنتاج (`scripts/ops/templates/production.env.example`) | ملاحظات |
|---|---|---|
| `BACKUP_ARCHIVE_PASSWORD` | إلزامي (secret) | D4. `check-env.sh` يفشل لو فاضي، تحذير لو أقل من 24 حرف |
| `BACKUP_DISKS` | `google` | `check-env.sh` يفشل لو مفيهوش `google` (معيار الإنتاج #8). `BACKUP_TENANT_DISKS` يغيّر disks الـ DB بس |
| `BACKUP_PATH_PREFIX` | `sroor-backups` | اسم المجلد على الـ disk |
| `BACKUP_NOTIFICATION_EMAIL` | إلزامي (variable) | إيميل الفشل + تنبيهات الـ health |
| `DB_BACKUP_USERNAME` / `DB_BACKUP_PASSWORD` | `sroor_backup` / secret | `check-env.sh`: إلزامي، مش `root` ومش `DB_USERNAME` |
| `GOOGLE_DRIVE_CLIENT_ID` / `_CLIENT_SECRET` / `_REFRESH_TOKEN` | secrets إلزامية لما `google` في الـ disks | `render-env.sh` يرفض `--optional` ليهم |
| `GOOGLE_DRIVE_FOLDER_ID` | فاضي (§2 خطوة 5) | الـ workflow يمرّره `--optional` |
| `DB_RESTORE_USERNAME` / `DB_RESTORE_PASSWORD` | **مش في الإنتاج** | للـ drill على جهاز تاني؛ على الـ VPS استخدم `--db-user` (§5) |
| `HEALTH_CHECK_HORIZON` | `true` | فحص Horizon (الـ staging مفيهوش Horizon) |
| `BACKUP_VERIFY_AFTER_UPLOAD`، `BACKUP_MAX_AGE_HOURS`، `DB_DUMP_TIMEOUT`، `BACKUP_RESTORE_DB_PREFIX`، `HEALTH_DISK_WARN_PERCENT`/`_FAIL_PERCENT`، `SCHEDULE_HEARTBEAT_URL` | defaults | `true`، `26`، `3600`، `tenant_zz_restore_`، `80`/`90`، فاضي |

---

## 4. الأوامر

```bash
# كل حاجة (المركزي + كل المستأجرين غير المؤرشفين) + الاحتفاظ:
php artisan backup:tenants
# مستأجر واحد (أي حالة، حتى archived) من غير المركزي:
php artisan backup:tenants --tenant=shop1
php artisan backup:tenants --only-central
php artisan backup:tenants --skip-central --no-cleanup --disk=local
```

exit 0 = كل DB اترفعت واتأكدنا منها. exit 1 = فيه فشل (التفاصيل في الـ output والـ log والإيميل).

---

## 5. الاستعادة — `backup:restore-tenant`

**الأمر عمره ما بيكتب على DB موجودة.** بيعمل DB جديدة اسمها لازم يبدأ بـ `BACKUP_RESTORE_DB_PREFIX` (`tenant_zz_restore_`، داخل نمط `tenant\_%` بتاع الـ provisioner)، ويرفض لو الاسم موجود.

```bash
# أحدث نسخة متحقق منها للمستأجر، في DB جديدة، وتفضل موجودة:
php artisan backup:restore-tenant shop1 --db-user=sroor_provisioner
# نسخة معيّنة من السجل:
php artisan backup:restore-tenant shop1 --backup=123 --db-user=sroor_provisioner
# أرشيف مش في السجل (مثلًا اتنزّل على جهاز الـ drill):
php artisan backup:restore-tenant central --disk=google --path=sroor-backups/central/central-2026-10-11-01-30-00.zip
# الـ drill: استعادة + تحقق + حذف النسخة المستعادة:
php artisan backup:restore-tenant shop1 --drop-after-verify --db-user=sroor_provisioner
```

الخطوات: تنزيل في مجلد مؤقت خاص ← مقارنة الـ sha256 بالسجل (اختلاف = رفض) ← فك التشفير ← التأكد إن الـ manifest بتاع نفس الـ subject ← `CREATE DATABASE` + `mysql < dump` (كلمة المرور في option file بصلاحية 600 بيتمسح، مش في argv) ← **عدد صفوف كل جدول = الـ manifest** ← `--drop-after-verify` يحذف النسخة. لو العدد مختلف: exit 1، جدول بالفروق، والـ DB **بتفضل** للفحص. لو الاستيراد فشل: الـ DB الجديدة بتتمسح.

- `--db-user`: الحساب اللي يقدر يعمل `CREATE DATABASE` على `tenant\_%` (على الـ VPS = `sroor_provisioner`). الباسورد بيتسأل مخفي (الـ config متخزن على الـ VPS، فمتغيرات البيئة وقت التشغيل مش بتتقري). من غيره: `DB_RESTORE_*` ثم حساب الـ DB المركزي.
- عدد الصفوف في الـ manifest بيتحسب قبل الـ dump مباشرةً (الـ dump snapshot منفصل): على مستأجر شغال وقت الـ backup ممكن يطلع فرق صغير حقيقي. الـ drill يتعمل على نسخة الليل (01:30) أو نسخة الـ deploy.

**استعادة حقيقية لمستأجر (حادثة):** الأمر بيجهّز نسخة مستعادة ومتحقق منها بس. تحويل المستأجر عليها خطوة يدوية للـ CTO: المحل read-only/صيانة ← تعديل `tenancy_db_name` بتاع المستأجر عن طريق `UpdateTenantDatabaseConfigAction` (OPS-9: step-up + audit) ← تجربة ← الـ DB القديمة تفضل لحد ما يتأكد. ممنوع `DROP` للـ DB القديمة من غير backup نهائي متحقق منه.

---

## 6. Restore drill شهري — **أول drill لم يُنفَّذ**

أول يوم عمل في كل شهر، على الـ VPS (أو جهاز drill عليه نفس الـ `.env` من غير `config:cache`):

1. `php artisan backup:restore-tenant central --drop-after-verify --db-user=sroor_provisioner`
2. نفس الأمر لـ 2 مستأجرين: أكبر مستأجر + واحد عشوائي.
3. مرة كل 3 شهور: استعادة من نسخة **شهرية** (`--backup=<id>` أقدم واحدة في السجل) عشان نتأكد إن الاحتفاظ شغال.
4. سجّل النتيجة هنا:

| التاريخ | Subject | النسخة (id / تاريخ) | الجداول | الصفوف | النتيجة | مين |
|---|---|---|---|---|---|---|
| — | — | — | — | — | لم يُنفَّذ | — |

لو الـ drill فشل: ده incident — وقّف الـ deploys لحد ما السبب يتعرف (§8).

---

## 7. الـ health checks (`spatie/laravel-health`)

متسجلين في `App\Providers\HealthServiceProvider` (مفيش HTTP endpoint). `health:check` بيشتغل كل 5 دقايق بالـ scheduler (إيميل لـ `BACKUP_NOTIFICATION_EMAIL` عند **failed** بس، مرة كل ساعة بالكتير)، و`deploy.sh` بيشغله كبوابة الـ release.

| الفحص | أحمر لما | في بوابة الـ deploy |
|---|---|---|
| Database | الاتصال بالـ DB المركزي فشل | ✔ |
| Cache | كتابة/قراية الكاش فشلت | ✔ |
| Redis | (لو cache/queue/session = redis) الاتصال فشل | ✔ |
| Disk Space | استخدام القرص > 90% (تحذير > 80%، التحذير مش بيوقف الـ deploy) | ✔ |
| Backup Archive Password | فاضي في الإنتاج (D4). أقل من 24 حرف = تحذير | ✔ |
| Horizon | (`HEALTH_CHECK_HORIZON=true`) Horizon واقف | ✘ |
| Queue | job الـ heartbeat (`health:queue-check-heartbeat` كل دقيقة) ماتنفذش من 5 دقايق | ✘ |
| Schedule | الـ cron ماشتغلش من 5 دقايق (`health:schedule-check-heartbeat`) | ✘ |
| Backup Freshness | أي DB لازم يتعملها backup (المركزي + كل مستأجر مش archived وعمره > 26 ساعة) مالهاش نسخة **متحقق منها** أحدث من 26 ساعة. failed في الإنتاج، warning بره | ✘ |

**بوابة الـ deploy** (`SROOR_HEALTH_DEPLOY_GATE=1` في `deploy.sh`): فحوص الـ release بس، ومن غير ولا «warning» (spatie بيعتبر الـ warning فشل مع `--fail-command-on-failing-check`). Horizon/queue/schedule/freshness متعلقين بالـ cron والـ supervisor مش بالـ release: لو دخلوا البوابة، أول deploy مستحيل (مفيش حاجة اشتغلت لسه) وrelease سليم ممكن يترجع وHorizon بيعيد التشغيل.

اختياري ومستحسن: `SCHEDULE_HEARTBEAT_URL` (مثلًا healthchecks.io) — لو الخادم أو الـ cron وقف، مفيش `health:check` هيشتغل يبعت إيميل، فالـ ping الخارجي هو اللي ينبّه.

---

## 8. الـ backup قبل الـ migrations في الـ deploy

`scripts/ops/deploy.sh`، مرحلة `pre-migration-backup` (بعد `storage:link` وقبل `migrate`): `php artisan backup:tenants --no-cleanup` من الـ release الجديد. أي DB تفشل ← الـ release يقف (exit 1، المستخدمين على الـ release القديم، مفيش migration اتعملت). أول release على خادم جديد مفيهوش backup (مفيش بيانات). طوارئ بس: `--skip-pre-migration-backup` (بيتسجل في `shared/deploy-history.log`). التفاصيل في `deploy-runbook.md` §2.

---

## 9. لما حاجة تفشل

| العَرَض | السبب الغالب | العلاج |
|---|---|---|
| `BACKUP_ARCHIVE_PASSWORD فاضي` | D4 | اضبطه (نفس القيمة القديمة لو فيه أرشيفات!) ثم `config:cache` |
| `قرص Google Drive ... مش مضبوط` | مفاتيح `GOOGLE_DRIVE_*` ناقصة | §2 خطوة 6 |
| `invalid_grant` / `Upload ... failed` | الـ refresh token اتلغى أو انتهى | §2 خطوة 4 تاني |
| `Access denied for user 'sroor_backup'` | الحساب/الصلاحيات | `vps-runbook.md` §5، `--only 41-mysql-users` |
| `النسخة المرفوعة ... مش مطابقة` | رفع ناقص/تالف | يتعاد الليلة الجاية تلقائيًا؛ لو اتكرر: مساحة الـ Drive/الشبكة |
| `جدول سجل النسخ tenant_backups ... مش موجود` | الـ release اللي فيه OPS-5 لسه ماعملش migrate | طبيعي في أول deploy (الأرشيفات بتترفع من غير سجل)؛ بعد الـ migrate بيختفي |
| `BackupFreshness` أحمر | فشل الليلة (شوف الإيميل) أو الـ scheduler واقف | `php artisan backup:tenants` يدويًا بعد إصلاح السبب |

---

## 10. اختبارات

```bash
cd backend
php artisan test --filter='BackupTenantsCommandTest|RestoreTenantBackupCommandTest|HealthChecksTest|ScheduledTelegramJobsGuardTest|BackupConfigTest'
bash ../scripts/ops/tests/deploy-test.sh
```

تغطي: أرشيف لكل مستأجر مشفّر AES-256 (الـ manifest مايتقراش من غير الباسورد) وعدد الصفوف صح والـ sha256 = السجل و`verified_at`؛ مستأجر بايظ مايوقفش الباقي + إيميل؛ D4 في الإنتاج (exit 1، صفر ملفات، صفر صفوف)؛ المركزي كـ subject لوحده؛ تخطي الـ archived إلا بالطلب؛ الاحتفاظ 7/4/3 ومسح صفوف السجل؛ الاستعادة في DB جديدة بعدد صفوف مطابق، رفض checksum غلط/باسورد غلط/subject تاني/اسم DB حي أو موجود، فرق عدد الصفوف يفضل للفحص؛ الـ health (التسجيل، البوابة، D4، 26 ساعة، غير متحقق منه، مستأجر جديد/مؤرشف)؛ وترتيب الـ deploy (backup قبل migrate، فشله يوقف الـ release، الـ skip بيتسجل).
