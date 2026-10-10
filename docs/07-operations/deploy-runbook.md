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
| `tests` | PHP **8.4** (نفس الـ VPS): `composer install` ← `php artisan test` (sqlite) ← `scripts/ops/tests/run-tests.sh` ← `render-env-test.sh` ← `deploy-test.sh` |
| `build` | `composer install` (كامل، عشان `lang:export`) ← `npm ci && npm run build` ← `composer install --no-dev --optimize-autoloader` ← tar لـ `backend/` + `scripts/ops/` بس (من الـ checkout، فمفيش scratch scripts ولا backups ولا `*.py`)، من غير `node_modules`/`tests`/`android`/`storage`/`.env*`/caches الـ build. بيتأكد إن مفيش `.env` ولا `vendor/phpunit` جوه، ويطلع sha256. الـ artifact بيتحفظ 7 أيام |
| `deploy` | `environment: production` (موافقة). checksum ← `render-env.sh` للـ `.env` ← **بوابة `check-env.sh`** ← `render-env.sh` لـ `deploy.env` (الـ migrator) ← SSH config من الـ secrets (`StrictHostKeyChecking yes` بـ known_hosts مثبّت، `BatchMode`، مفيش passwords) ← رفع الـ artifact والملفين و`scripts/ops` لـ `<APP_ROOT>/incoming/<sha>-<run>-<attempt>/` (`umask 077`) ← `ssh ... bash -s` يشغّل `deploy.sh` ويمسح مجلد الرفع في كل الحالات ← مسح الأسرار من الـ runner (`if: always()`) |

**بوابة الـ preflight** (في الـ runner وتاني على الخادم، `check-env.sh`) بتفشل الـ release لو: `TELESCOPE_ENABLED` مش `false` (أو غايب)، `BACKUP_ARCHIVE_PASSWORD` فاضي (**قرار D4**)، أي `MAIL_*` ناقص أو `MAIL_MAILER` مش `smtp` (**W1 Q6**)، `DB_AUDIT_PRUNER_*` ناقص، `APP_DEBUG` مش `false`، `SENTRY_SEND_DEFAULT_PII` مش `false`، وباقي معايير `vps-runbook.md` §8. `SENTRY_LARAVEL_DSN` فاضي = تحذير بس.

---

## 2. `scripts/ops/deploy.sh` — الترتيب على الخادم

بيشتغل كمستخدم التطبيق (`sroor`)، `set -euo pipefail`، مفيش `|| true`، ومفيش `set -x`. اسم الـ release: `releases/<UTC YYYYmmddHHMMSS>-<sha12>` (بيسمح بإعادة نشر نفس الـ commit لتدوير سر من غير ما نلمس مجلد شغّال).

| # | المرحلة (`STAGE`) | الخطوة | لو فشلت |
|---|---|---|---|
| 0 | — | lock: `mkdir <APP_ROOT>/.deploy.lock` (deploy واحد في المرة) | exit ≠ 0، مفيش أي تغيير |
| 1 | `preflight` | الـ layout موجود؛ الـ artifact: sha256، فيه `backend/artisan`، مفيش مسارات مطلقة أو `../`، مفيش `backend/.env`؛ `check-env.sh` على الـ `.env` الجديد (أو `shared/.env`)؛ ملفات MySQL option (600) للـ migrator والـ app والـ pruner في مجلد مؤقت خاص | exit 1 |
| 2 | `extract` | `tar -xzf` في `releases/<id>`؛ حذف `storage/` و`.env` وcaches الـ build اللي جت مع الـ artifact | exit 1، الـ release يتمسح |
| 3 | `env` | لو فيه `--env-file`: نسخة من `shared/.env` الحالي ← `shared/.env.previous`، والجديد يتركّب ذريًا (600)؛ ثم `backend/.env` ← `shared/.env` و`backend/storage` ← `shared/storage` (symlinks) | exit 1 + استرجاع `.env` |
| 4 | `storage-link` | `php artisan storage:link --force` (روابط `public/storage` و`public/central-assets` لازم في **كل** release لأن `public/` جزء منه) | exit 1 |
| 4b | `pre-migration-backup` | **OPS-5:** `php artisan backup:tenants --no-cleanup` من الـ release الجديد (الـ config مش متخزن لسه، بيقرا `shared/.env`): المركزي + كل مستأجر مش archived، مشفّر AES-256، مرفوع على Google Drive ومتحقق منه (sha256) ومتسجل في `tenant_backups`. **مابيتعملش في أول release** على الخادم (مفيش بيانات). طوارئ بس: `--skip-pre-migration-backup` (بيتسجل `pre-migration-backup-skipped` في `deploy-history.log`). التفاصيل في [`backup-restore.md`](backup-restore.md) | exit 1، مفيش migration اتعملت |
| 5 | `migrate-central` | `php artisan migrate --force` بحساب **`sroor_migrator`** (`DB_USERNAME`/`DB_PASSWORD` كمتغيرات بيئة للعملية دي بس؛ الـ config مش متخزن لسه) | exit 1، `tenants:migrate` مايشتغلش |
| 6 | `central-grants` | `sync-central-grants.sh` كـ migrator: صلاحيات الجداول لعقد الـ audit الـ append-only، وكل من `app` و`audit_pruner` يتحقق من `SHOW GRANTS` بتاعه (`vps-runbook.md` §5.1) | exit 1 |
| 7 | `migrate-tenants` | `php artisan tenants:migrate --force` — **أي مستأجر يفشل يوقف الـ release** | exit 1 |
| 8 | `caches` | `config:cache` (والملف `chmod 600` لأنه فيه الأسرار) ← `route:cache` ← `view:cache` ← `event:cache` | exit 1 |
| 9 | `smoke` | `php artisan health:check --fail-command-on-failing-check` **من الـ release الجديد قبل التبديل**. لو مفيش ولا check مسجّل (`spatie/laravel-health`) يفشل، إلا مع `--allow-no-health-checks` | exit 1 |
| 10 | `switch` | `ln -sfn releases/<id> current.tmp && mv -Tf current.tmp current` (rename ذري) ← `sudo -n /usr/bin/systemctl reload php8.4-fpm` (الأمر الوحيد المسموح في sudoers، لتفريغ opcache) | rollback تلقائي |
| 11 | `queue-restart` | `php artisan horizon:terminate` (أو `queue:restart` مع `--queue-mode worker`)؛ supervisor يشغّل Horizon من `current` الجديد | rollback تلقائي |
| 12 | `health` | `health:check` من `current` + `GET <APP_URL>/up` (route ‏`/up` المدمج في Laravel، **مفيش route جديد**) على nginx المحلي (`curl --resolve <host>:443:127.0.0.1`)، 10 محاولات | rollback تلقائي |
| 13 | `cleanup` | `.previous_release` = السابق؛ إبقاء آخر `--keep` (5) releases، و`current` والسابق عمرهم ما يتمسحوا؛ سطر في `shared/deploy-history.log` | — |

**أكواد الخروج:** `0` نجح؛ `1` فشل قبل التبديل (المستخدمين ماشافوش حاجة، الـ release اتمسح، `.env` اترجع)؛ `3` فشل بعد التبديل و**اترجع تلقائيًا** للسابق؛ `4` الـ rollback نفسه محتاج تدخل (مثلًا أول release فشل بعد التبديل: `current` بيتشال)؛ `2` استخدام غلط.

**الـ health check:** **[OPS-5/OPS-7]** الفحوص متسجلة في `App\Providers\HealthServiceProvider` (الجدول الكامل في [`backup-restore.md`](backup-restore.md) §7). `deploy.sh` بيعمل `export SROOR_HEALTH_DEPLOY_GATE=1`، فـ `health:check` في المرحلتين 9 و12 (وفي الـ rollback) بيشغّل **فحوص الـ release بس**: DB المركزي، الكاش، Redis، المساحة (فشل فوق 90% بس)، و`BACKUP_ARCHIVE_PASSWORD` (D4)، ومن غير «warning» (spatie بيعتبر الـ warning فشل مع `--fail-command-on-failing-check`). Horizon والـ queue والـ scheduler وعمر آخر backup **مش** في البوابة: بيعتمدوا على الـ cron/supervisor مش على الـ release، ولو دخلوا كان أول deploy هيبقى مستحيل وrelease سليم ممكن يترجع وHorizon بيعيد التشغيل؛ بيتراقبوا بـ `health:check` المجدول كل 5 دقايق (إيميل). `--allow-no-health-checks` لسه موجود للبروفة بس، ومابقاش له لازمة بعد OPS-5.

---

## 3. الأسرار أثناء الـ deploy

- `production.env` و`deploy.env` بيتعملوا في الـ runner بـ `render-env.sh` (`umask 077`)، ويترفعوا في مجلد `incoming/...` بصلاحية 700، ومجلد الرفع بيتمسح بـ `trap` في آخر الـ SSH session مهما حصل. الـ runner بيمسح نسخه في خطوة `if: always()`.
- كلمة مرور الـ migrator **عمرها ما بتدخل `shared/` ولا الـ release** (`deploy-test.sh` بيتأكد).
- MySQL بياخد الباسوردات من option files (600) في مجلد مؤقت خاص بيتمسح على الخروج — مش من argv ولا من `MYSQL_PWD`.
- الاستثناء الوحيد: `php artisan migrate` بياخد باسورد الـ migrator كمتغير بيئة للعملية دي (مقروء لنفس المستخدم وroot بس في `/proc/<pid>/environ`).

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

## 6. اختبار محلي

```bash
bash scripts/ops/tests/deploy-test.sh
```

offline بالكامل: stubs لـ `php` و`mysql` و`sudo` و`curl` في أول الـ PATH، و`APP_ROOT` مؤقت. يغطي: أول deploy وتاني، الترتيب الكامل للأوامر، **[OPS-5]** الـ backup قبل الـ migrations (مش في أول release، فشله يوقف الـ release من غير أي migration، و`--skip-pre-migration-backup` بيتسجل)، وإن كل استدعاء artisan بـ `SROOR_HEALTH_DEPLOY_GATE=1`، ورفض الـ preflight لـ `DB_BACKUP_PASSWORD` فاضي و`GOOGLE_DRIVE_REFRESH_TOKEN` فاضي و`BACKUP_DISKS=local`، الحساب المستخدم في كل migrate، فشل قبل التبديل (central migrate، `tenants:migrate`، `config:cache`، مفيش health checks، صلاحيات audit غلط)، rollback تلقائي (health بعد التبديل، `/up`، restart الـ queue)، أول release يفشل بعد التبديل، رفض الـ preflight (Telescope، D4، SMTP، pruner، `APP_DEBUG`، `.env` جوه الـ artifact، checksum، sha غلط)، الـ lock، الـ rollback اليدوي والرجوع منه، الـ pruning من غير ما يمشي ورا الـ symlinks، وإن مفيش سر ظهر في أي output.
