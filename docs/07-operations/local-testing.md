# بيئة الاختبار المحلية الكاملة (Laragon على Windows)

> الهدف: نسخة كاملة من المنصة على جهاز الـ CTO بدومينات وsubdomains حقيقية (central + admin + مستأجرين اتنين) قبل أي deploy على السيرفر.
> محلي 100%: مفيش أي اتصال بسيرفر خارجي، ومفيش قاعدة بيانات قديمة بتتلمس. كل الـ databases الجديدة اسمها بيبدأ بـ `sroor_local_`.

---

## 1. الشكل العام

| الدومين | الدور | بيخدم إيه |
|---|---|---|
| `sroor.test` | central host | الـ workspace resolver (`/connect`)، `/brochure`، `/up` |
| `admin.sroor.test` | platform console (`CENTRAL_ADMIN_DOMAINS`) | `/super-admin/*` و`/api/v1/super-admin/*` **بس هنا**، مع CSP/HSTS من `AdminSecurityHeaders` |
| `demo.sroor.test` | مستأجر `demo` | ERP + POS، فيه بيانات سنة كاملة (`tenant:populate-realistic-data`) |
| `shop2.sroor.test` | مستأجر `shop2` | مستأجر فاضي، عشان اختبار العزل بين المستأجرين يبقى واضح |

| الحاجة | القيمة |
|---|---|
| Apache vhost | `C:\laragon\etc\apache2\sites-enabled\00-sroor-local.conf` ← `D:/projects/sroor/backend/public` |
| الـ DB المركزية | `sroor_local_central` |
| DB لكل مستأجر | `sroor_local_tenant_<id>` (مثلًا `sroor_local_tenant_demo`) |
| نسخ الاستعادة (drill) | `sroor_local_tenant_zz_restore_*` (نفس الـ prefix) |
| الـ env | `backend/.env.local-test.example` بيتدمج في `backend/.env` |
| السكربتات | `scripts/local/` |

### ليه الـ vhost اسمه `00-sroor-local.conf`؟
Laragon بيعمل لوحده `auto.sroor.test.conf` (الـ DocumentRoot بتاعه جذر الـ repo، وده غلط) وبيرجع يكتبه كل ما يقوم. Apache بيقرا ملفات `sites-enabled` بالترتيب الأبجدي، وأول vhost اسمه مطابق هو اللي بيكسب. `00-sroor-local.conf` بييجي بعد `00-default.conf` وقبل `auto.sroor.test.conf`، فبيكسب من غير ما نمسح أو نعدّل أي ملف auto، ومن غير ما نقفل "Auto virtual hosts" (اللي باقي المشاريع معتمدة عليها). اتأكدنا بـ `httpd -S`: الـ vhost بتاعنا هو أول واحد لـ `sroor.test` و`*.sroor.test`.

الـ vhost كمان:
- `DirectoryIndex index.php`: في `backend/public/index.html` متتبع في git (ده الـ shell بتاع Capacitor، `scripts/capacitor/webAssets.mjs` بيعتمد عليه، فلازم يفضل)، وإعداد Laragon العام بيقدّم `index.html` الأول، فكان `/` هيفتح صفحة static بدل Laravel.
- مفيش أي ملف `.php` بيتنفذ غير `index.php` (نفس قاعدة nginx على الـ VPS). ده بيقفل `public/update_webhook.php` اللي بيعمل `git reset --hard origin/main` + migrate على الـ repo.

---

## 2. الخطوات بالترتيب

### الخطوة 1: الـ hosts (مرة واحدة، **Administrator**)
افتح PowerShell بـ "Run as Administrator":
```powershell
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\add-hosts.ps1 -DryRun
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\add-hosts.ps1
```
بيضيف `admin.sroor.test` و`demo.sroor.test` و`shop2.sroor.test` (و`sroor.test` لو مش موجود؛ Laragon غالبًا ضايفه) جوه block عليه علامة، وبياخد نسخة من hosts قبل أي تعديل (`hosts.sroor-bak-<timestamp>`)، وبيعمل `ipconfig /flushdns`. تشغيله تاني مش بيكرر حاجة.
- مستأجر جديد: `add-hosts.ps1 -ExtraHost shop3.sroor.test` (ملف hosts في Windows مفيهوش wildcard).
- الإزالة: `add-hosts.ps1 -Remove`.

### الخطوة 2: Reload لـ Apache
Laragon ← Menu ← Apache ← Reload (أو Stop ثم Start All).

### الخطوة 3: setup (PowerShell عادي، من غير Administrator)
```powershell
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\setup-local.ps1 -DryRun
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\setup-local.ps1 -SuperAdminEmail owner@sroor.test
```
بيعمل بالترتيب (وكل خطوة بتشوف الحالة الأول، فتشغيله تاني آمن وبيشتغل كـ "update"):
1. يتأكد من PHP 8.3+ وextensions (`pdo_mysql`, `bcmath`, `intl`, `mbstring`, `zip`)، الـ mysql client بتاع Laragon، npm، و`vendor/`.
2. يجرب `root` من غير باسورد (الافتراضي في Laragon). لو اترفض بيسألك على user/باسورد MySQL المحلي (الكتابة مخفية).
3. ياخد نسخة `backend/.env.backup-<timestamp>` (اتضافت `.env.backup-*` لـ `.gitignore`)، وبعدين يدمج المفاتيح. `APP_KEY` ما بيتلمسش، وأي secret ليه قيمة (`*PASSWORD*`, `*TOKEN*`, `*SECRET*`, `*_KEY*`, `*DSN*`) بيفضل زي ما هو. الاستثناء الوحيد `DB_USERNAME`/`DB_PASSWORD`، بياخدوا بيانات MySQL المحلية اللي اتكشفت. وبعدين `config:clear` + `route:clear`.
4. ينشئ `sroor_local_central` بس.
5. `migrate`، وبعدها `CentralPermissionsSeeder` + `PlansAndFeaturesSeeder` (الاتنين idempotent)، وبعدها `tenants:migrate` لو فيه مستأجرين.
6. `central:create-super-admin` (الباسورد 12 حرف أو أكتر، بيتسأل مرتين ومش بيظهر).
7. يجهّز `demo` و`shop2` عن طريق `ProvisionTenantAction` ← `TenantProvisionerService`، وده نفس مسار الإنتاج: DB + migrate + permissions + فرع رئيسي + أدمن بدور `admin` + اشتراك. بيسألك على باسورد واحد للأدمنين الاتنين (8 حروف أو أكتر). الدومينات: `<slug>.sroor.test` + `<slug>.localhost`.
8. `tenant:populate-realistic-data demo` لو `demo` مفيهوش فواتير (من غير `--fresh`، يعني ما بيمسحش حاجة). بيطبع **مرة واحدة** باسورد اتولد لموظفي البيانات التجريبية (`admin@2m.com`, `cashier1@2m.com`, `cashier2@2m.com`).
9. `storage:link`، ويمسح `public/hot` لو قديم ومفيش Vite شغال (الملف ده بقى git-ignored ومش متتبع)، وبعدين `npm run build` (اللي بيشغّل `lang:export` الأول).
10. smoke checks محلية: `/up` و`/api/v1/ping` على كل host، وإن `/super-admin/login` بيرجع 404 على host المستأجر، وإن الـ resolver على `sroor.test` بيلاقي `demo`.

خيارات: `-SkipDemoData`، `-Shop2DemoData` (بيانات لـ shop2 كمان)، `-SkipBuild`، `-DevServer` (هتشتغل بـ `npm run dev`)، `-SkipSuperAdmin`.

> تنبيه: الـ setup بيحوّل `backend/.env` من sqlite لـ MySQL. للرجوع لإعدادك القديم استخدم `reset-local.ps1 -KeepDatabases -RestoreEnv <backup>` (§8).
>
> **PHPUnit:** `phpunit.xml` بيغيّر جزء بس من المفاتيح (DB/cache/queue/mail). الباقي، زي `CENTRAL_ADMIN_DOMAINS` و`TENANT_DB_PREFIX` و`QUICK_LOGIN_ENABLED`، كان هيتقري من `.env`، فالاختبارات محليًا كانت هتختلف عن CI. عشان كده الـ setup بيكتب `backend/.env.testing` = سطر علامة + نسخة من `.env.example` (نفس اللي بيعمله CI بالظبط، ومن غير أي secrets، وعليه git-ignore). Laravel بيقراه بدل `.env` لما `APP_ENV=testing`. الملف ده بيتحدث مع كل setup، ولو عندك `.env.testing` معمول بإيدك ما بيتلمسش.

---

## 3. الروابط

| إيه | الرابط | الدخول |
|---|---|---|
| مستأجر demo | http://demo.sroor.test/login | `admin@demo.sroor.test` أو `01000000201` + باسورد الأدمن اللي اخترته. أو موظفين البيانات التجريبية. أو Quick login (`QUICK_LOGIN_ENABLED=true`، محلي بس) |
| مستأجر shop2 | http://shop2.sroor.test/login | `admin@shop2.sroor.test` أو `01000000202` |
| لوحة المنصة | http://admin.sroor.test/super-admin/login | إيميل الـ super-admin |
| الـ resolver | http://sroor.test/connect | اكتب `demo` أو `shop2` |
| Telescope | من لوحة المنصة (signed link) | super-admin بس |

### 2FA للـ super-admin
أول دخول بيدّي token مخصص للإعداد بس (صالح 15 دقيقة). امسح الـ QR بأي authenticator app (Google Authenticator / Microsoft Authenticator / 1Password)، أكّد بالكود، واحفظ الـ recovery codes. من بعدها كل دخول = باسورد + كود.

### نسيت الباسورد (super-admin)
`CENTRAL_PASSWORD_RESET_URL=http://admin.sroor.test/super-admin/reset-password`. الإيميل مش بيتبعت فعلًا (`MAIL_MAILER=log`)، بيتكتب في `backend/storage/logs/laravel-YYYY-MM-DD.log`، افتحه وانسخ الرابط.
لو عايز inbox حقيقي: Laragon فيه Mailpit. شغّله وحط `MAIL_MAILER=smtp`، `MAIL_HOST=127.0.0.1`، `MAIL_PORT=1025`، `MAIL_SCHEME=null` في `.env`، وبعدها `php artisan config:clear`. الواجهة على http://localhost:8025.

---

## 4. التيرمنالات اللي تفضل مفتوحة (من `backend/`)

```powershell
php artisan schedule:work          # الـ scheduler. بيشغّل كمان queue:work --stop-when-empty كل دقيقة (زي staging)
php artisan queue:work --tries=3 --timeout=120   # اختياري: worker دايم (زي supervisor على الـ VPS)، الـ jobs بتخلص أسرع
php artisan pail                   # اختياري: متابعة الـ logs لايف
npm run dev                        # اختياري: HMR. شغّل الـ setup بـ -DevServer أو امسح public/hot لما تقفله
```
- `DB_QUEUE_CONNECTION=mysql` إجباري هنا. من غيره الـ job اللي بيتبعت من جوه request مستأجر بيتكتب في جدول `jobs` جوه DB المستأجر، والـ worker (اللي شغال في السياق المركزي) عمره ما هيشوفه. نفس الكلام لـ `PULSE_DB_CONNECTION=mysql`.
- مع `npm run dev`: الـ HMR على `localhost:5173` (`vite.config.js`). الصفحات على `*.sroor.test` بتحمّل منه عادي (`cors: true`). وعلى `admin.sroor.test` الـ CSP بيضيف origin الـ dev server لوحده لما `APP_ENV=local` و`public/hot` موجود.
- بعد ما تقفل `npm run dev` لازم `public/hot` يتمسح (Vite بيمسحه لوحده لو اتقفل بشكل طبيعي). لو فضل موجود، الصفحات هتفضل بيضا.

---

## 5. Desktop (Electron) و Android

### Electron
النسخة المتغلفة (packaged) بتقبل بس `https://*.baraa-solutions.com`، وده مقصود. محليًا شغّلها من السورس في وضع dev:
```powershell
cd D:\projects\sroor\desktop
npm run dev        # electron . --dev ← بيسمح بـ localhost و *.sroor.test (src/security/urlPolicy.js)
```
من الإعدادات حط Server URL = `http://demo.sroor.test` (Electron بيقرا ملف hosts بتاع Windows).
الربط بكود المساحة بقى شغال محليًا: الـ resolver بيرجّع `server_url` بنفس scheme الـ `APP_URL` (يعني `http://demo.sroor.test` هنا، و`https://` دايمًا في الإنتاج).
حدود معروفة: زرار "تبديل مساحة العمل" والـ deep link `sroor://connect` لسه بيرجعوا لـ `https://baraa-solutions.com` (hardcoded في `LoginView.vue` وفي `desktop/`). محليًا ارجع لـ `http://sroor.test/connect` بإيدك.

### Android (Capacitor) (لسه ما اتجربش على جهاز)
الموبايل/الـ emulator مش بيشوف ملف hosts بتاع Windows. الطريقة اللي من غير root:
```powershell
cd D:\projects\sroor\backend
php artisan serve --host=127.0.0.1 --port=8000
adb reverse tcp:8000 tcp:8000
```
- Chromium (والـ WebView بتاع Android) بيحوّل `*.localhost` لـ loopback، والـ `adb reverse` بيوصل بورت 8000 على الموبايل بالجهاز. والـ setup ضاف الدومين `demo.localhost` للمستأجر.
- **تعديل مؤقت، ما يتعملوش commit:** في `backend/capacitor.config.json` خلّي `server.url` = `http://demo.localhost:8000`، وبعدين `npx cap sync android` وشغّل من Android Studio. لما تخلص: `git restore backend/capacitor.config.json` وبعدين `npx cap sync android`.
- `cleartext: true` موجود بالفعل في الـ config.

---

## 6. HTTPS و HSTS

- كل حاجة محليًا على **http**. `SESSION_SECURE_COOKIE=false`.
- `AdminSecurityHeaders` بيبعت `Strict-Transport-Security: max-age=31536000; includeSubDomains` على `admin.sroor.test` بس. **ده مش بيبوّظ http:** المتصفحات بتتجاهل HSTS اللي جاي على http (RFC 6797 §8.1). اتأكدنا من الكود: الهيدر مش مشروط بالـ scheme، بس مفيش متصفح بيطبقه غير على https.
- **لو فعّلت SSL في Laragon** (Menu ← Apache ← SSL) وفتحت `https://admin.sroor.test` ولو مرة واحدة، المتصفح هيثبّت https لـ `admin.sroor.test` (والـ subdomains بتاعته) لمدة سنة، و`http://admin.sroor.test` هيبطل يفتح في المتصفح ده. الحل: `chrome://net-internals/#hsts` ← Delete domain security policies ← `admin.sroor.test`. ملحوظة: SSL في Laragon بيطلع certificates للـ auto vhosts بس. الـ vhost بتاعنا عايز block ‏`*:443` بإيدك. مش مطلوب للاختبار.
- `SESSION_DOMAIN=null` (كوكي لكل host لوحده) مقصود، مع إن المقترح كان `.sroor.test`. الكوكي على `.sroor.test` كان هيبقى مشترك بين `demo` و`shop2` و`admin`، وده عكس عزل الإنتاج. الـ API شغال بـ Bearer tokens أصلًا، فمفيش حاجة محتاجة كوكي مشترك.

---

## 7. حدود معروفة

اتصلحت (W2 batch 3، lane 3H):
- الـ SPA بياخد الـ central hosts من السيرفر (`<meta name="central-domains">` في `app.blade.php` ← `resources/js/helpers/platformHosts.js`)، فـ `sroor.test` بقى central زي `baraa-solutions.com`. والـ admin hosts بتتبعت في `<meta name="admin-domains">` على لوحة المنصة بس (مش بتظهر في صفحات المستأجرين).
- الـ resolver بيرجّع `server_url` بـ scheme الـ `APP_URL` (`http` هنا، `https` دايمًا في الإنتاج).
- `CENTRAL_DOMAIN` بيتقري من `config('tenancy.central_domain')` بس (`App\Support\PlatformHosts`)، فـ `config:cache` على الـ VPS مش بيبوّظه. القيمة الفاضية بترجع لـ `baraa-solutions.com`.
- `backend/public/hot` اتشال من git وبقى ignored (ومعاه `backend/public/storage`).
- `/impersonate/leave` بيحوّل لـ `/super-admin/tenants` على أول host في `CENTRAL_ADMIN_DOMAINS` (هنا `http://admin.sroor.test/super-admin/tenants`).
- `TenantSampleSeeder` بقى idempotent وبنفس هوية `provision-local-tenant.php`: id/slug = `SEED_DEMO_TENANT_SLUG` (الافتراضي `demo`)، والدومينات `<slug>.sroor.test` + `<slug>.localhost`. لو `demo` موجود ما بيعملش حاجة. الـ setup لسه **مش بيشغّل** `db:seed` الكامل لأن `DatabaseSeeder` بيعمل super-admin في جدول `users` (المسار القديم)؛ استخدم `central:create-super-admin`.

لسه موجودة:
1. زرار "تبديل مساحة العمل" في Electron (`LoginView.vue`) والـ deep link في `desktop/` بيرجعوا لـ `https://baraa-solutions.com/connect` (§5).
2. `tenant:populate-realistic-data` بيعمل مخزن رئيسي تاني (`STR-MAIN`) جنب `MAIN-01` بتاع الـ provisioner، فبيبقى فيه فرعين `is_main`. ده خاص بالبيانات التجريبية.
3. ملفات المستأجر في `backend/storage/tenant<id>/`. لو عندك مستأجر sqlite قديم اسمه `demo`، الاتنين هيشاركوا نفس الفولدر.
4. لو `backend/public/hot` موجود على جهازك من قبل كده ومفيش `npm run dev` شغال، الصفحات هتفضل بيضا لحد ما يتمسح (الـ setup بيمسحه).

---

## 8. Reset

```powershell
# اعرض الأول
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\reset-local.ps1 -DryRun
# امسح كل sroor_local_* (بيطلب منك تكتب RESET)، وبعدين ابني من الأول
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\reset-local.ps1
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\setup-local.ps1
# ارجع لـ .env القديم (sqlite) من غير ما تمسح الـ DBs
powershell -ExecutionPolicy Bypass -File D:\projects\sroor\scripts\local\reset-local.ps1 -KeepDatabases -RestoreEnv D:\projects\sroor\backend\.env.backup-<timestamp>
```
الـ reset بيعمل `DROP` بس للأسماء اللي مطابقة `^sroor_local_[a-z0-9_]+$`. أي database تانية على السيرفر (مشاريع تانية أو نسخ من الإنتاج) عمرها ما بتتلمس.

---

## 9. Checklist الاختبار

**المستأجر demo**
- [ ] دخول بالباسورد، وQuick login (محلي بس)، والخروج.
- [ ] فتح وردية، بيع من POS (كاش + آجل)، طباعة إيصال حراري وA4، قفل الوردية والفرق.
- [ ] مرتجع بيع، ومرتجع شراء.
- [ ] فاتورة شراء ← المخزون والمورد اتحدثوا.
- [ ] تحويل مخزون بين فرعين: الكمية بتخرج من فرع وبتدخل التاني، والحركات ظاهرة.
- [ ] الخزينة: مصروف، تحصيل من عميل، دفع لمورد. الأرصدة صح (3 خانات عشرية).
- [ ] التقارير والـ daily journal.
- [ ] **عزل الفروع:** اعمل يوزر صلاحياته على فرع واحد. الفرع التاني مش ظاهر، وأي طلب بـ `X-Store-Id` بتاعه بيرجع 403.
- [ ] الصلاحيات: كاشير ما يقدرش يمسح فاتورة أو يدخل الإعدادات (من الـ API نفسه، مش بس الـ UI).
- [ ] عربي/إنجليزي، dark/light، وعلى عرض موبايل.

**العزل بين المستأجرين**
- [ ] `shop2` فاضي: مفيش أصناف ولا فواتير ولا عملاء من `demo`.
- [ ] token بتاع `demo` مع `X-Tenant: shop2` (أو على `shop2.sroor.test`) ← 401، ومفيش أي بيانات بترجع.
- [ ] `http://demo.sroor.test/super-admin/login` ← 404. و`http://admin.sroor.test/api/v1/items` ← 404.
- [ ] فولدرات الملفات منفصلة (لوجو مرفوع في demo مش ظاهر في shop2).

**لوحة المنصة (admin.sroor.test)**
- [ ] إعداد 2FA، دخول بكود، دخول بـ recovery code، step-up.
- [ ] نسيت الباسورد ← الرابط في الـ log أو Mailpit ← إعادة التعيين.
- [ ] إنشاء مستأجر جديد من اللوحة (`shop3`): ضيف `shop3.sroor.test` بـ `add-hosts.ps1 -ExtraHost shop3.sroor.test`، والـ DB هتبقى `sroor_local_tenant_shop3`.
- [ ] تعليق مستأجر أو تحويله read-only، وتأثير ده على دخوله.
- [ ] impersonation لمستأجر.
- [ ] Telescope وPulse ظاهرين للـ super-admin بس.

**الـ queue والـ scheduler**
- [ ] `schedule:work` شغال، و`jobs` المركزي بيفضى، و`failed_jobs` فاضي.

**الـ backups (local disk)**
- [ ] (اختياري) حط `BACKUP_ARCHIVE_PASSWORD` بقيمة محلية للتجربة، وبعدها `php artisan config:clear`.
- [ ] `php artisan backup:tenants --disk=local --no-cleanup` ← أرشيفات في `backend/storage/app/private/sroor-local-backups/<subject>/`، وصفوف في جدول `tenant_backups`.
- [ ] restore drill: `php artisan backup:restore-tenant demo --drop-after-verify` ← بيرجّع في `sroor_local_tenant_zz_restore_*`، يعدّ الصفوف، وبعدين يمسحها.
- [ ] `php artisan health:check`.

---

## 10. Playwright E2E (W2 batch 3، lane 3L)

دخول الـ "master key" القديم اتشال، فرقم الأدمن المركزي `01000000001` مبقاش بيفتح جلسة جوه أي مستأجر. الافتراضي في الـ E2E بقى **أدمن المستأجر `demo`** اللي `setup-local.ps1` بيعمله (عن طريق `provision-local-tenant.php`):

| المتغير | الافتراضي | ملاحظة |
|---|---|---|
| `E2E_USER_PHONE` | `01000000201` | أدمن `demo` |
| `E2E_USER_PASSWORD` | `password` | حط الباسورد اللي اخترته في الـ setup (أو استخدم `password` وقت الـ setup) |
| `E2E_WORKSPACE_CODE` | `demo` | كود مساحة العمل لو شاشة الدخول طلبته الأول |

- القيم دي في مكان واحد: `e2e/utils/e2e-user.js` (بيستخدمه `e2e/auth/login.setup.js` و`e2e/flows/login-flow.spec.js` و`e2e/flows/users-full-page-audit.spec.js`). الـ specs القديمة في `backend/tests/e2e/` بقى افتراضيها نفس الرقم.
- أي قيمة تانية تتحدد من الـ environment، مثلًا:
  ```powershell
  $env:E2E_USER_PHONE='01000000202'; $env:E2E_WORKSPACE_CODE='shop2'; $env:E2E_USER_PASSWORD='<الباسورد>'; npm run e2e:desktop
  ```
- لو عملت المستأجر بـ `TenantSampleSeeder` بدل الـ setup، رقم الأدمن هناك `01000000099` والباسورد اتطبع مرة واحدة (أو `SEED_DEMO_TENANT_PASSWORD`)، فحط `E2E_USER_PHONE=01000000099`.
- اتأكد إن الـ specs بتتحمّل من غير سيرفر: من `backend/` شغّل `npx playwright test --config=../playwright.config.js --list`.
