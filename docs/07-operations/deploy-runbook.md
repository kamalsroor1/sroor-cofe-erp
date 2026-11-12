# Deploy runbook — الـ release على الـ VPS (OPS-3)

> الحالة: **منفَّذ ومختبَر offline، والبروفة على الـ VPS لم تُنفَّذ بعد** (`vps-runbook.md` §15). الملفات: `.github/workflows/release.yml` و`scripts/ops/deploy.sh` (+ `scripts/ops/sync-central-grants.sh`). هذا الملف هو ترتيب الخطوات والعقد بينهم، ومرجع الـ deploy/rollback اليدوي.
>
> - لا يحتوي أي سر ولا عنوان خادم. الأسرار في GitHub Environment `production` و`shared/.env` على الخادم فقط (`secrets.md`).
> - **لا علاقة له بـ `deploy.yml`** (ينشر `main` على الخادم المشترك الذي عليه المحل الحي) ولا بسكريبتات الجذر `deploy_*.py`. لا تُلمس.
> - تخطيط المجلدات من `scripts/ops/steps/90-app-layout.sh` (OPS-1): `releases/<id>/`، `current -> releases/<id>`، `shared/.env`، `shared/storage/`.

---

## 1. الـ workflow (`.github/workflows/release.yml`)

**التشغيل:** push لـ tag ‏`v*`، أو يدويًا (Actions ← release ← Run workflow). Environment ‏`production` لازم يقصر الـ deploy على tags ‏`v*` ويطلب موافقة الـ CTO (`secrets.md` §2). `concurrency: release-production` بدون إلغاء: release واحد في المرة، ومفيش deploy يتقطع في النص.

| Job | بيعمل إيه |
|---|---|
| `tests` | PHP **8.5** (نفس الـ VPS، **[CTO-2026-11-11]** من أرشيف Ubuntu 26.04؛ الـ runner نفسه لسه `ubuntu-24.04` وsetup-php بيركّب 8.5 عليه): `composer install` ← `php artisan test` (sqlite) ← `scripts/ops/tests/run-tests.sh` ← `render-env-test.sh` ← `deploy-test.sh` |
| `build` | `composer install` (كامل، عشان `lang:export`) ← `npm ci && npm run build` ← `composer install --no-dev --optimize-autoloader` ← tar لـ `backend/` + `scripts/ops/` بس (من الـ checkout، فمفيش scratch scripts ولا backups ولا `*.py`)، من غير `node_modules`/`tests`/`android`/`storage`/`.env*`/caches الـ build. بيتأكد إن مفيش `.env` ولا `vendor/phpunit` جوه، ويطلع sha256. الـ artifact بيتحفظ 7 أيام |
| `deploy` | `environment: production` (موافقة). checksum ← **[CTO-2026-11-11]** «Release mode» (`vars.RELEASE_REHEARSAL`: فاضي/`false` = strict، `true` = rehearsal، غير كده = فشل) ← `render-env.sh` للـ `.env` (مع `--rehearsal` في البروفة بس) ← **بوابة `check-env.sh`** ← `render-env.sh` لـ `deploy.env` (الـ migrator) ← SSH config من الـ secrets (`StrictHostKeyChecking yes` بـ known_hosts مثبّت، `BatchMode`، مفيش passwords) ← رفع الـ artifact والملفين و`scripts/ops` لـ `<APP_ROOT>/incoming/<sha>-<run>-<attempt>/` (`umask 077`) ← `ssh ... bash -s` يشغّل `deploy.sh` ويمسح مجلد الرفع في كل الحالات ← مسح الأسرار من الـ runner (`if: always()`) |

**بوابة الـ preflight** (في الـ runner وتاني على الخادم، `check-env.sh`) بتفشل الـ release لو: `TELESCOPE_ENABLED` مش `false` (أو غايب)، `BACKUP_ARCHIVE_PASSWORD` فاضي (**قرار D4**)، أي `MAIL_*` ناقص أو `MAIL_MAILER` مش `smtp` (**W1 Q6**)، `DB_AUDIT_PRUNER_*` ناقص، `APP_DEBUG` مش `false`، `SENTRY_SEND_DEFAULT_PII` مش `false`، وباقي معايير `vps-runbook.md` §8. `SENTRY_LARAVEL_DSN` فاضي = تحذير بس.

**[CTO-2026-11-11] وضع البروفة:** لو متغير الـ Environment ‏`RELEASE_REHEARSAL=true`، البوابة (`check-env.sh --mode rehearsal`) بتحوّل **بس** شرط SMTP (`MAIL_MAILER=log`) وشرط Google Drive (`BACKUP_DISKS=local`) لسطور `WARN  REHEARSAL ...` بأسماء المفاتيح، وكل الباقي (D4، Telescope، `APP_DEBUG`...) زي ما هو. التفاصيل والإيقاف في [`secrets.md`](secrets.md) §2.1، وخطوات أول deploy في §7 هنا. من غير المتغير: البوابة الصارمة بالظبط زي الأول.

---

## 2. `scripts/ops/deploy.sh` — الترتيب على الخادم

بيشتغل كمستخدم التطبيق (`sroor`)، `set -euo pipefail`، مفيش `|| true`، ومفيش `set -x`. اسم الـ release: `releases/<UTC YYYYmmddHHMMSS>-<sha12>` (بيسمح بإعادة نشر نفس الـ commit لتدوير سر من غير ما نلمس مجلد شغّال).

| # | المرحلة (`STAGE`) | الخطوة | لو فشلت |
|---|---|---|---|
| 0 | — | lock: `mkdir <APP_ROOT>/.deploy.lock` (deploy واحد في المرة) | exit ≠ 0، مفيش أي تغيير |
| 1 | `preflight` | الـ layout موجود؛ الـ artifact: sha256، فيه `backend/artisan`، مفيش مسارات مطلقة أو `../`، مفيش `backend/.env`؛ `check-env.sh --mode strict|rehearsal` على الـ `.env` الجديد (أو `shared/.env`) — **[CTO-2026-11-11]** `--rehearsal` لازم يطابق `RELEASE_REHEARSAL` في الـ `.env` والعكس، وفي البروفة بيطبع كل تساهل بالاسم؛ ملفات MySQL option (600) للـ migrator والـ app والـ pruner في مجلد مؤقت خاص | exit 1 |
| 2 | `extract` | `tar -xzf` في `releases/<id>`؛ حذف `storage/` و`.env` وcaches الـ build اللي جت مع الـ artifact | exit 1، الـ release يتمسح |
| 3 | `env` | لو فيه `--env-file`: نسخة من `shared/.env` الحالي ← `shared/.env.previous`، والجديد يتركّب ذريًا (600)؛ ثم `backend/.env` ← `shared/.env` و`backend/storage` ← `shared/storage` (symlinks) | exit 1 + استرجاع `.env` |
| 4 | `storage-link` | `php artisan storage:link --force` (روابط `public/storage` و`public/central-assets` لازم في **كل** release لأن `public/` جزء منه) | exit 1 |
| 4b | `pre-migration-backup` | **OPS-5:** `php artisan backup:tenants --no-cleanup` من الـ release الجديد (الـ config مش متخزن لسه، بيقرا `shared/.env`): المركزي + كل مستأجر مش archived، مشفّر AES-256، مرفوع على Google Drive ومتحقق منه (sha256) ومتسجل في `tenant_backups`. **مابيتعملش في أول release** على الخادم (مفيش بيانات). طوارئ بس: `--skip-pre-migration-backup` (بيتسجل `pre-migration-backup-skipped` في `deploy-history.log`). التفاصيل في [`backup-restore.md`](backup-restore.md) | exit 1، مفيش migration اتعملت |
| 5 | `migrate-central` | `php artisan migrate --force` بحساب **`sroor_migrator`** (`DB_USERNAME`/`DB_PASSWORD` كمتغيرات بيئة للعملية دي بس؛ الـ config مش متخزن لسه) | exit 1، `tenants:migrate` مايشتغلش |
| 6 | `central-grants` | `sync-central-grants.sh` كـ migrator: صلاحيات الجداول لعقد الـ audit الـ append-only، وكل من `app` و`audit_pruner` يتحقق من `SHOW GRANTS` بتاعه (`vps-runbook.md` §5.1) | exit 1 |
| 6b | `central-permissions` | **[CTO-2026-11-11]** `php artisan db:seed --class=CentralPermissionsSeeder --force` بحساب `app`: بيزامن أدوار وصلاحيات المشغّلين (`super_admin`، `support`) من enum ‏`CentralPermission`، وبيمسح أي صلاحية central-guard خرجت من الـ enum. idempotent، ومن غير أي بيانات تجريبية. ده الـ seeder **الوحيد** المسموح في الـ pipeline (`deploy-test.sh` §7b بيمنع أي `db:seed` تاني). اتضاف بعد أول deploy، لما `central:create-super-admin` فشل لأن دور `super_admin` ماكانش موجود | exit 1 |
| 6c | `plan-catalog` | **[CTO-2026-11-11]** `php artisan db:seed --class=PlansAndFeaturesSeeder --force` بحساب `app`: الكتالوج المعتمد (الباقات والمميزات والإضافات) من `Database\Seeders\Catalog\*`. **بيضيف الناقص بس** وبيدمج مفاتيح المميزات الجديدة، ومابيكتبش فوق سعر أو حد أو اسم عدّله السوبر أدمن. اتضاف بعد ما صفحة الأسعار طلعت فاضية على أول deploy. ده والـ seeder اللي فوقه هما الاتنين **الوحيدين** المسموحين (`deploy-test.sh` §7b) | exit 1 |
| 7 | `migrate-tenants` | **[OPS-25]** `php artisan tenants:migrate-all --force` بيشتغل **منفصل (setsid + nohup)** على السيرفر: كل مستأجر في process لوحده بـ timeout (`--tenant-timeout`، الافتراضي 900 ث، من متغير GitHub `TENANT_MIGRATE_TIMEOUT_SECONDS`) وبيكمّل للمستأجر اللي بعده حتى لو واحد فشل. ملف الحالة `shared/tenant-migrate-status.json`، الـ log `shared/tenant-migrate.log`، كود الخروج `shared/tenant-migrate.rc`. الـ deploy بيستنى (حد أقصى `--tenant-migrate-max-wait` = 7200 ث) وبيطبع التقدّم والتقرير النهائي (ok / failed / timeout / skipped). **أي مستأجر failed أو timeout يوقف الـ release قبل التبديل** (الكود القديم لسه شغال على الـ schema المتهجّرة). **[OPS-2]** المستأجرين اللي `provisioning_status` بتاعهم مش `ready` بيتخطّوا. لو الـ SSH أو الـ workflow اتقطع، الـ migration مبتتقتلش، وإعادة تشغيل الـ deploy بتتصل بالـ run الشغال (`.pid`) بدل ما تبدأ واحد تاني | exit 1، الباقي متهجّر والـ release مش متفعّل |
| 7b | `relocate-app-releases` | **[W2 batch 5، APP-3، CTO Q9]** `php artisan app-releases:relocate`: **[R1] بينسخ بس** ملفات الـ APK/EXE من `storage/app/public/apks` (متاحة للعامة) للقرص الخاص `storage/app/private/releases` بنفس المسار النسبي ويتأكد من الـ SHA-256، **ومابيمسحش النسخة العامة** (الـ release القديم لسه شغال وبيقرا منها، وممكن نرجع له)، **من غير أي تعديل في الـ DB**. idempotent: لو مفيش حاجة يرجع 0. ملف موجود في القرصين بمحتوى مختلف (conflict) أو نسخ فشل ← exit 1 والملفين زي ما هما. ملفات في جذر `public/` (`public/*.apk`) بيطبع عنها تحذير بس ومابيمسحهاش (`vps-runbook.md` §4.4) | exit 1 |
| 8 | `caches` | `config:cache` (والملف `chmod 600` لأنه فيه الأسرار) ← `route:cache` ← `view:cache` ← `event:cache` | exit 1 |
| 9 | `smoke` | `php artisan health:check --fail-command-on-failing-check` **من الـ release الجديد قبل التبديل**. لو مفيش ولا check مسجّل (`spatie/laravel-health`) يفشل، إلا مع `--allow-no-health-checks` | exit 1 |
| 10 | `switch` | `ln -sfn releases/<id> current.tmp && mv -Tf current.tmp current` (rename ذري) ← `sudo -n /usr/bin/systemctl reload php8.5-fpm` (الأمر الوحيد اللي `deploy.sh` بيستخدمه من sudoers، لتفريغ opcache؛ **[CTO-2026-11-11]** على 26.04 ده sudo-rs، والقاعدة arguments بالظبط) | rollback تلقائي |
| 11 | `queue-restart` | `php artisan horizon:terminate` (أو `queue:restart` مع `--queue-mode worker`)؛ supervisor يشغّل Horizon من `current` الجديد | rollback تلقائي |
| 12 | `health` | `health:check` من `current` + `GET <APP_URL>/up` (route ‏`/up` المدمج في Laravel، **مفيش route جديد**) على nginx المحلي (`curl --resolve <host>:443:127.0.0.1`)، 10 محاولات | rollback تلقائي |
| 12b | `prune-legacy-releases` | **[R1]** بعد ما الـ health check اللي بعد التبديل ينجح بس: `php artisan app-releases:relocate --prune-legacy` من `current` — بيمسح النسخة العامة من كل ملف **بس لو** الـ SHA-256 بتاع النسخة الخاصة مطابق. **مش fatal:** لو فشل بيكتب تحذير (`app-releases:relocate --prune-legacy failed (non-fatal)`) والـ release الجديد يفضل شغال من غير rollback، والنسخ العامة تفضل لحد الـ deploy الجاي | تحذير بس |
| 13 | `cleanup` | `.previous_release` = السابق؛ إبقاء آخر `--keep` (5) releases، و`current` والسابق عمرهم ما يتمسحوا؛ سطر في `shared/deploy-history.log` | — |

**أكواد الخروج:** `0` نجح؛ `1` فشل قبل التبديل (المستخدمين ماشافوش حاجة، الـ release اتمسح، `.env` اترجع)؛ `3` فشل بعد التبديل و**اترجع تلقائيًا** للسابق؛ `4` الـ rollback نفسه محتاج تدخل (مثلًا أول release فشل بعد التبديل: `current` بيتشال)؛ `2` استخدام غلط.

**الـ health check:** **[OPS-5/OPS-7]** الفحوص متسجلة في `App\Providers\HealthServiceProvider` (الجدول الكامل في [`backup-restore.md`](backup-restore.md) §7). `deploy.sh` بيعمل `export SROOR_HEALTH_DEPLOY_GATE=1`، فـ `health:check` في المرحلتين 9 و12 (وفي الـ rollback) بيشغّل **فحوص الـ release بس**: DB المركزي، الكاش، Redis، المساحة (فشل فوق 90% بس)، و`BACKUP_ARCHIVE_PASSWORD` (D4)، ومن غير «warning» (spatie بيعتبر الـ warning فشل مع `--fail-command-on-failing-check`). Horizon والـ queue والـ scheduler وعمر آخر backup **مش** في البوابة: بيعتمدوا على الـ cron/supervisor مش على الـ release، ولو دخلوا كان أول deploy هيبقى مستحيل وrelease سليم ممكن يترجع وHorizon بيعيد التشغيل؛ بيتراقبوا بـ `health:check` المجدول كل 5 دقايق (إيميل). `--allow-no-health-checks` لسه موجود للبروفة بس، ومابقاش له لازمة بعد OPS-5.

---

## 3. الأسرار أثناء الـ deploy

- `production.env` و`deploy.env` بيتعملوا في الـ runner بـ `render-env.sh` (`umask 077`)، ويترفعوا في مجلد `incoming/...` بصلاحية 700، ومجلد الرفع بيتمسح بـ `trap` في آخر الـ SSH session مهما حصل. الـ runner بيمسح نسخه في خطوة `if: always()`.
- كلمة مرور الـ migrator **عمرها ما بتدخل `shared/` ولا الـ release** (`deploy-test.sh` بيتأكد).
- MySQL بياخد الباسوردات من option files (600) في مجلد مؤقت خاص بيتمسح على الخروج — مش من argv ولا من `MYSQL_PWD`.
- الاستثناء الوحيد: `php artisan migrate` بياخد باسورد الـ migrator كمتغير بيئة للعملية دي (مقروء لنفس المستخدم وroot بس في `/proc/<pid>/environ`).
- **تغيير `APP_KEY` (SEC-21):** token بوت Telegram بتاع كل محل (`settings.telegram_bot_token`) وباسورد DB المستأجر بيتخزنوا مشفّرين بـ `APP_KEY`. لو غيّرت المفتاح، حط القديم في `APP_PREVIOUS_KEYS` (مفصولة بفاصلة) في `shared/.env` قبل الـ deploy، عشان Laravel يفك بيه القيم القديمة. من غيره: الـ token القديم مابيتقريش، المحل ماتوصلوش تنبيهات (مفيش رجوع لبوت المنصة)، وشاشة الإعدادات بتقول `telegram_bot_token_needs_reentry: true` لحد ما الأدمن يدخل الـ token تاني.

---

## 4. الـ rollback

**تلقائي:** أي فشل بعد التبديل (المراحل 10–12) ← `current` يرجع للسابق ← reload لـ php-fpm ← `horizon:terminate` من السابق ← `shared/.env` يرجع ← health check للسابق ← exit 3 (أو 4 لو حاجة فشلت).

**يدوي** (كمستخدم `sroor` على الخادم):

```bash
bash /var/www/sroor/current/scripts/ops/deploy.sh --app-root /var/www/sroor --rollback
```

بيبدّل `current` لـ `.previous_release`، reload، restart للـ queue، health check، ويسجّل الـ release اللي سابه في `.previous_release` (تشغيله تاني يرجّعك). `shared/.env` مابيتغيرش لأن كل release معاه `config.php` متخزن بتاعه.

**قواعد ثابتة:**
1. **الـ migrations مش بتترجع تلقائيًا.** كل migration لازم تكون متوافقة للخلف (الكود السابق يشتغل على الـ schema الجديد). لو مش ممكن، الـ release يتعلّم «بلا rollback آلي» ويحتاج backup حديث قبل النشر.
1b. **[W2 batch 5] ملفات الإصدارات بعد `relocate-app-releases`:** **[R1]** المرحلة 7b بتنسخ بس، فالـ rollback **التلقائي** (فشل بعد التبديل) بيلاقي النسخ العامة زي ما هي. المسح بيحصل في 12b بعد ما الـ release الجديد يثبت إنه سليم، ومابيترجعش. لو رجعت **يدويًا بعد كده** لـ release **أقدم من W2 batch 5** (بيقرا الـ APK من `storage/app/public` بس)، تحميل التحديثات من التطبيقات هيرجع 404 لحد ما تنشر release جديد؛ نقطة البيع نفسها مش متأثرة. أي release من W2 batch 5 أو بعده بيقرا القرص الخاص الأول وبعدين القديم، فالرجوع بينهم آمن. ولو لازم الرجوع لـ release قديم لفترة: انسخ (مش انقل) `shared/storage/app/private/releases/apks` لـ `shared/storage/app/public/apks` يدويًا، والـ deploy الجاي هيشيلها تاني.
1c. **[W2 batch 5، خطر موثّق] نافذة الـ deploy وأعمدة `tenants` الجديدة:** بين `migrate-central` (المرحلة 5) والتبديل (المرحلة 10) الـ release **القديم** لسه بيخدم الطلبات على schema فيها أعمدة جديدة (مثلًا `archive_state`, `archived_at`, `purge_started_at`, `purged_at` من migration ‏`500300`). موديل `Tenant` (stancl VirtualColumn) في الكود القديم مايعرفش الأعمدة دي كـ custom columns، فلو الكود القديم **حفظ** صف مستأجر في النافذة دي (تغيير حالة، تحديث بيانات من السوبر أدمن، provisioning) ممكن يكتب قيم الأعمدة الجديدة **جوه عمود `data` (JSON)** كمان. العمود الحقيقي مابيتأثرش، بس `data` بيبقى فيه نسخة قديمة ممكن تتقري غلط بعدين. التقليل: انشر في وقت هادي، ومتعملش عمليات على المستأجرين من لوحة السوبر أدمن أثناء الـ deploy. بعد الـ deploy اتأكد (قراءة بس): `SELECT id FROM tenants WHERE JSON_CONTAINS_PATH(data, 'one', '$.archive_state', '$.archived_at', '$.purge_started_at', '$.purged_at');` — لو رجع صفوف، صلّحها يدويًا بعد مراجعة (مفيش أمر تلقائي).
1d. **[W2 batch 5] قبل أول deploy فيه migration ‏`2026_10_10_500400_add_release_integrity_columns_to_app_versions_table`:** الـ migration بتضيف `unique(platform, version_code)` على `app_versions`. شغّل الفحص ده (قراءة بس) على قاعدة البيانات المركزية الأول:
    ```sql
    SELECT platform, version_code, COUNT(*) FROM app_versions GROUP BY 1,2 HAVING COUNT(*)>1;
    ```
    لو رجع أي صف: الـ migration هتفشل (والـ release يقف قبل التبديل). قرّر يدويًا أنهي إصدار يفضل (بعد backup) قبل ما تنشر.
2. lock متبقي من deploy اتقطع (مثلًا اتلغى الـ run): اتأكد إن مفيش `deploy.sh` شغال (`pgrep -f deploy.sh`)، بعدين `rmdir /var/www/sroor/.deploy.lock`.
3. سجّل السبب، وأعد الـ deploy بعد الإصلاح.

---

## 5. Checklist البروفة (يكمل `vps-runbook.md` §10 و§15) — **لم تُنفَّذ**

- [ ] deploy أول release ← `current` يشير إليه، `curl` على `/up` و health check = 200.
- [ ] `ls -l releases/<id>/backend/public/storage releases/<id>/backend/public/central-assets` ← الرابطان يشيران داخل `shared/storage`؛ شعار مرفوع من الـ super-admin يظهر.
- [ ] release ثانٍ ← الروابط موجودة فيه أيضًا (المرحلة 4 تعمل في كل release).
- [ ] rollback يدوي ← الـ release السابق يعمل، والملفات المرفوعة بعد الـ release الثاني ما زالت ظاهرة (لأنها في `shared/storage`).
- [ ] release بـ migration فاشلة عمدًا على مستأجر تجريبي ← exit 1 قبل تبديل `current`.
- [ ] health فاشل بعد التبديل ← exit 3 والرجوع للسابق تلقائيًا.
- [ ] **OPS-5:** تاني deploy ← `backup:tenants` اشتغل قبل `migrate`، والأرشيفات ظهرت في مجلد `sroor-backups` على Drive وفي `tenant_backups` بـ `verified_at`. وتجربة سحب صلاحية Drive مؤقتًا ← الـ release يقف عند `pre-migration-backup` (exit 1، مفيش migration).
- [ ] `.env` بـ `TELESCOPE_ENABLED=true` أو `BACKUP_ARCHIVE_PASSWORD` فاضي ← الـ workflow يفشل قبل الاتصال بالخادم.

---

## 5b. المسار القديم (متقاعد) — BE-06

`deploy_root_baraa.py` (جذر الـ repo) كان بيعمل `tenants:migrate --tenants=2m --force || true` (مستأجر واحد والفشل مخفي)، ويمسح صف المستأجر `tenant_sroor`، ويشغّل seeders و`tenant:populate-realistic-data`. اتعطّل: بيخرج برسالة إلا لو `SROOR_ALLOW_LEGACY_DEPLOY` اتضبط صراحةً، وخطوات المسح والـ seed والـ populate اتشالت منه وبقى `tenants:migrate --force` لكل المستأجرين من غير `|| true`. المسار الوحيد المعتمد للـ SaaS هو `release.yml` ← `scripts/ops/deploy.sh` (backup قبل الـ migrate، `migrate --force` مركزي، `tenants:migrate --force` لكل المستأجرين وأي فشل يوقف الـ release، من غير seeders). `scripts/ops/tests/deploy-test.sh` §7b بيفشل لو حد رجّع `db:seed` أو `tinker` أو `--tenants=` أو `|| true` على migrate/backup في المسار الجديد.
`.github/workflows/deploy.yml` (push إلى `main` ← webhook محل Hostinger الشغال) **لم يتعدّل** (محل شغال، قرار الـ CTO): تعطيله إجراء يدوي في إعدادات GitHub.

## 6. اختبار محلي

```bash
bash scripts/ops/tests/deploy-test.sh
```

offline بالكامل: stubs لـ `php` و`mysql` و`sudo` و`curl` في أول الـ PATH، و`APP_ROOT` مؤقت. يغطي: أول deploy وتاني، الترتيب الكامل للأوامر، **[OPS-5]** الـ backup قبل الـ migrations (مش في أول release، فشله يوقف الـ release من غير أي migration، و`--skip-pre-migration-backup` بيتسجل)، وإن كل استدعاء artisan بـ `SROOR_HEALTH_DEPLOY_GATE=1`، ورفض الـ preflight لـ `DB_BACKUP_PASSWORD` فاضي و`GOOGLE_DRIVE_REFRESH_TOKEN` فاضي و`BACKUP_DISKS=local`، الحساب المستخدم في كل migrate، فشل قبل التبديل (central migrate، `tenants:migrate`، `config:cache`، مفيش health checks، صلاحيات audit غلط)، rollback تلقائي (health بعد التبديل، `/up`، restart الـ queue)، أول release يفشل بعد التبديل، رفض الـ preflight (Telescope، D4، SMTP، pruner، `APP_DEBUG`، `.env` جوه الـ artifact، checksum، sha غلط)، الـ lock، الـ rollback اليدوي والرجوع منه، الـ pruning من غير ما يمشي ورا الـ symlinks، **[CTO-2026-11-11]** وضع البروفة (`.env` بروفة من غير `--rehearsal` مرفوض والعكس، D4 وTelescope مش بيتساهل فيهم، التساهلات مكتوبة بالاسم في الـ log، `deployed-rehearsal` في الـ history، والرجوع للصارم)، وإن مفيش سر ظهر في أي output.

---

## 7. أول deploy على `ibraa.app` (بروفة) — **[CTO-2026-11-11]** — خطوات الـ CTO بالترتيب

> لا يقوم بها أي agent: push الـ tag والموافقة والـ SSH كلها بإيدك. **عمرك ما تعمل push لـ `main`** (بينشر المحل الحي عبر `deploy.yml`)، ولا `git push --tags`، ولا `git push` من غير refspec.

**7.1 GitHub (مرة واحدة)**
1. Settings → Environments → `production` → Environment variables → **Add variable**: `RELEASE_REHEARSAL` = `true`.
2. اتأكد إن قواعد الـ Environment زي ما هي: Required reviewer = إنت، Deployment tags = `v*`.

**7.2 الكود**
3. التعديلات دي لسه مش committed. اعمل commit على `feature/multi-tenant` وبعدين `git push origin feature/multi-tenant` (ده بيشغّل `ci.yml` بس؛ `deploy.yml` بيشتغل على `main`/`master` بس). استنى الـ CI يخضر.

**7.3 nginx لـ `www` (قبل أو بعد الـ deploy، مستقل عنه)**
4. `dig +short www.ibraa.app` لازم يرجّع الخادم (سجل الـ DNS الـ wildcard بيغطيه).
5. القالب `scripts/ops/templates/nginx-site.conf.tmpl` اتغير (301 من `www.<domain>` للـ apex على 80 و443 بنفس الشهادة الـ wildcard). ارفع نسخة `scripts/ops` الجديدة وأعد الخطوة 70 بس:
   ```bash
   # من جهازك (جذر الريبو):
   scp -r scripts/ops opsadmin@<server>:~/ops-new
   # على الخادم كـ opsadmin:
   sudo rsync -a --chown=root:root ~/ops-new/ /root/ops/ && rm -rf ~/ops-new
   sudo bash /root/ops/provision.sh --env-file /root/sroor-provision.env --only 70-nginx   # بيعمل nginx -t قبل الـ reload
   curl -sSI http://www.ibraa.app/x  | grep -iE '^(HTTP|location)'    # 301 -> https://ibraa.app/x
   curl -sSI https://www.ibraa.app/x | grep -iE '^(HTTP|location)'    # 301 -> https://ibraa.app/x
   ```

**7.4 الـ tag**
6. من جهازك:
   ```bash
   git fetch origin
   git log -1 --oneline origin/feature/multi-tenant          # الـ commit اللي هيتنشر
   git tag -a v0.1.0-rc.1 origin/feature/multi-tenant -m "SaaS first deploy (rehearsal)"
   git push origin v0.1.0-rc.1                                # الـ tag ده بس
   ```
   لو احتجت تعيد بعد إصلاح: tag جديد (`v0.1.0-rc.2`)، ماتحركش tag اتعمله push. لو الفشل مؤقت (شبكة) ممكن «Re-run failed jobs» لنفس الـ run.

**7.5 الموافقة ومتابعة الـ log**
7. Actions → `release` → run الـ tag: `tests` ثم `build` لازم يخضروا، و`deploy` يقف على «Waiting» ← **Review deployments** ← `production` ← **Approve**.
8. المتوقع في الـ log: خطوة «Release mode» تطبع `release mode: rehearsal` مع annotation أصفر `REHEARSAL MODE`؛ الـ render يطبع `WARN  rehearsal: empty and waived: ...` و`MAIL_MAILER=log` و`BACKUP_DISKS=local`؛ البوابة `PASS ... meets the REHEARSAL standards only`؛ و`deploy.sh`: `REHEARSAL MODE` ← `first release on this server: no pre-migration backup` ← migrate ← grants ← `tenants:migrate` ← relocate ← caches ← smoke ← switch ← `horizon:terminate` ← health + `/up` ← `release ... is live` ← `REHEARSAL MODE: release ... is live`.
9. لو فشل: exit 1 = مفيش حاجة اتغيرت (صلّح واعمل tag جديد)؛ exit 4 في أول release = `current` اتشال (مفيش release سابق نرجع له)، الموقع مش هيشتغل لحد release ناجح. الأكواد في §2.

**7.6 على الخادم بعد النجاح** (SSH بالمفتاح؛ `sroor` للأوامر العادية و`opsadmin` لـ `sudo`). ماتعملش `cat` لـ `shared/.env`:
   ```bash
   readlink /var/www/sroor/current
   tail -n 3 /var/www/sroor/shared/deploy-history.log                  # ... deployed-rehearsal
   stat -c '%a %U' /var/www/sroor/shared/.env                           # 600 sroor
   grep -c '^RELEASE_REHEARSAL=true$' /var/www/sroor/shared/.env        # 1
   cd /var/www/sroor/current/backend
   php artisan health:check          # Release Rehearsal = warning، Backup Freshness أحمر لحد أول backup، الباقي ok
   php artisan backup:tenants        # أول backup (مشفّر، local) -> exit 0
   ls -l /var/www/sroor/shared/storage/app/private/sroor-backups/
   php artisan central:create-super-admin <email>   # لو مفيش super-admin؛ الباسورد بيتسأل تفاعليًا، و2FA بتطبيق authenticator مش بالإيميل
   curl -sS -o /dev/null -w '%{http_code}\n' https://ibraa.app/up      # 200
   # كـ opsadmin:
   sudo supervisorctl status                                            # horizon RUNNING
   sudo bash /root/ops/verify.sh --env-file /root/sroor-provision.env   # كله PASS + سطر WARN REHEARSAL MODE
   ```
   بعدها: checklist البروفة في §5 (release تاني ← الـ backup قبل الـ migrate بيشتغل على القرص المحلي، rollback يدوي...).

**7.7 الخروج من البروفة (قبل أول عميل):** [`secrets.md`](secrets.md) §2.1 — ضيف أسرار SMTP وTelegram وDrive ← امسح `RELEASE_REHEARSAL` ← tag جديد (`v0.1.0` مثلًا) لازم يعدّي الوضع الصارم.

## 8. Migrations تقيلة على محل كبير (MIG-01 / MIG-02)

- أي migration بتلمس `invoices` / `invoice_items` / `stock_movements` / `payments` / `store_stocks` لازم تستخدم `App\Support\Database\OnlineMigration`:
  - إضافة عمود + FK: `OnlineMigration::addNullableForeignKey()` (عمود `ADD COLUMN` فوري، ثم FK بـ `foreign_key_checks=0` + `ALGORITHM=INPLACE, LOCK=NONE`). ممنوع `foreignId()->constrained()` على الجداول دي (بيعمل نسخ كامل للجدول ويوقّف الإدخال).
  - backfill: `OnlineMigration::forEachIdRange()` (دفعات 2000 id، كل دفعة transaction قصيرة). ممنوع UPDATE واحد على الجدول كله.
  - أي backfill بيلمس أرصدة المخزون: دفعات + `lockForUpdate` على الأصناف ثم `store_stocks` + كتابة نسبية (`quantity = quantity + ?`) + سطر `stock_movements` لكل فرق.
- `withShortLockTimeout()` بتضبط `lock_wait_timeout=5` للجلسة وبتعيد المحاولة (1205/1213) وبترجّع القيمة القديمة.
- النشر على محل كبير (أكتر من ~100 ألف فاتورة) في وقت هادي؛ بعد النشر شغّل `stock:report-drift` على كل المحلات وراجع أي فرق نُقل للفرع الرئيسي.
- مثال لعدم الاعتماد على الذاكرة: فحص CI/lint يمنع `constrained()` على الجداول الكبيرة لسه متعملش (متابعة).

## §2b. تهجير المستأجرين على نطاق كبير (OPS-25)

**السياسة: الكود الجديد مبيتفعّلش (`current` مبيتبدّلش) إلا لما كل المستأجرين الجاهزين ينجحوا.** لو محل رقم 7 من 50 فشل: المحلات الباقية بتتهجّر برضه (مفيش وقوف عند أول فشل)، التقرير النهائي بيسمّي الفاشل، الـ release بيتلغي (exit 1)، والمحلات كلها شغالة على الكود القديم فوق schema جديدة. بعد علاج المحل (شوف `shared/tenant-migrate.log`) أعد تشغيل الـ deploy: المحلات المتهجّرة بتخلص في ثواني.

**قاعدة expand/contract (إلزامية):** كل tenant migration لازم تكون متوافقة مع الكود **السابق** (إضافة أعمدة/جداول nullable أو بـ default فقط). أي حذف/إعادة تسمية/تضييق عمود يتأجل لـ release لاحق بعد ما الكود القديم يخرج من الخدمة. وكل migration لازم تكون idempotent (`hasColumn`/`hasTable`/`hasIndex`) لأن الإعادة بعد قطع في النص ممكنة. الـ backfill على جداول كبيرة بالـ chunks، مش في ALTER واحد طويل.

**الزمن:** بيتقدّر بـ (عدد المحلات × زمن أبطأ محل) + `backup:tenants` قبلها. مرجع القياس (tmpfs، دفعة 39 migration): حوالي 30 ث لمحل متوسط، 3 د 21 ث لمحل كبير. قرص الإنتاج أبطأ. الـ workflow بيسجّل `deploy duration` في الـ log وفي `GITHUB_STEP_SUMMARY`؛ سجّل الرقم الحقيقي بعد أول deploy على staging بقرص حقيقي وعدّل `TENANT_MIGRATE_TIMEOUT_SECONDS` على أساسه.

**حدود:** `timeout-minutes` للـ job = 180، والانتظار جوه `deploy.sh` ≤ 7200 ث. لو اتجاوز، الـ migration **مبتتقتلش**؛ الـ deploy بيفشل برسالة واضحة، سيب شوية وقت وأعد تشغيله (بيتصل بالـ run الشغال). الـ timeout لكل محل بيبعت SIGTERM ثم SIGKILL بعد `--grace` (30 ث)؛ MySQL بيكمّل أو بيرجّع الـ DDL من ناحيته، والإعادة آمنة بشرط الـ idempotency فوق.

**زر «run-migrations» في لوحة المشغّل:** لسه synchronous جوه الـ HTTP request (حد 120 ث). محل كبير ممكن يتقطع؛ استخدم `php artisan tenants:migrate-all --tenants=<id> --force` من السيرفر لحد ما يتحوّل لـ Job (OPS-25 البند 4، لسه).

**Checklist staging (5 محلات، محل منهم migration فاشلة عمدًا):** الباقي يتهجّر، التقرير يسمّي الفاشل، `current` مبيتبدّلش، وإعادة التشغيل بعد العلاج تنجح؛ وقتل `artisan` في نص migration ثم إعادة الـ deploy بينجح ومفيش `.deploy.lock` متبقي.
