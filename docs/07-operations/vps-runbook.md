# VPS runbook — تهيئة خادم الإنتاج (OPS-1)

> المرجع التنفيذي لتهيئة الـ VPS الإنتاجي للـ SaaS من الصفر. السكريبتات في `scripts/ops/` (idempotent، تقرأ كل شيء حساس من ملف env على الخادم). هذا الملف **لا يحتوي أي سر ولا أي عنوان خادم**، ويجب أن يبقى كذلك.
>
> المهمة: Phase 1 · W1 · **OPS-1** (`docs/05-planning/phase-1-plan.md` §4.13). يعتمد عليها: OPS-2 (provisioning المستأجر)، OPS-3 (الـ deploy)، OPS-4 (الأسرار)، OPS-5 (الـ backup)، OPS-6a (Redis cache).

---

## 1. النطاق والقرارات المعتمدة

| البند | القرار |
|---|---|
| المزوّد | **Hetzner** Cloud، ≈ 4 vCPU / 8GB RAM / 160GB (Q-O1) |
| نظام التشغيل | **Ubuntu 24.04 LTS** (السكريبتات ترفض غيره) |
| بيئة البروفة (Q-O5) | البروفات على **نفس الـ VPS الإنتاجي** ببيانات اختبار ← **مسح كامل** ← go-live. VPS staging صغير بعد أول عميل مدفوع |
| الدومين (Q-R1) | دومين المنصة من `PLATFORM_DOMAIN` فقط (لا hardcode). شهادة **wildcard** واحدة: `<domain>` + `*.<domain>` (كل محل subdomain، والـ super-admin على `admin.<domain>`) |
| الأسرار | **جديدة بالكامل**. لا يُعاد استخدام أي قيمة من الريبو أو تاريخه أو الخادم القديم (كلها تُعتبر مسرّبة لأن الريبو public) |
| الـ queue | **Horizon** تحت supervisor (`laravel/horizon` من PKG-1). بديل مؤقت: `queue:work` (`QUEUE_MODE=worker`) |
| Telescope / Pulse | `TELESCOPE_ENABLED=false` في production (يُفحص آليًا). Pulse خلف الـ gate الحالي للـ super-admin فقط |
| PHP | **[CTO-2026-10-09] W1 Q6:** **PHP 8.4** (Ubuntu 24.04 فيه 8.3 بس). المصدر: PPA ‏`ondrej/php` (مفيش apt repo رسمي من php.net؛ الـ PPA ده بيصونه مسؤول حزم PHP في Debian). الـ release في الـ CI بيتبني على نفس الإصدار |
| MySQL | **[CTO-2026-10-09] W1 Q6:** **MySQL 8.4 LTS** من الـ repo الرسمي `repo.mysql.com` (component ‏`mysql-8.4-lts`)، ومفتاح التوقيع بيتفحص بالـ fingerprint قبل ما apt يثق فيه. نفس إصدار job ‏`mysql` في الـ CI |
| الـ audit | **append-only على مستوى الـ DB** (IDEN-1.15، W1 Q2): مستخدم `app` مالوش `UPDATE`/`DELETE` على `central_audit_logs` و`activity_log`؛ الـ prune بمستخدم منفصل `audit_pruner` (§5.1) |
| البريد | **SMTP إلزامي** (W1 Q6) عن طريق Brevo أو Amazon SES SMTP (الـ CTO بيعمل الحساب). أي `MAIL_*` ناقص يوقف الـ release (`render-env.sh` + `check-env.sh`) |
| Sentry | الـ DSN في `.env` الـ VPS **بس** (بيوصل له من GitHub Environment secret)، `SENTRY_SEND_DEFAULT_PII=false`. فاضي = تحذير مش فشل لحد OPS-7 |
| الـ backup | `BACKUP_ARCHIVE_PASSWORD` فاضي (قرار D4) = الـ backups ترفض تشتغل، والـ deploy preflight يفشل، والـ health check أحمر؛ التطبيق نفسه يقوم عادي |

ما لا يدخل في OPS-1: ملء `.env` الإنتاجي من GitHub Secrets (OPS-4)، الـ release pipeline (OPS-3، [`deploy-runbook.md`](deploy-runbook.md))، إنشاء DB المستأجر تلقائيًا (OPS-2)، الـ backups (OPS-5).

---

## 2. المتطلبات المسبقة (بيد الـ CTO، بالترتيب)

1. **مفتاحا SSH جديدان** على جهازك (لا كلمات مرور):
   - مفتاح الإدارة: `ssh-keygen -t ed25519 -f ~/.ssh/sroor_admin -C "sroor-admin"`
   - مفتاح الـ deploy (لـ GitHub Actions لاحقًا في OPS-3): `ssh-keygen -t ed25519 -f ~/.ssh/sroor_deploy -C "sroor-deploy"`
   - الملفات `.pub` فقط تُنسخ للخادم. المفتاح الخاص للـ deploy يذهب لـ GitHub Environment secret (OPS-4)، لا لأي ملف في الريبو.
2. **إنشاء الخادم على Hetzner**: Ubuntu 24.04، اختر مفتاح `sroor_admin.pub` عند الإنشاء (فلا تُرسل كلمة مرور root بالبريد). يُفضّل تفعيل **Hetzner Cloud Firewall** (22/80/443 فقط) كطبقة ثانية فوق ufw، و**Backups/Snapshots** من Hetzner.
3. **DNS** للدومين: سجلات `A` (و`AAAA` إن وُجد IPv6) لـ `<domain>` و`*.<domain>` تشير لعنوان الخادم.
4. **توكن DNS API** لمزوّد الـ DNS، صلاحيته **تعديل DNS لهذه الـ zone فقط** (لـ certbot DNS-01). **TODO(CTO): تأكيد مزوّد الـ DNS** — المدعوم: `cloudflare`، `digitalocean`، `linode`، `ovh`، `rfc2136`.
5. بريد لتنبيهات Let's Encrypt (`CERTBOT_EMAIL`).
6. **[CTO-2026-10-09]** حساب SMTP (**Brevo** أو **Amazon SES**) بدومين مرسل موثَّق (SPF/DKIM)، وحساب **Sentry** (free tier). القيم تروح GitHub Environment `production` بس (`secrets.md` §2).
7. **[CTO-2026-10-09]** GitHub Environment **`production`** (reviewer = الـ CTO، deployment rule على tags `v*`) — شرط للـ release (OPS-3).

---

## 3. محتويات `scripts/ops/`

| الملف | الغرض |
|---|---|
| `provision.sh` | المنسّق: `--list`، `--env-file`، `--only STEP`، `--from STEP`. يرفض ملف env غير مملوك لـ root أو ليس `600` |
| `provision.env.example` | كل المتغيرات (الأسرار فارغة). يُنسخ للخادم كـ `/root/sroor-provision.env` |
| `steps/NN-*.sh` | خطوة لكل مكوّن (الجدول في §4.3). كل خطوة idempotent وتعمل منفردة |
| `lib/common.sh` | logging، تحقق المتغيرات والأسرار، parser آمن لـ `.env` (لا ينفّذ محتوى الملف)، render للقوالب، تثبيت الملفات فقط عند التغيير |
| `lib/mysql-grants.sh` | **مصدر الحقيقة الوحيد** لصلاحيات MySQL (§5) |
| `templates/` | nginx، php-fpm، php.ini، supervisor، cron، redis، mysql، sshd، fail2ban، sudoers، `production.env.example` |
| `check-env.sh` | يفحص `.env` الإنتاجي مقابل معايير الإنتاج **دون طباعة أي قيمة** (يعيد استخدامه OPS-3 كبوابة release) |
| `verify.sh` | فحوص القبول بعد التهيئة (read-only): الخدمات، SSH، الـ firewall، المنافذ، PHP 8.4، MySQL 8.4، `SHOW GRANTS`، عقد الـ audit (§5.1)، الشهادة، nginx |
| `sync-central-grants.sh` | **[CTO-2026-10-09]** صلاحيات الجداول المركزية لعقد الـ audit الـ append-only (§5.1)؛ بيشغّله `deploy.sh` (كـ migrator) و`41-mysql-users` (كـ root) و`verify.sh` (`--check-only`) |
| `deploy.sh` | **[CTO-2026-10-09]** الـ release على الخادم (OPS-3): [`deploy-runbook.md`](deploy-runbook.md) |
| `templates/deploy.env.example` | **[CTO-2026-10-09]** مفاتيح الـ deploy بس (`DB_MIGRATOR_*`)، مش جزء من `.env` التطبيق |
| `tests/run-tests.sh` | اختبارات offline للسكريبتات (§13) |
| `tests/deploy-test.sh` | **[CTO-2026-10-09]** اختبارات `deploy.sh` offline (§13) |

قواعد ثابتة في كل السكريبتات: `set -euo pipefail`، لا `|| true`، لا `set -x`، لا كلمة مرور في سطر الأوامر (`MYSQL_PWD`/`REDISCLI_AUTH`/stdin بدلًا منها)، لا `curl | sh`.

---

## 4. التهيئة خطوة بخطوة

### 4.1 رفع السكريبتات وملف الإعدادات

من جهازك (من جذر الريبو):

```bash
scp -r scripts/ops root@<server>:/root/ops
scp ~/.ssh/sroor_admin.pub ~/.ssh/sroor_deploy.pub root@<server>:/root/keys/
```

على الخادم (كـ root):

```bash
cp /root/ops/provision.env.example /root/sroor-provision.env
chmod 600 /root/sroor-provision.env
nano /root/sroor-provision.env      # PLATFORM_DOMAIN, CERTBOT_EMAIL, CERTBOT_DNS_PLUGIN ...

# توليد الأسرار على الخادم مباشرة، دون طباعتها:
for key in MYSQL_APP_PASSWORD MYSQL_MIGRATOR_PASSWORD MYSQL_PROVISIONER_PASSWORD MYSQL_BACKUP_PASSWORD MYSQL_AUDIT_PRUNER_PASSWORD REDIS_PASSWORD; do
  sed -i "s/^${key}=.*/${key}=$(openssl rand -hex 32)/" /root/sroor-provision.env
done

# توكن الـ DNS في ملف خاص (صيغة الملف حسب الـ plugin، مثال cloudflare):
install -d -m 700 /root/.secrets
install -m 600 /dev/null /root/.secrets/certbot-dns.ini
nano /root/.secrets/certbot-dns.ini   # dns_cloudflare_api_token = <token>
```

> الأسرار تُنقل لاحقًا لـ GitHub Environment secrets (OPS-4) **بالنسخ المباشر من الخادم إلى واجهة GitHub**، لا عبر chat أو مستندات.

### 4.2 التشغيل

```bash
cd /root/ops
bash provision.sh --list
bash provision.sh --env-file /root/sroor-provision.env
```

**احتياط القفل خارج الخادم:** خطوة `10-ssh-users` تُغلق دخول root وكلمات المرور. **أبقِ جلسة root الحالية مفتوحة**، وفي terminal جديد تأكد من `ssh -i ~/.ssh/sroor_admin opsadmin@<server>` قبل إغلاقها. ثم عيّن كلمة مرور sudo للـ admin من جلسة root: `passwd opsadmin` (تفاعليًا، لا تُحفظ في أي ملف). كونسول Hetzner هو طريق الطوارئ.

### 4.3 الخطوات

| الخطوة | ماذا تفعل |
|---|---|
| `00-base` | تحقق Ubuntu 24.04، `apt upgrade`، أدوات أساسية، timezone (UTC)، swap، **unattended security upgrades** |
| `10-ssh-users` | `opsadmin` (sudo) و`sroor` (حساب الخدمة + الـ deploy) بمفاتيح فقط؛ `sshd_config.d/00-sroor-hardening.conf`: `PermitRootLogin no`، `PasswordAuthentication no`، `KbdInteractiveAuthentication no`، `AllowUsers`؛ `sshd -t` قبل أي reload؛ sudoers محدود لـ `sroor` (reload php-fpm + برامج supervisor الخاصة به فقط) |
| `20-firewall` | ufw: deny incoming، `limit` على منفذ SSH، 80، 443؛ fail2ban (`sshd` + `recidive`) |
| `30-php` | **PHP 8.4** FPM من PPA ‏`ondrej/php` (`add-apt-repository`، المفتاح من Launchpad عبر HTTPS) + `bcmath intl pdo_mysql redis gd zip mbstring xml curl opcache`؛ `update-alternatives` يثبّت `/usr/bin/php` على 8.4؛ pool مخصص `sroor` على `/run/php/sroor-fpm.sock`؛ `opcache.validate_timestamps=0` (الـ deploy يعمل reload) |
| `40-mysql` | **MySQL 8.4 LTS** من `repo.mysql.com` (component ‏`mysql-8.4-lts`، مفتاح `RPM-GPG-KEY-mysql-2023` يُرفض لو الـ fingerprint ≠ `MYSQL_APT_KEY_FINGERPRINT`، pin أولوية 1001)؛ يرفض سيرفر عليه إصدار تاني؛ على `127.0.0.1` فقط، `mysqlx=OFF`، `local_infile=0`، utf8mb4، حذف الحسابات المجهولة؛ root يبقى `auth_socket` (لا كلمة مرور) |
| `41-mysql-users` | DB المركزي + الحسابات الخمسة بأقل صلاحية (§5)، ثم **يقارن `SHOW GRANTS` بالـ runbook ويفشل عند أي اختلاف**، ثم يعيد صلاحيات الجداول للـ audit append-only (`sync-central-grants.sh --as-root`، §5.1) |
| `50-redis` | Redis على loopback، `requirepass`، `maxmemory-policy noeviction` (لا تُفقد jobs)، `appendonly yes` |
| `60-tls` | certbot DNS-01 لـ `<domain>` + `*.<domain>`، hook يعمل reload لـ nginx بعد كل تجديد، `certbot.timer` |
| `70-nginx` | HTTPS فقط؛ 80 ← 301؛ host غير معروف ← رفض (`444` / `ssl_reject_handshake`)؛ root = `current/backend/public`؛ **`index.php` فقط ينفَّذ** (أي `*.php` آخر في `public/` ← 404)؛ HSTS؛ منع الـ dotfiles؛ حد الـ body ‏`25m` لكل المسارات **ما عدا** `location = /api/v1/super-admin/app-versions` ‏(`160m`، رفع APK حتى 150 MB حسب `StoreAppVersionRequest`)، وPHP على `upload_max_filesize=155M` / `post_max_size=160M` (nginx هو البوابة الفعلية) |
| `80-queue` | supervisor: برنامج `sroor-horizon` (أو `sroor-worker`)، `stopwaitsecs=3600` |
| `85-scheduler` | `/etc/cron.d/sroor-scheduler`: `schedule:run` كل دقيقة كمستخدم `sroor` |
| `90-app-layout` | `/var/www/sroor/{releases,shared/storage,shared/.env}`؛ `.env` مملوك لـ `sroor` و`600` |

إعادة خطوة واحدة: `bash provision.sh --env-file /root/sroor-provision.env --only 41-mysql-users`.

---

## 5. مستخدمو MySQL بأقل صلاحية

كلهم `@localhost` (unix socket) — لذا `DB_HOST=localhost` في `.env`. الـ MySQL لا يستمع إلا على `127.0.0.1`.

| الحساب | يستخدمه | الصلاحيات |
|---|---|---|
| `sroor_app` | التطبيق وقت التشغيل (web + Horizon + scheduler) | `SELECT, INSERT` على المركزي (مستوى الـ DB) + `UPDATE, DELETE` **لكل جدول مركزي على حدة ما عدا جداول الـ audit** (§5.1). لا يصل لأي DB مستأجر؛ اتصالات المستأجرين تتم بمستخدم كل مستأجر الذي ينشئه stancl (OPS-2) |
| `sroor_migrator` | `php artisan migrate --force` للمركزي + مزامنة صلاحيات الجداول أثناء الـ deploy (OPS-3) فقط. **ليس في `.env` التطبيق** (ملف `deploy.env` مؤقت، `deploy-runbook.md` §3) | DDL + DML على المركزي فقط، **مع `GRANT OPTION` على المركزي فقط** (عشان يدي صلاحيات الجداول الجديدة لـ `app`/`audit_pruner` من غير root) |
| `sroor_provisioner` | إنشاء/حذف DB المستأجر ومستخدمه (`PermissionControlledMySQLDatabaseManager`، OPS-2) | `CREATE USER` عام + كل صلاحيات مستوى الـ DB على النمط الصارم `tenant\_%` **مع `GRANT OPTION`** + قراءة `mysql.user(Host, User)` فقط |
| `sroor_backup` | `mysqldump --single-transaction` (OPS-5) | `SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER` على المركزي وكل المستأجرين + `PROCESS` |
| `sroor_audit_pruner` | **[CTO-2026-10-09]** الـ prune المجدول لجداول الـ audit الأقدم من سنتين (IDEN-1.15، اتصال Laravel ‏`audit_pruner` بـ `DB_AUDIT_PRUNER_*`) | `SELECT, DELETE` على `central_audit_logs` و`activity_log` **فقط** (لكل جدول، §5.1). مفيش أي صلاحية تانية |

**ناتج `SHOW GRANTS` المتوقع حرفيًا للصلاحيات الثابتة** (بالأسماء الافتراضية؛ لأسماء أخرى: `bash steps/41-mysql-users.sh --print-expected-grants`). الاختبار `tests/run-tests.sh` يفشل إن اختلف هذا البلوك عن `lib/mysql-grants.sh`. صلاحيات الجداول (`ON \`sroor_central\`.\`<table>\``) مش هنا لأنها بتعتمد على الجداول الموجودة: §5.1.

<!-- ops:expected-grants:begin -->
```sql
GRANT USAGE ON *.* TO `sroor_app`@`localhost`
GRANT SELECT, INSERT ON `sroor_central`.* TO `sroor_app`@`localhost`
GRANT USAGE ON *.* TO `sroor_migrator`@`localhost`
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW, TRIGGER ON `sroor_central`.* TO `sroor_migrator`@`localhost` WITH GRANT OPTION
GRANT CREATE USER ON *.* TO `sroor_provisioner`@`localhost`
GRANT ALL PRIVILEGES ON `tenant\_%`.* TO `sroor_provisioner`@`localhost` WITH GRANT OPTION
GRANT SELECT (`Host`, `User`) ON `mysql`.`user` TO `sroor_provisioner`@`localhost`
GRANT PROCESS ON *.* TO `sroor_backup`@`localhost`
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON `sroor_central`.* TO `sroor_backup`@`localhost`
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON `tenant\_%`.* TO `sroor_backup`@`localhost`
GRANT USAGE ON *.* TO `sroor_audit_pruner`@`localhost`
```
<!-- ops:expected-grants:end -->

ملاحظات مُتحقَّق منها على MySQL 8.4 (بخادم مؤقت معزول، انظر §13):

- **`ALL PRIVILEGES` للـ provisioner = بالضبط قائمة stancl** (`ALTER, ALTER ROUTINE, CREATE, CREATE ROUTINE, CREATE TEMPORARY TABLES, CREATE VIEW, DELETE, DROP, EVENT, EXECUTE, INDEX, INSERT, LOCK TABLES, REFERENCES, SELECT, SHOW VIEW, TRIGGER, UPDATE`). هذه كل صلاحيات مستوى الـ DB في MySQL 8، فيخزنها ويعرضها MySQL كـ `ALL PRIVILEGES` على مستوى الـ DB (ليست صلاحيات عامة).
- **fail closed — كل الحسابات على النمط الصارم `tenant\_%`:** في هدف الـ `GRANT` يكون `_` wildcard لحرف واحد. stancl الأصلي ينفّذ `GRANT ... ON \`tenant_<id>\`.*` بلا escape، والـ `id` هو الـ slug (`alpha_dash`، يقبل `_` و`-`). مثال: منحة مستخدم المستأجر `shop_a` على `tenant_shop_a` تطابق أيضًا DB المستأجر `shop-a` ‏(`tenant_shop-a`) ← قراءة/كتابة/`DROP` عبر المستأجرين. لذلك نمط الـ provisioner صارم، وMySQL **يرفض** منحة stancl غير المهرَّبة (`ERROR 1044`) ويقبل المهرَّبة (`tenant\_shop\_a`) — مُتحقَّق منه على 8.4.3. النتيجة: `PermissionControlledMySQLDatabaseManager` الأصلي **يفشل عمدًا** حتى يسلّم OPS-2 الـ manager الذي يهرّب الهدف (انظر §12.2). لا توسّع النمط إلى `tenant_%` أبدًا لتجاوز هذا الفشل. السكريبت يرفض أيضًا أي اسم DB مركزي قد يطابق نمط المستأجرين (مثل `tenantXcentral`).
- **`SELECT (Host, User)` على `mysql.user`** يكفي لـ `userExists()` في stancl، ولا يكشف `authentication_string` (مُختبر: مرفوض).
- **`migrator`:** `app` مالوش DDL، فـ migrations المركزي بتشتغل بحساب `migrator` منفصل يستخدمه الـ deploy بس (OPS-3): كلمة مروره في GitHub secret ‏`DB_MIGRATOR_PASSWORD` وبتوصل الخادم في ملف `deploy.env` مؤقت بيتمسح آخر الـ run، ومش في `.env` التطبيق. `deploy.sh` بيمرّرها لـ `php artisan migrate` كمتغير بيئة للعملية دي بس (الـ config مش متخزن لسه، والمتغير الحقيقي بيغلب `.env`). `GRANT OPTION` على المركزي مايديلوش قوة جديدة على الـ audit (هو أصلًا يقدر `DROP`)، بس بيخليه يقدر يدي صلاحياته لحسابات تانية؛ مقبول لأنه مش موجود وقت التشغيل.

التحقق اليدوي (على الخادم كـ root):

```bash
for u in sroor_app sroor_migrator sroor_provisioner sroor_backup; do
  mysql -N -B -r -e "SHOW GRANTS FOR \`$u\`@\`localhost\`"
done
```

> استخدم `-r` دائمًا: بدونه يضاعف وضع الـ batch الـ backslash (`tenant\\_%`).

تدوير كلمة مرور حساب: غيّر القيمة في `/root/sroor-provision.env` ثم `--only 41-mysql-users` (ينفّذ `ALTER USER` ويعيد ضبط الصلاحيات)، ثم حدّث الـ secret المقابل في GitHub و`shared/.env` (بإعادة تشغيل الـ release). شغّله والـ deploy مش شغال: الـ `REVOKE ALL` بيشيل صلاحيات الجداول لحظيًا لحد ما الخطوة تعيدها.

### 5.1 عقد الـ audit الـ append-only (IDEN-1.15، W1 Q2) — **[CTO-2026-10-09]**

**المطلوب:** مستخدم `app` في production **مالوش `UPDATE`/`DELETE`** على جداول الـ audit المركزية (`CENTRAL_AUDIT_TABLES`، افتراضيًا `central_audit_logs activity_log`)، والـ prune (سنتين) بمستخدم منفصل محدود.

**الدفاتر المالية الـ append-only (ENTI-1.10، W2 batch 2):** القائمة `CENTRAL_APPEND_ONLY_TABLES` (افتراضيًا `tenant_credit_ledger`، دفتر رصيد الـ credits). `app` مالوش `UPDATE`/`DELETE` عليها زي جداول الـ audit، لكن **مفيش prune أبدًا**: `audit_pruner` مالوش أي صلاحية عليها. الجدول ميتكتبش في القائمتين مع بعض (`validate_mysql_names` بيرفض).

**ليه per-table:** MySQL مفيهوش grant بمعنى «كل الـ DB ما عدا جدول» (الـ partial revokes بتشتغل على الصلاحيات العامة بس). فـ `app` واخد `SELECT, INSERT` على مستوى الـ DB، و`UPDATE, DELETE` على كل جدول لوحده ما عدا جداول الـ audit. صلاحية الجدول محتاجة الجدول يكون موجود، فبتتزامن **بعد كل migration مركزية**.

**مين بيزامن:** `scripts/ops/sync-central-grants.sh` (idempotent):

| الوضع | مين | إمتى |
|---|---|---|
| `--client-file migrator.cnf --verify-app app.cnf --verify-pruner pruner.cnf` | `sroor_migrator` (عنده `GRANT OPTION` على المركزي) | كل deploy، بعد `migrate --force` وقبل `tenants:migrate` (`deploy.sh`). أي اختلاف يوقف الـ release قبل تبديل `current` |
| `--as-root` | root بالـ socket | `41-mysql-users` (بعد `REVOKE ALL`)، أو إصلاح يدوي |
| `--as-root --check-only` | root | `verify.sh` (مقارنة بس) |

في كل مرة: كل جدول مركزي مش audit ← `GRANT UPDATE, DELETE ... TO app`؛ كل جدول audit موجود ← `REVOKE IF EXISTS ALL PRIVILEGES ... FROM app` و`FROM audit_pruner` ثم `GRANT SELECT, DELETE ... TO audit_pruner`؛ كل جدول في `CENTRAL_APPEND_ONLY_TABLES` موجود ← `REVOKE IF EXISTS ALL PRIVILEGES ... FROM app` و`FROM audit_pruner` من غير أي `GRANT` بعدها؛ وأي صلاحية جدول متبقية لجدول اتمسح (MySQL بيسيبها بعد `DROP TABLE`) ← `REVOKE IF EXISTS`. بعدها كل حساب يقرأ `SHOW GRANTS` بتاعه بنفسه ولازم يطابق العقد حرفيًا.

**ليه `SELECT` للـ pruner:** MySQL بيطلب `SELECT` على الأعمدة اللي في `WHERE` (`DELETE ... WHERE created_at < ?`)؛ من غيرها الـ prune بيفشل بـ `ERROR 1142` (متحقَّق منه على 8.4.3). الـ pruner مالوش `UPDATE` ولا `INSERT` ولا أي جدول تاني.

**الشكل المتوقع لصلاحيات الجداول** (مثال بجدولين عاديين):

```sql
GRANT UPDATE, DELETE ON `sroor_central`.`tenants` TO `sroor_app`@`localhost`
GRANT UPDATE, DELETE ON `sroor_central`.`domains` TO `sroor_app`@`localhost`
GRANT SELECT, DELETE ON `sroor_central`.`central_audit_logs` TO `sroor_audit_pruner`@`localhost`
GRANT SELECT, DELETE ON `sroor_central`.`activity_log` TO `sroor_audit_pruner`@`localhost`
-- tenant_credit_ledger: ولا سطر (لا app ولا audit_pruner)
```

**Checklist التحقق (على الخادم كـ root):**

```bash
bash /root/ops/sync-central-grants.sh --as-root --check-only     # PASS = مطابق
mysql -N -B -r -e "SHOW GRANTS FOR \`sroor_app\`@\`localhost\`" | grep -E 'central_audit_logs|activity_log|tenant_credit_ledger'   # لازم يطلع فاضي
mysql -N -B -r -e "SHOW GRANTS FOR \`sroor_audit_pruner\`@\`localhost\`"   # USAGE + SELECT, DELETE على جدولي الـ audit بس
```

- [ ] `app`: `INSERT` على جدول الـ audit ينجح، و`UPDATE`/`DELETE`/`TRUNCATE` يترفضوا.
- [ ] `audit_pruner`: `DELETE ... WHERE created_at < ...` ينجح، و`UPDATE`/`INSERT`/قراءة أي جدول تاني يترفضوا.
- [ ] `app`: `INSERT` و`SELECT` على `tenant_credit_ledger` ينجحوا، و`UPDATE`/`DELETE` يترفضوا؛ و`audit_pruner` مالوش `DELETE` عليه.
- [ ] `GRANT UPDATE` يدوي على جدول audit لـ `app` ← أول deploy بعده يشيله.

متحقَّق منه محليًا على MySQL 8.4.3 مؤقت (`run-tests.sh` الجزء الحي، §13)، **مش على الـ VPS**.

**عقد مع الـ lanes التانية:** اتصال Laravel ‏`audit_pruner` (IDEN-1.15) لازم يقرأ `DB_AUDIT_PRUNER_USERNAME` و`DB_AUDIT_PRUNER_PASSWORD` (نفس `DB_HOST`/`DB_PORT`/`DB_DATABASE` المركزي)، وأي كود بيعمل `update()`/`delete()` على `CentralAuditLog` أو `Activity` بالاتصال المركزي العادي هيفشل في production بـ `ERROR 1142` — ده المقصود.

---

## 6. الـ queue والـ scheduler

- **Horizon** (`QUEUE_MODE=horizon`): `supervisorctl status sroor-horizon`. عند كل release ينفّذ OPS-3 `php artisan horizon:terminate`، فيعيد supervisor تشغيله من الـ `current` الجديد. لوحة Horizon خلف `central_web` (IDEN-1.7).
- **بديل** (`QUEUE_MODE=worker`): `sroor-worker` × `QUEUE_WORKER_PROCESSES` بـ `queue:work redis --max-time=3600`؛ الـ deploy ينفّذ `queue:restart`.
- قبل أول deploy يكون البرنامج في `BACKOFF/FATAL` لأن `current` غير موجود — طبيعي.
- **الـ scheduler:** cron كل دقيقة كمستخدم `sroor`. أي job تخص المستأجرين يجب أن تدور عليهم داخل tenancy (`tenancy()->runForMultiple`)، ولا تلمس بيانات مستأجر من السياق المركزي.
- **[OPS-5/OPS-7] الـ backups والـ health:** `backup:tenants` الساعة 01:30، `backup:run --only-files` 02:30، `backup:clean` 03:00، `health:check` كل 5 دقايق، والـ heartbeats (`health:schedule-check-heartbeat` و`health:queue-check-heartbeat`) كل دقيقة. بعد **أول** deploy على الخادم شغّل `php artisan backup:tenants` مرة يدوي (الـ health check `Backup Freshness` بيبقى أحمر لحد أول نسخة). الـ runbook: [`backup-restore.md`](backup-restore.md).
- Redis بـ `noeviction`: إن امتلأت الذاكرة تفشل الكتابة بدل حذف jobs بصمت. راقب `INFO memory` وارفع `REDIS_MAXMEMORY` عند الحاجة.

### 6.1 queue الـ provisioning (OPS-2)

إنشاء مستأجر جديد بقى job في الـ queue (`App\Jobs\ProvisionTenantJob`: `tries=3`، `timeout=900` ثانية). المستأجر بيفضل `provisioning_status=pending` لحد ما worker يشيل الـ job؛ من غير worker على الـ queue الصح **مفيش مستأجر جديد هيشتغل**.

- **connection مخصوص:** `config/queue.php` → `provisioning` (redis، `retry_after` = `TENANT_PROVISIONING_RETRY_AFTER`، الافتراضي 1000، وأقل قيمة 960). لازم `retry_after` > timeout الـ job (900)، وإلا الـ job بيتسلّم مرتين وهو لسه شغال. الـ connection الافتراضي (`retry_after=90`) **مش** مناسب.
- **supervisor في Horizon:** `config/horizon.php` → `supervisor-provisioning` (process واحد، `timeout=930`، `tries=3`) على connection `provisioning` والـ queue `TENANT_PROVISIONING_QUEUE` (الافتراضي `provisioning`). مفيش برنامج supervisor جديد: نفس `sroor-horizon` بيشغّله.
- **الـ `.env` على الـ VPS** (من القالب `scripts/ops/templates/production.env.example`):
  - `TENANT_PROVISIONING_QUEUE_CONNECTION=provisioning`
  - `TENANT_PROVISIONING_QUEUE=provisioning` (نفس اسم queue الـ supervisor)
  - `TENANT_DB_PER_TENANT_USER=true` (**إلزامي** هنا: `sroor_app` مالوش صلاحية على DBs المستأجرين، فكل مستأجر بيتعمله مستخدم MySQL على الـ DB بتاعته بس، §5)
  - `TENANT_DB_USER_HOST=localhost`
  - `DB_PROVISIONER_USERNAME=sroor_provisioner` و`DB_PROVISIONER_PASSWORD` = `MYSQL_PROVISIONER_PASSWORD` من `/root/sroor-provision.env` (§5).
- **ربط الأسرار:** `DB_PROVISIONER_PASSWORD` بيتضاف كـ Environment secret في GitHub (`production`)، ومفتاحه في `production.env.example`، ويتربط في `env:` بتاع خطوة «Render the production .env» في `release.yml` (القاعدة في [`secrets.md`](secrets.md) §2 بند 6). لحد ما يتربط، المفاتيح دي متعلّقة (commented) في القالب والـ provisioning على الـ VPS مش هيشتغل.
- **QUEUE_MODE=worker** (البديل المؤقت): لازم worker منفصل `queue:work provisioning --queue=provisioning --timeout=930 --tries=3`؛ الـ workers العادية مش بتسمع الـ queue دي.
- **محليًا / CI:** المفاتيح فاضية = الـ connection والـ queue الافتراضيين. `scripts/local/provision-local-tenant.php` و`TenantSampleSeeder` بيجهّزوا inline (`sync`)، فـ `QUEUE_CONNECTION=database` من غير worker مابقاش بيسيب الـ demo `pending`.
- **كل loop على «كل المستأجرين» بيتخطى اللي مش `ready`** (مالهمش DB): `tenants:migrate`/`rollback`/`seed`/`run` من غير `--tenants` (subclasses في `app/Console/Tenancy/`، متسجلة في `AppServiceProvider`)، و`backup:tenants`، وفحص `Backup Freshness`، و`tenants:audit-super-admin`. `--tenants`/`--tenant` صريح بيتنفّذ زي ما هو (ده اللي الـ job نفسه بيستخدمه). `tenants:migrate-fresh` ماتغيّرش (مدمّر وبيتشغّل بنية صريحة بس).
- **تحذير: `tenants:migrate-fresh` مش متفلتر** (مش بيتخطّى اللي مش `ready`): بيمسح ويعيد بناء DB **كل** مستأجر، بما فيهم اللي الـ job شغال عليهم دلوقتي. **ممنوع تشغيله على الإنتاج نهائيًا** (القاعدة العامة في `.claude/rules/security-and-operations.md`)؛ محليًا بس، وعلى `--tenants` صريح.
- **مستأجر `failed`:** الصف المركزي بيفضل؛ إعادة المحاولة من الكونسول (`POST /super-admin/tenants/{id}/retry-provisioning`). `provisioning_error_code` بيوضح المرحلة اللي فشلت.
- **مستأجر واقف على `pending`/`running`** (الـ dispatch ضاع، أو الـ worker مات): لو مفيش أي نشاط على الصف (`updated_at` و`provisioning_started_at`) أطول من الـ unique window (`TENANT_PROVISIONING_UNIQUE_FOR`، الافتراضي 3600 ثانية، أقل قيمة 2850) الـ retry بيتقبل كمان، وبيفك الـ unique lock والـ overlap lock قبل ما يبعت الـ job. قبل كده بيرجع 409 (لسه شغال). و`failed()` بيفك الـ overlap lock (`laravel-queue-overlap:App\Jobs\ProvisionTenantJob:<id>`) بنفسه، فالـ retry بعد timeout مابيتبلعش.
- **اللي الـ job بيمسحه عند الفشل:** الـ DB واليوزر اللي **هو** عملهم بس، وبشرط إنهم لسه نفس اسم الـ DB ونفس الـ username الحاليين للمستأجر (الاسم متخزّن، مش flag). لو حد غيّر الاسم أو حوّل الـ username لحساب مشترك، الـ job مابيلمسهمش وبيكتب warning في الـ log بالاسم بس. تعديل `update-db-config` و`run-migrations` و`update-units` على مستأجر مش `ready` بيرجع 409 `provisioning.workspace_not_ready`.
- **باسورد DB المستأجر** (`tenancy_db_password` في `tenants.data`) بيتخزن مشفّر بـ `APP_KEY` (الصفوف القديمة plaintext لسه شغالة). تغيير `APP_KEY` من غير re-encrypt = المستأجرين اللي ليهم يوزر خاص مش هيتصلوا.

---

## 7. شهادة wildcard (DNS-01)

- `60-tls` يطلب شهادة واحدة باسم `<domain>` تغطي `<domain>` و`*.<domain>`. HTTP-01 لا يدعم wildcard، لذلك DNS-01 إلزامي.
- للبروفات: `CERTBOT_STAGING=1` (شهادة غير موثوقة، لتجنب حدود Let's Encrypt)، ثم `CERTBOT_STAGING=0` قبل go-live مع حذف الشهادة التجريبية: `certbot delete --cert-name <domain>` ثم `--only 60-tls`.
- التجديد تلقائي (`certbot.timer`)؛ اختبار: `certbot renew --dry-run`.
- الـ custom domain لكل محل (add-on) مؤجل لـ Phase 3 (Q-E9) ولا يدخل هنا.

---

## 8. معايير الإنتاج و`.env`

`templates/production.env.example` هيكل الـ `.env` الإنتاجي (مفاتيح + قيم آمنة، الأسرار فارغة). الملف الحقيقي `/var/www/sroor/shared/.env` يُملأ في OPS-4 من GitHub Environment secrets.

`check-env.sh` يفشل (دون طباعة أي قيمة) إذا:

| المفتاح | المطلوب |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `TELESCOPE_ENABLED` | `false` صراحةً (غيابه = `true` في `config/telescope.php`) |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` / `redis` |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUICK_LOGIN_ENABLED` | `false` أو غائب |
| `APP_KEY` | موجود (`base64:`) وجديد |
| `APP_URL` | يبدأ بـ `https://` |
| `DB_USERNAME` / `DB_PASSWORD` / `REDIS_PASSWORD` | موجودة، و`DB_USERNAME` ليس `root` |
| `LOG_LEVEL` | ليس `debug` |
| `DB_AUDIT_PRUNER_USERNAME` / `DB_AUDIT_PRUNER_PASSWORD` | **[CTO-2026-10-09]** موجودين، والحساب مش `root` ومش هو `DB_USERNAME` (§5.1) |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_FROM_ADDRESS` | **[CTO-2026-10-09] W1 Q6:** `smtp`، وكلهم موجودين (`MAIL_PORT` رقم، `MAIL_FROM_ADDRESS` إيميل). `render-env.sh` يرفض `--optional` لأي منهم |
| `BACKUP_ARCHIVE_PASSWORD` | **[CTO-2026-10-09] D4:** موجود (فاضي = فشل)؛ أقل من 24 حرف = تحذير |
| `DB_BACKUP_USERNAME` / `DB_BACKUP_PASSWORD` | **[OPS-5]** موجودين، والحساب (`sroor_backup`) مش `root` ومش `DB_USERNAME` |
| `BACKUP_DISKS` / `GOOGLE_DRIVE_CLIENT_ID` / `_CLIENT_SECRET` / `_REFRESH_TOKEN` | **[OPS-5]** `BACKUP_DISKS` (أو `BACKUP_TENANT_DISKS`) لازم فيه `google` (معيار #8: الـ backup يطلع بره الخادم)، والـ 3 مفاتيح OAuth موجودين. `BACKUP_NOTIFICATION_EMAIL` فاضي = تحذير. الإعداد في [`backup-restore.md`](backup-restore.md) §2 |
| `CENTRAL_DOMAIN` / `CENTRAL_ADMIN_DOMAINS` / `CENTRAL_PASSWORD_RESET_URL` | **[W2 batch 3]** الثلاثة موجودين. `CENTRAL_DOMAIN` اسم host بس (من غير `https://` ولا `/`)، والـ tenants على `<slug>.<CENTRAL_DOMAIN>`. `CENTRAL_ADMIN_DOMAINS` فيها host واحد على الأقل (مفصولة بفاصلة)؛ فاضية = كل routes الـ super-admin بترجع 404 في production. `CENTRAL_PASSWORD_RESET_URL` تبدأ بـ `https://` والـ host بتاعها واحد من `CENTRAL_ADMIN_DOMAINS`؛ فاضية = إيميلات استرجاع كلمة سر الـ super-admin مابتتبعتش. الثلاثة GitHub Environment **variables** (`vars.*`) مش secrets |
| `SENTRY_SEND_DEFAULT_PII` | `false` أو غائب. و`SENTRY_LARAVEL_DSN` فاضي = **تحذير** (`WARN`) مش فشل |

```bash
bash /root/ops/check-env.sh /var/www/sroor/shared/.env --check-perms
```

Pulse: يبقى خلف `viewPulse` (super-admin المركزي فقط) — لا تغيير في الكود ضمن OPS-1.

---

## 9. التحقق (معيار القبول)

```bash
bash /root/ops/verify.sh --env-file /root/sroor-provision.env
```

يجب أن تكون كل الأسطر `PASS`: الخدمات (nginx، php-fpm، mysql، redis، supervisor، cron، fail2ban، unattended-upgrades، certbot.timer)؛ `sshd -T` (لا كلمات مرور، لا root)؛ ufw؛ MySQL وRedis على loopback فقط؛ امتدادات PHP؛ **`SHOW GRANTS` لكل مستخدم = §5**؛ الشهادة صالحة 14+ يومًا وتغطي `*.<domain>`؛ `nginx -t`؛ الـ cron والـ supervisor؛ و`check-env.sh` إن كان `.env` مملوءًا.

فحوص خارجية من جهازك:

```bash
ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no root@<server>   # يجب: Permission denied (publickey)
nc -vz <server> 3306 ; nc -vz <server> 6379                                         # يجب: فشل الاتصال
curl -sI http://<domain> | head -1                                                  # 301
curl -sI https://unknown-shop.<domain> | head -1                                    # يصل للتطبيق بعد أول deploy
```

---

## 10. checklist بروفة الـ rehearsal (Q-O5)

- [ ] Snapshot من Hetzner قبل البدء.
- [ ] `provision.sh` كامل بـ `CERTBOT_STAGING=1`، ثم `verify.sh` كله `PASS`.
- [ ] إعادة تشغيل `provision.sh` كاملًا مرة ثانية ← لا أخطاء ولا تغييرات (idempotency).
- [ ] `--only 41-mysql-users` بعد تغيير كلمة مرور ← `verify.sh` لا يزال `PASS` (التدوير يعمل).
- [ ] `ssh root@...` وبكلمة مرور مرفوضان؛ `opsadmin` بالمفتاح يعمل و`sudo` يطلب كلمة المرور.
- [ ] من OPS-2: إنشاء مستأجر تجريبي بحساب `provisioner` ← DB + مستخدم المستأجر؛ `SHOW GRANTS FOR` مستخدم المستأجر = قائمة stancl على **الاسم المهرَّب** (`tenant\_<id>` مع `\_`) فقط؛ ومستأجران `x_y` و`x-y` لا يصل أي منهما لـ DB الآخر.
- [ ] رفع APK تجريبي (~80 MB) من شاشة الـ super-admin ← `201` لا `413`.
- [ ] من OPS-3: deploy + rollback ناجحان، Horizon يعيد التشغيل، الـ scheduler يعمل (`storage/logs`). الترتيب الكامل (ومنه `storage:link --force` في كل release) في `deploy-runbook.md`.
- [ ] **[CTO-2026-10-09]** `php -v` = 8.4، و`mysql -e 'SELECT VERSION()'` = 8.4.x، و`verify.sh` يعدّي سطري الإصدار.
- [ ] **[CTO-2026-10-09]** §5.1 checklist كامل بعد أول deploy (الجداول المركزية موجودة).
- [ ] **[CTO-2026-10-09]** إيميل تجريبي يوصل عن طريق الـ SMTP (مثلًا reset password لحساب super-admin تجريبي)، وخطأ تجريبي يظهر في Sentry من غير PII.
- [ ] من OPS-5: backup يومي مشفّر ← restore إلى DB منفصلة يطابق عدد الصفوف (`php artisan backup:restore-tenant <id> --drop-after-verify --db-user=sroor_provisioner`، [`backup-restore.md`](backup-restore.md) §6).
- [ ] `reboot` ← كل الخدمات تعود (`verify.sh`).
- [ ] **قبل go-live: مسح كامل** — Rebuild للخادم من Hetzner على صورة Ubuntu 24.04 نظيفة، **أسرار جديدة كلها** (أسرار البروفة تُعتبر محروقة)، `CERTBOT_STAGING=0`، ثم التهيئة والتحقق من جديد.

---

## 11. الرجوع والتعافي

- **قبل أي تهيئة أو تعديل كبير:** Snapshot من Hetzner. الرجوع الكامل = استعادة الـ snapshot (أو Rebuild + إعادة التشغيل؛ السكريبتات idempotent).
- **قفل SSH:** كونسول Hetzner (Rescue/Console) ← احذف `/etc/ssh/sshd_config.d/00-sroor-hardening.conf` ← `systemctl restart ssh` ← صحّح المفتاح ← أعد `--only 10-ssh-users`.
- **nginx لا يبدأ:** `nginx -t` يحدد السبب؛ لا يحدث reload إلا بعد نجاح `nginx -t`.
- **ufw:** `ufw disable` من الكونسول، ثم `--only 20-firewall`.
- **صلاحيات MySQL انحرفت:** `--only 41-mysql-users` يعيدها حرفيًا كما في §5 (REVOKE ثم GRANT)، ولا يمس مستخدمي المستأجرين ولا البيانات.
- لا سكريبت هنا يحذف بيانات (لا `DROP DATABASE`، لا `migrate:fresh`). الحذف الوحيد: الحسابات المجهولة في MySQL على تثبيت جديد.

---

## 12. مخاطر معروفة

1. **`CREATE USER` العام للـ provisioner** يسمح له بتعديل/حذف أي حساب لا يحمل `SYSTEM_USER` (أي حساباتنا الأربعة وحسابات المستأجرين، لا root). مقبول لأن stancl يحتاجه؛ كلمة مروره لا توضع إلا حيث يعمل الـ provisioning (OPS-2).
2. **نمط stancl غير المهرَّب — شرط صلب (blocking) لـ OPS-2، TODO(CTO):** الـ slug يقبل `_` و`-`، فمنحة stancl الأصلية `tenant_shop_a` (بلا escape) تطابق DB المستأجر `shop-a` أيضًا = ثغرة عزل بين المستأجرين. OPS-1 يغلقها بالفشل: الـ provisioner على `tenant\_%` فيُرفض الـ GRANT غير المهرَّب (`ERROR 1044`) ولا يُنشأ مستأجر بهذا الـ manager. **OPS-2 لا يُعتبر منتهيًا** حتى يوجد subclass لـ `PermissionControlledMySQLDatabaseManager` يهرّب `\` و`_` و`%` في هدف الـ `GRANT` (مع اختبار `@group mysql` لمستأجرين `x_y`/`x-y`). بديل أضعف يحتاج قرار CTO: منع `_` في الـ slug (`StoreTenantRequest`) مع إرجاع نمط الـ provisioner إلى `tenant_%` — يعتمد على أن كل مسار ينشئ slug يطبّق نفس الـ validation وعلى عدم وجود slugs قديمة فيها `_`، لذلك الـ escape هو الافتراضي الآمن.
3. **أسرار الاتصال بالمستأجرين** (اسم/كلمة مرور مستخدم كل مستأجر) يخزنها stancl في `tenants.data` في DB المركزي — قرار OPS-2.
4. **`opcache.validate_timestamps=0`:** أي تعديل يدوي على الكود لا يظهر بدون `systemctl reload php8.4-fpm` (OPS-3 يعمله تلقائيًا).
5. سكريبتات `public/*.php` القديمة (مثل webhooks) **لا تعمل على الـ VPS** عمدًا (nginx ينفّذ `index.php` فقط).
6. إن وُضع الدومين خلف proxy (مثل Cloudflare orange-cloud) يجب ضبط real IP في nginx و`TrustProxies` — غير مفعّل حاليًا.
7. **[CTO-2026-10-09] كلمة مرور `audit_pruner` في `shared/.env`:** الـ scheduler بيشتغل كمستخدم التطبيق، فاختراق التطبيق يقدر يوصل لها ويمسح صفوف audit (مش يعدّلها). البديل الأقوى: تشغيل الـ prune من cron لـ root بملف env منفصل — محتاج قرار CTO وتعديل في IDEN-1.15.
8. **[CTO-2026-10-09] الـ repos الخارجية (ondrej/php، repo.mysql.com) مش داخلة في unattended-upgrades** (بيغطي Ubuntu security بس): تحديث أمني يدوي شهري لـ PHP/MySQL (`apt upgrade` في نافذة صيانة بعد snapshot). ترقية MySQL تلقائية كانت هتعمل restart للـ DB في أي وقت.
9. **[CTO-2026-10-09] fingerprint مفتاح MySQL** (`BCA43417C3B485DD128EC6D4B7B3B788A8D3785C`) مكتوب من المعرفة ومش متحقَّق منه أونلاين في الـ lane دي: قارنه بـ https://dev.mysql.com/doc/refman/8.4/en/checking-gpg-signature.html قبل أول تشغيل. لو مختلف، الخطوة بتفشل وماتثقش في المفتاح (fail closed).

---

## 13. اختبار السكريبتات محليًا

```bash
bash scripts/ops/tests/run-tests.sh
```

لا يحتاج خادمًا ولا root ولا شبكة: syntax و`set -euo pipefail`، الأنماط الممنوعة، **تطابق §5 مع `lib/mysql-grants.sh`**، تغطية قائمة stancl، رفض كلمات المرور الضعيفة دون طباعتها، `check-env.sh` (حالة سليمة + 15 مخالفة)، parser الـ `.env` لا ينفّذ شيئًا، render كل القوالب، ترتيب الخطوات.

فحص حي اختياري على **MySQL 8 مؤقت ومعزول فقط** (ينشئ ثم يحذف حسابات `opstest_*`):

```bash
OPS_TEST_MYSQL_CMD="mysql -h127.0.0.1 -P3399 -uroot" bash scripts/ops/tests/run-tests.sh
# داخل Docker/CI أضف: OPS_TEST_MYSQL_USER_HOST=%
```

يتحقق من: `SHOW GRANTS` = المتوقع (مع التطبيق مرتين)، تنفيذ تسلسل stancl كاملًا كـ provisioner (create DB، `userExists`، `CREATE USER`، `GRANT` مهرَّب، `DROP USER`، `DROP DATABASE`)، رفض الـ `GRANT` غير المهرَّب (`ERROR 1044`)، وأن مستخدم المستأجر لا يصل لـ DB شبيهة الاسم (`opstest_tenant-probe`)، ورفض كل ما خارج كل دور (provisioner لا يقرأ hashes ولا يلمس المركزي، app لا ينشئ DB ولا يغير الـ schema ولا يقرأ مستأجرًا، backup لا يكتب، migrator لا يلمس المستأجرين). **[CTO-2026-10-09]** وكمان عقد §5.1 عن طريق مسار الـ deploy نفسه (`sync-central-grants.sh` كـ migrator مرتين): `app` يعمل `INSERT` على الـ audit ويترفض له `UPDATE`/`DELETE`/`TRUNCATE`، الـ pruner يمسح بـ `WHERE` ويترفض له `UPDATE`/`INSERT`/أي جدول تاني، و`GRANT UPDATE` يدوي + صلاحية جدول اتمسح بيتشالوا في المزامنة التالية.

> **[CTO-2026-10-09]** الجزء الحي بقى يفشل فعلًا عند أي خطوة فاشلة: قبل كده كان جوه `( ... ) && ok || ko`، وbash بيتجاهل `set -e` في الحالة دي، فخطوة فاشلة في النص كانت ممكن تعدّي.

اختبارات الـ release (OPS-3)، offline بالكامل (stubs لـ `php`/`mysql`/`sudo`/`curl` في `APP_ROOT` مؤقت):

```bash
bash scripts/ops/tests/deploy-test.sh        # 94 حالة: deploy، rollback تلقائي ويدوي، preflight، lock، pruning، الأسرار
bash scripts/ops/tests/render-env-test.sh    # يشمل رفض --optional لـ MAIL_* و BACKUP_ARCHIVE_PASSWORD
```

---

## 14. سجل التحقق

| التاريخ | البيئة | النتيجة |
|---|---|---|
| 2026-10-08 | محلي (Git Bash) + MySQL 8.4.3 مؤقت معزول | `run-tests.sh` أخضر بما فيه الفحص الحي |
| 2026-10-08 | محلي (Git Bash) + MySQL 8.4.3 مؤقت معزول | بعد إصلاح المراجعة: provisioner على `tenant\_%` (fail closed) + حدود رفع APK؛ `run-tests.sh` أخضر بما فيه الفحص الحي |
| 2026-10-09 | محلي (Git Bash، Windows) + MySQL 8.4.3 مؤقت معزول | W2 (OPS-1 امتداد + OPS-3): `run-tests.sh` = 176/0 بما فيه الجزء الحي (عقد §5.1 عن طريق الـ migrator)؛ `deploy-test.sh` = 94/0؛ `render-env-test.sh` = 27/0؛ shellcheck 0.11.0 (`--severity=warning`) نظيف؛ actionlint 1.7.12 على `release.yml` نظيف |
| — | الـ VPS (بروفة Q-O5) | **لم يُنفَّذ بعد** — يملؤه الـ CTO بعد §10 و§15 |

---

## 15. البروفة الحقيقية على الـ VPS — **الحالة: لم تُنفَّذ (NOT EXECUTED)**

> **[CTO-2026-10-09]** محتاجة بنية تحتية عند الـ CTO: (1) VPS على **Hetzner** (Ubuntu 24.04)، (2) GitHub Environment **`production`** بالـ secrets/vars (`secrets.md` §2)، (3) **مفتاح SSH للـ deploy** (المفتاح العام على الخادم، الخاص في `DEPLOY_SSH_PRIVATE_KEY`). ولا agent بيعمل SSH ولا بيشغّل الـ workflow؛ الخطوات دي بإيد الـ CTO، والنتائج تتسجل في §14.

| # | الخطوة | النتيجة المتوقعة | تم؟ |
|---|---|---|---|
| 1 | Snapshot من Hetzner للخادم النظيف | snapshot موجود | ☐ |
| 2 | §4.1 + §4.2: `provision.sh` كامل بـ `CERTBOT_STAGING=1` | كل الخطوات `done` | ☐ |
| 3 | `verify.sh` | كله `PASS` (منها PHP 8.4، MySQL 8.4، §5، §5.1، `check-env.sh` يتعمل `SKIP` لأن `.env` فاضي) | ☐ |
| 4 | `provision.sh` كامل مرة تانية | مفيش أخطاء (idempotency) | ☐ |
| 5 | `ssh-keyscan -p <port> <host>` من جهازك، وقارن الـ fingerprint بالـ Hetzner console، وحطه في `DEPLOY_SSH_KNOWN_HOSTS` | — | ☐ |
| 6 | `git tag v0.0.1-rc1` + push للـ tag (أو Actions ← release ← Run workflow مع `allow_no_health_checks` لو الـ health checks لسه مش متسجلة) | `tests` و`build` خضر، `deploy` مستني موافقتك | ☐ |
| 7 | وافق على الـ deploy | `deploy.sh` exit 0؛ `current` ← `releases/<id>`؛ `curl -sI https://<domain>/up` = 200 | ☐ |
| 8 | §5.1 checklist على الخادم | كله مطابق | ☐ |
| 9 | release تاني (tag جديد) | `.previous_release` = الأول؛ الروابط في الـ release التاني سليمة | ☐ |
| 10 | rollback يدوي: `bash <release>/scripts/ops/deploy.sh --app-root /var/www/sroor --rollback` كمستخدم `sroor` | `current` ← الأول، Horizon اتعمله restart، الملفات المرفوعة بعد الـ release التاني لسه ظاهرة | ☐ |
| 11 | release بـ migration فاشلة عمدًا على مستأجر تجريبي (فرع تجريبي + tag `v0.0.0-fail`) | exit 1، `current` ماتغيرش، مفيش reload | ☐ |
| 12 | release بـ health check فاشل بعد التبديل (مثلًا `--health-url` غلط في run يدوي) | exit 3، rollback تلقائي للسابق | ☐ |
| 13 | `.env` بـ `TELESCOPE_ENABLED=true` أو `BACKUP_ARCHIVE_PASSWORD` فاضي (عدّل الـ secret مؤقتًا) | الـ workflow يفشل في «Production standards gate» قبل أي اتصال بالخادم | ☐ |
| 14 | `reboot` ثم `verify.sh` | كل الخدمات رجعت | ☐ |
| 15 | **قبل go-live:** Rebuild كامل + أسرار جديدة كلها + `CERTBOT_STAGING=0` (§10 آخر بند) | — | ☐ |
