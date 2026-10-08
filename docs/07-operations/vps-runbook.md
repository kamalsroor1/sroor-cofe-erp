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

ما لا يدخل في OPS-1: ملء `.env` الإنتاجي من GitHub Secrets (OPS-4)، الـ release pipeline (OPS-3)، إنشاء DB المستأجر تلقائيًا (OPS-2)، الـ backups (OPS-5).

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
| `verify.sh` | فحوص القبول بعد التهيئة (read-only): الخدمات، SSH، الـ firewall، المنافذ، PHP، `SHOW GRANTS`، الشهادة، nginx |
| `tests/run-tests.sh` | اختبارات offline للسكريبتات (§13) |

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
for key in MYSQL_APP_PASSWORD MYSQL_MIGRATOR_PASSWORD MYSQL_PROVISIONER_PASSWORD MYSQL_BACKUP_PASSWORD REDIS_PASSWORD; do
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
| `30-php` | PHP 8.3 FPM + `bcmath intl pdo_mysql redis gd zip mbstring xml curl opcache`؛ pool مخصص `sroor` على `/run/php/sroor-fpm.sock`؛ `opcache.validate_timestamps=0` (الـ deploy يعمل reload) |
| `40-mysql` | MySQL 8 على `127.0.0.1` فقط، `mysqlx=OFF`، `local_infile=0`، utf8mb4، حذف الحسابات المجهولة؛ root يبقى `auth_socket` (لا كلمة مرور) |
| `41-mysql-users` | DB المركزي + الحسابات الأربعة بأقل صلاحية (§5)، ثم **يقارن `SHOW GRANTS` بالـ runbook ويفشل عند أي اختلاف** |
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
| `sroor_app` | التطبيق وقت التشغيل (web + Horizon + scheduler) | `SELECT, INSERT, UPDATE, DELETE` على المركزي **فقط**. لا يصل لأي DB مستأجر؛ اتصالات المستأجرين تتم بمستخدم كل مستأجر الذي ينشئه stancl (OPS-2) |
| `sroor_migrator` | `php artisan migrate --force` للمركزي أثناء الـ deploy (OPS-3) فقط | DDL + DML على المركزي فقط |
| `sroor_provisioner` | إنشاء/حذف DB المستأجر ومستخدمه (`PermissionControlledMySQLDatabaseManager`، OPS-2) | `CREATE USER` عام + كل صلاحيات مستوى الـ DB على النمط الصارم `tenant\_%` **مع `GRANT OPTION`** + قراءة `mysql.user(Host, User)` فقط |
| `sroor_backup` | `mysqldump --single-transaction` (OPS-5) | `SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER` على المركزي وكل المستأجرين + `PROCESS` |

**ناتج `SHOW GRANTS` المتوقع حرفيًا** (بالأسماء الافتراضية؛ لأسماء أخرى: `bash steps/41-mysql-users.sh --print-expected-grants`). الاختبار `tests/run-tests.sh` يفشل إن اختلف هذا البلوك عن `lib/mysql-grants.sh`:

<!-- ops:expected-grants:begin -->
```sql
GRANT USAGE ON *.* TO `sroor_app`@`localhost`
GRANT SELECT, INSERT, UPDATE, DELETE ON `sroor_central`.* TO `sroor_app`@`localhost`
GRANT USAGE ON *.* TO `sroor_migrator`@`localhost`
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW, TRIGGER ON `sroor_central`.* TO `sroor_migrator`@`localhost`
GRANT CREATE USER ON *.* TO `sroor_provisioner`@`localhost`
GRANT ALL PRIVILEGES ON `tenant\_%`.* TO `sroor_provisioner`@`localhost` WITH GRANT OPTION
GRANT SELECT (`Host`, `User`) ON `mysql`.`user` TO `sroor_provisioner`@`localhost`
GRANT PROCESS ON *.* TO `sroor_backup`@`localhost`
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON `sroor_central`.* TO `sroor_backup`@`localhost`
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON `tenant\_%`.* TO `sroor_backup`@`localhost`
```
<!-- ops:expected-grants:end -->

ملاحظات مُتحقَّق منها على MySQL 8.4 (بخادم مؤقت معزول، انظر §13):

- **`ALL PRIVILEGES` للـ provisioner = بالضبط قائمة stancl** (`ALTER, ALTER ROUTINE, CREATE, CREATE ROUTINE, CREATE TEMPORARY TABLES, CREATE VIEW, DELETE, DROP, EVENT, EXECUTE, INDEX, INSERT, LOCK TABLES, REFERENCES, SELECT, SHOW VIEW, TRIGGER, UPDATE`). هذه كل صلاحيات مستوى الـ DB في MySQL 8، فيخزنها ويعرضها MySQL كـ `ALL PRIVILEGES` على مستوى الـ DB (ليست صلاحيات عامة).
- **fail closed — كل الحسابات على النمط الصارم `tenant\_%`:** في هدف الـ `GRANT` يكون `_` wildcard لحرف واحد. stancl الأصلي ينفّذ `GRANT ... ON \`tenant_<id>\`.*` بلا escape، والـ `id` هو الـ slug (`alpha_dash`، يقبل `_` و`-`). مثال: منحة مستخدم المستأجر `shop_a` على `tenant_shop_a` تطابق أيضًا DB المستأجر `shop-a` ‏(`tenant_shop-a`) ← قراءة/كتابة/`DROP` عبر المستأجرين. لذلك نمط الـ provisioner صارم، وMySQL **يرفض** منحة stancl غير المهرَّبة (`ERROR 1044`) ويقبل المهرَّبة (`tenant\_shop\_a`) — مُتحقَّق منه على 8.4.3. النتيجة: `PermissionControlledMySQLDatabaseManager` الأصلي **يفشل عمدًا** حتى يسلّم OPS-2 الـ manager الذي يهرّب الهدف (انظر §12.2). لا توسّع النمط إلى `tenant_%` أبدًا لتجاوز هذا الفشل. السكريبت يرفض أيضًا أي اسم DB مركزي قد يطابق نمط المستأجرين (مثل `tenantXcentral`).
- **`SELECT (Host, User)` على `mysql.user`** يكفي لـ `userExists()` في stancl، ولا يكشف `authentication_string` (مُختبر: مرفوض).
- **`migrator` — TODO(CTO):** الخطة تحدد `app` = DML فقط، فلا يستطيع تشغيل migrations المركزي. الافتراضي الأكثر أمانًا هنا: حساب `migrator` منفصل يستخدمه الـ deploy فقط (OPS-3) ولا يوضع في `.env` التطبيق. البديل (منح `app` صلاحيات DDL) أبسط لكنه يوسّع صلاحيات التطبيق وقت التشغيل.

التحقق اليدوي (على الخادم كـ root):

```bash
for u in sroor_app sroor_migrator sroor_provisioner sroor_backup; do
  mysql -N -B -r -e "SHOW GRANTS FOR \`$u\`@\`localhost\`"
done
```

> استخدم `-r` دائمًا: بدونه يضاعف وضع الـ batch الـ backslash (`tenant\\_%`).

تدوير كلمة مرور حساب: غيّر القيمة في `/root/sroor-provision.env` ثم `--only 41-mysql-users` (ينفّذ `ALTER USER` ويعيد ضبط الصلاحيات)، ثم حدّث الـ secret المقابل في GitHub و`shared/.env`.

---

## 6. الـ queue والـ scheduler

- **Horizon** (`QUEUE_MODE=horizon`): `supervisorctl status sroor-horizon`. عند كل release ينفّذ OPS-3 `php artisan horizon:terminate`، فيعيد supervisor تشغيله من الـ `current` الجديد. لوحة Horizon خلف `central_web` (IDEN-1.7).
- **بديل** (`QUEUE_MODE=worker`): `sroor-worker` × `QUEUE_WORKER_PROCESSES` بـ `queue:work redis --max-time=3600`؛ الـ deploy ينفّذ `queue:restart`.
- قبل أول deploy يكون البرنامج في `BACKOFF/FATAL` لأن `current` غير موجود — طبيعي.
- **الـ scheduler:** cron كل دقيقة كمستخدم `sroor`. أي job تخص المستأجرين يجب أن تدور عليهم داخل tenancy (`tenancy()->runForMultiple`)، ولا تلمس بيانات مستأجر من السياق المركزي.
- Redis بـ `noeviction`: إن امتلأت الذاكرة تفشل الكتابة بدل حذف jobs بصمت. راقب `INFO memory` وارفع `REDIS_MAXMEMORY` عند الحاجة.

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
- [ ] من OPS-3: deploy + rollback ناجحان، Horizon يعيد التشغيل، الـ scheduler يعمل (`storage/logs`).
- [ ] من OPS-5: backup يومي مشفّر ← restore إلى DB منفصلة يطابق عدد الصفوف.
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
4. **`opcache.validate_timestamps=0`:** أي تعديل يدوي على الكود لا يظهر بدون `systemctl reload php8.3-fpm` (OPS-3 يعمله تلقائيًا).
5. سكريبتات `public/*.php` القديمة (مثل webhooks) **لا تعمل على الـ VPS** عمدًا (nginx ينفّذ `index.php` فقط).
6. إن وُضع الدومين خلف proxy (مثل Cloudflare orange-cloud) يجب ضبط real IP في nginx و`TrustProxies` — غير مفعّل حاليًا.

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

يتحقق من: `SHOW GRANTS` = المتوقع (مع التطبيق مرتين)، تنفيذ تسلسل stancl كاملًا كـ provisioner (create DB، `userExists`، `CREATE USER`، `GRANT` مهرَّب، `DROP USER`، `DROP DATABASE`)، رفض الـ `GRANT` غير المهرَّب (`ERROR 1044`)، وأن مستخدم المستأجر لا يصل لـ DB شبيهة الاسم (`opstest_tenant-probe`)، ورفض كل ما خارج كل دور (provisioner لا يقرأ hashes ولا يلمس المركزي، app لا ينشئ DB ولا يغير الـ schema ولا يقرأ مستأجرًا، backup لا يكتب، migrator لا يلمس المستأجرين).

---

## 14. سجل التحقق

| التاريخ | البيئة | النتيجة |
|---|---|---|
| 2026-10-08 | محلي (Git Bash) + MySQL 8.4.3 مؤقت معزول | `run-tests.sh` أخضر بما فيه الفحص الحي |
| 2026-10-08 | محلي (Git Bash) + MySQL 8.4.3 مؤقت معزول | بعد إصلاح المراجعة: provisioner على `tenant\_%` (fail closed) + حدود رفع APK؛ `run-tests.sh` أخضر بما فيه الفحص الحي |
| — | الـ VPS (بروفة Q-O5) | **لم يُنفَّذ بعد** — يملؤه الـ CTO بعد §10 |
