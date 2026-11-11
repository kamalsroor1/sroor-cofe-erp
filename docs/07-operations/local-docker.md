# بيئة التطوير المحلية بـ Docker (للتطوير بس)

> **للتطوير المحلي بس.** الإنتاج على الـ VPS فاضل native (PHP-FPM 8.5 من Ubuntu 26.04 + nginx + MySQL 8.4 + Redis + supervisor/Horizon، شوف `vps-runbook.md`). مفيش أي حاجة هنا بيستخدمها CI أو `release.yml` أو `deploy.yml` أو `scripts/ops/deploy.sh`.
> كل البورتات على `127.0.0.1` بس. مفيش أي اتصال بسيرفر.

---

## 1. فيها إيه

| Service | Image | الدور | من البرّه |
|---|---|---|---|
| `app` | `sroor-dev-php:8.5.12` (مبنية من `php:8.5.12-fpm-trixie`) | PHP-FPM 8.5 | - |
| `web` | `nginx:1.30.6-alpine` | نفس قواعد vhost الـ VPS (`index.php` بس هو اللي بيتنفذ) | `http://localhost:8000` |
| `horizon` | نفس image الـ app | `php artisan horizon` (Redis queue، فيها supervisors `default` و`provisioning` و`long`) | - |
| `scheduler` | نفس image الـ app | `php artisan schedule:work` (= الـ cron كل دقيقة) | - |
| `vite` | نفس image الـ app | Vite dev server + HMR | `http://localhost:5173` |
| `mysql` | `mysql:8.4.13-oraclelinux9` | الـ DB المركزية + DB لكل مستأجر | `127.0.0.1:33060` |
| `redis` | `redis:8.4.8-alpine` | cache + session + queue (عليه password، `noeviction`) | `127.0.0.1:63790` |

الـ image فيها: `bcmath intl pdo_mysql redis gd zip mbstring pcntl exif` + OPcache، و**MySQL 8.4 client الرسمي** (`mysql`/`mysqldump` من repo.mysql.com، عشان الـ backups وسكربت الـ grants)، وNode 24 وComposer 2.11.

الملفات:

| الملف | إيه هو |
|---|---|
| `compose.yaml` | الـ stack (project name: `sroor-dev`) |
| `docker/php/Dockerfile` | الـ image، بتتبني بـ UID/GID بتوعك |
| `docker/php/conf.d/sroor-dev.ini`، `docker/php/php-fpm.d/zz-sroor-dev.conf` | إعدادات PHP/FPM (نسخة dev من templates الـ VPS) |
| `docker/nginx/default.conf` | vhost واحد لكل الـ hosts |
| `docker/mysql/conf.d/sroor.cnf` | نسخة dev من `mysql-sroor.cnf.tmpl` |
| `docker/mysql/init/10-sroor-accounts.sh` | بيعمل الـ DB المركزية والحسابات الخمسة |
| `docker/php/bin/sroor-dev-migrate` | ترتيب الـ migrations زي `deploy.sh` |
| `docker/bin/dev.sh` | نقطة الدخول (كل الأوامر تحت) |
| `backend/.env.docker.example` | الـ env template (الـ passwords فاضية) |

---

## 2. أول مرة

```bash
cd ~/projects/sroor
docker/bin/dev.sh init     # backend/.env.docker + passwords عشوائية + build الـ image + APP_KEY
docker/bin/dev.sh up       # docker compose up -d
SEED_DEMO_TENANT_PASSWORD=fixture-demo-pass docker/bin/dev.sh setup
```

- `init` بينسخ `backend/.env.docker.example` لـ `backend/.env.docker` (عليه git-ignore، mode 600) **لو مش موجود بس**، وبيملا أي password فاضي بقيمة عشوائية من 32 حرف (مش بتتطبع)، وعمره ما بيغيّر قيمة موجودة. أول build بياخد حوالي 8 دقايق (تنزيل)، وبعد كده الـ cache.
- `setup` بيعمل بالترتيب: `migrate` كـ **migrator** ← الـ per-table grants على الـ DB المركزية (`scripts/ops/sync-central-grants.sh`) والتأكد منها ← `tenants:migrate` ← `db:seed` كـ **app**. الـ seeder بيعمل المستأجر `demo` عن طريق `TenantProvisionerService` (نفس مسار الإنتاج: حساب الـ provisioner بيعمل الـ DB ويوزر MySQL خاص بالمستأجر).
- `SEED_DEMO_TENANT_PASSWORD` / `SEED_SUPER_ADMIN_PASSWORD` اختياريين، بيعدّوا من الـ environment بتاعك (`-e`) ومش بيتخزنوا. لو مش محطوطين، الـ seeder بيولّد password وبيطبعه مرة واحدة.

### `backend/.env` بتاعك ما بيتلمسش
الـ containers بتعمل mount لـ `backend/.env.docker` **مكان** `backend/.env` جوه الـ container بس. ملف `.env` بتاعك (Laragon / sqlite / أي حاجة) بيفضل زي ما هو. لو مفيش `backend/.env` خالص، `init` بيعمل ملف placeholder فيه سطر comment بس (لازم يكون فيه ملف كـ mount point، وإلا الـ runtime هيعمله فاضي وowner بتاعه root).

> الاتنين بيشاركوا نفس `storage/` و`bootstrap/cache/` و`public/hot`. ما تعملش `config:cache` على الـ host وانت شغال بالـ stack (والعكس).

---

## 3. الاستخدام اليومي

```bash
docker/bin/dev.sh up | down | ps
docker/bin/dev.sh logs [app|horizon|scheduler|vite|mysql|redis|web]
docker/bin/dev.sh artisan tinker
docker/bin/dev.sh shell                  # bash جوه الـ app (composer / npm / php)
docker/bin/dev.sh migrate                # بعد migration جديدة (مركزية أو مستأجر)
docker/bin/dev.sh test --filter=CustomersApiTest
docker compose restart horizon           # بعد تعديل كود الـ jobs (زي horizon:terminate في الـ deploy)
docker/bin/dev.sh destroy                # down -v: بيمسح volumes الـ MySQL/Redis (بيطلب تكتب DESTROY)
```

- `down` بيسيب الـ volumes (الداتا فاضلة). `destroy` بس هو اللي بيمسحها.
- `composer install` / `npm ci`: من `dev.sh shell`. الـ vite service بيعمل `npm ci --ignore-scripts` لوحده لو `node_modules` مش موجود.
- **ما تشغّلش `php artisan migrate` جوه الـ container على طول**: حساب الـ app ما يقدرش يعمل DDL (زي الإنتاج). استخدم `dev.sh migrate`.

---

## 4. الروابط و الـ tenant subdomains

`*.localhost` بيروح لـ `127.0.0.1` في المتصفح (وcurl) من غير `/etc/hosts`. nginx بيرد على أي host، والـ app بتقرر central ولا tenant من الـ Host header.

| الرابط | إيه |
|---|---|
| `http://localhost:8000` | central (الـ workspace resolver) |
| `http://demo.localhost:8000/login` | المستأجر `demo`: `01000000201` أو `admin@demo.com` + الـ password بتاع الـ setup |
| `http://<slug>.localhost:8000` | أي مستأجر جديد (الـ host بيتحل بالـ slug لوحده) |
| `http://admin.localhost:8000/super-admin/login` | لوحة المنصة (`CENTRAL_ADMIN_DOMAINS=admin.localhost`). اعمل super-admin بـ `dev.sh artisan central:create-super-admin` |
| `http://localhost:5173` | Vite (الصفحات بتحمّل منه لوحدها وهو شغال، والـ CSP بيسمح بيه في `APP_ENV=local`) |
| `127.0.0.1:33060` / `127.0.0.1:63790` | MySQL / Redis لأي GUI (الـ credentials في `backend/.env.docker`) |

- `CENTRAL_DOMAIN=sroor.test` (زي Laragon)، فالمستأجر بيتسجل بـ `demo.sroor.test` + `demo.localhost`. **ما تخليهوش `localhost`**: `TenantSampleSeeder` هيسجل `demo.localhost` مرتين ويقع.
- الـ resolver (`/connect`) بيرجّع `server_url` = `http://demo.sroor.test` من غير بورت. يعني الربط بالكود مش هيفتح غير لو ضفت `sroor.test` في `/etc/hosts` وخليت `SROOR_HTTP_PORT=80`. افتح `demo.localhost:8000` على طول.
- تغيير البورتات: `SROOR_HTTP_PORT` و`SROOR_MYSQL_PORT` و`SROOR_REDIS_PORT` في الـ shell أو في ملف `.env` في جذر الـ repo (عليه git-ignore). Vite لازم يفضل على 5173 (`vite.config.js`)، فاقفل `npm run dev` بتاع الـ host الأول.

---

## 5. حسابات MySQL (least privilege زي الإنتاج)

`docker/mysql/init/10-sroor-accounts.sh` بيستخدم `scripts/ops/lib/mysql-grants.sh` نفسه (`build_users_sql`)، وده المصدر الوحيد للـ grants على الـ VPS. فالحسابات والصلاحيات **هي هي** بتاعة الإنتاج:

| الحساب | الصلاحيات |
|---|---|
| `sroor_app` | `SELECT, INSERT` على `sroor_central` + `UPDATE, DELETE` لكل جدول (ما عدا جداول الـ audit و`tenant_credit_ledger`). **مفيش وصول لـ DBs المستأجرين** |
| `sroor_migrator` | DDL على `sroor_central` + GRANT OPTION (بيوزّع الـ table grants) |
| `sroor_provisioner` | `CREATE USER` + `ALL` على `` `tenant\_%` `` WITH GRANT OPTION (الـ pattern بالـ escape) |
| `sroor_backup` | read-only على central + `tenant\_%` |
| `sroor_audit_pruner` | `SELECT, DELETE` على جداول الـ audit بس |
| `tu_<random>` | يوزر لكل مستأجر، على الـ DB بتاعته بس (`TENANT_DB_PER_TENANT_USER=true`) |

الفرق الوحيد عن الإنتاج:
1. الـ host بتاع الحسابات `%` بدل `localhost` (الـ app بيوصل من network الـ compose)، و`TENANT_DB_USER_HOST=%`.
2. **`GRANT SHOW DATABASES ON *.* TO sroor_app`** (للتطوير بس). السبب: في `APP_ENV=local` بس، `DatabaseTenancyBootstrapper` بتاع stancl بيتأكد إن DB المستأجر موجودة من خلال الـ connection المركزي (`information_schema.SCHEMATA`). وحساب الـ app ما يقدرش يشوف schemas المستأجرين، فكل request لمستأجر كان هيقع بـ `Database tenant_x does not exist`. الصلاحية دي بتكشف **أسماء** الـ DBs بس (لا جداول ولا داتا). الإنتاج (`APP_ENV=production`) مش بيعمل الـ check ده ومش بياخد الصلاحية دي. `sroor-dev-migrate` بيتأكد إن grants الـ app = عقد الإنتاج + السطر ده بالظبط، ولو فيه أي حاجة تانية بيوقف.

لو غيّرت أي `DB_*_PASSWORD` في `backend/.env.docker`: `docker/bin/dev.sh db-accounts` (بيعيد الحسابات ويرجّع الـ per-table grants).

Init script بتاع MySQL بيشتغل **مرة واحدة بس** (لما الـ volume يكون فاضي). لو وقع في النص، الـ container بيقوم من غير حسابات: `dev.sh destroy` وبعدين `up` + `setup` من الأول.

---

## 6. PHPUnit و Playwright

```bash
docker/bin/dev.sh test                                  # السويت كلها
docker/bin/dev.sh test --filter=CustomersApiTest
```
بيستخدم `phpunit.xml` زي ما هو (sqlite `:memory:`). الـ containers ما بتحطش أي إعداد Laravel كـ environment variable (لو حطت، كانت هتغلب `<env>` بتاع phpunit). لو `backend/.env.testing` موجود، Laravel بيقراه زي CI.

Playwright بيشتغل من الـ host ضد الـ stack:
```bash
cd backend
APP_URL=http://demo.localhost:8000 E2E_USER_PASSWORD=password npm run e2e:desktop
APP_URL=http://demo.localhost:8000 E2E_USER_PASSWORD=password npx playwright test --config=../playwright.config.js --project=desktop ../e2e/flows/login-flow.spec.js
```
`webServer` في `playwright.config.js` بيعمل probe على `http://127.0.0.1:8000`. الـ stack بيرد عليه، فـ Playwright بيستخدمه (`reuseExistingServer`) ومش بيشغّل `artisan serve` على الـ host. لو غيّرت `SROOR_HTTP_PORT`، الـ probe هيفشل وPlaywright هيحاول يشغّل PHP على الـ host.

---

## 7. Fedora و SELinux و Podman

- **SELinux:** كل الـ bind mounts عليها `selinux: z`، فـ Docker/Podman بيغيّر label الـ repo لـ `container_file_t` (label مشترك، المستخدم بتاعك بيقرا ويكتب عادي). أول `up` بياخد ثواني زيادة على شجرة كبيرة (`node_modules`، `vendor`).
- **الصلاحيات:** الـ image بتتبني بـ `id -u`/`id -g` بتوعك (`dev.sh` بيبعتهم)، وكل حاجة بتتكتب في الـ repo (`storage/`، `vendor/`، `node_modules/`، `public/hot`) الـ owner بتاعها انت. لو شغّلت `docker compose` على طول من غير `dev.sh`، الافتراضي 1000:1000.
- **Podman (rootless):** `dev.sh` بيستخدم `docker compose` لو موجود، وإلا `podman compose`. أو حدد بـ `SROOR_COMPOSE="podman compose"`. مع Podman، `dev.sh` بيحط `SROOR_USERNS_MODE=keep-id` عشان الـ UID جوه الـ container يبقى هو بتاعك. `podman compose` محتاج `systemctl --user start podman.socket`. الـ images والـ volumes بتاعة Podman منفصلة عن Docker، ومينفعش الاتنين يشتغلوا مع بعض (نفس البورتات).
- `docker compose` على Fedora: المستخدم لازم يكون في جروب `docker`.

---

## 8. حدود معروفة

- **Restore drill** (`backup:restore-tenant`) بيحتاج حساب restore بصلاحية `CREATE` على الـ restore prefix. مش متعمل في الـ stack، وبيرجع لحساب الـ app اللي ما يقدرش يعمل DB. `backup:tenants --disk=local` شغال بحساب `sroor_backup` و`mysqldump` 8.4.
- مفيش HTTPS/HSTS محليًا (زي Laragon، شوف `local-testing.md` §6).
- الإيميل `MAIL_MAILER=log` (في `storage/logs`). مفيش Mailpit.
- ملفات المستأجرين في `backend/storage/tenant<id>/`، ونفس الفولدر بتاع Laragon لو المستأجر عنده نفس الـ id.
- إصدار Redis على Ubuntu 26.04 ممكن يختلف عن `8.4.8` (الـ queue/cache مش بيتأثروا).
- Horizon ما بيعملش reload لوحده بعد تعديل الكود: `docker compose restart horizon`.
