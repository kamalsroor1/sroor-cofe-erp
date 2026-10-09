# الأسرار: GitHub Environments، و`.env` الإنتاجي، وبوابة gitleaks

> المهمة: Phase 1 · W1 · **OPS-4** (`docs/05-planning/phase-1-plan.md` §4.13).
> مرتبطة بـ: OPS-1 ([vps-runbook.md](vps-runbook.md)، `scripts/ops/check-env.sh`)، OPS-3 (`release.yml` يستدعي `render-env.sh`)، OPS-5 (أسرار Google Drive والتشفير).
> القاعدة الحاكمة: الأسرار تعيش في **مكانين فقط**: `shared/.env` على الـ VPS (مملوك لمستخدم التطبيق و`600`) و**GitHub Environment secrets**. لا في الريبو، ولا في المستندات، ولا في الـ logs، ولا في الـ chat.

## 1. الملفات

| الملف | الدور |
|---|---|
| `.gitleaks.toml` | قواعد gitleaks: القواعد الافتراضية من upstream + قواعد للأشكال التي تسربت فعلًا في هذا الريبو (`PASS = "..."`/`password='...'` في السكريبتات، `KEY=VALUE` في ملفات env والـ workflows، tokens في JSON، tokens في الـ URL، `APP_KEY`، Google refresh token) |
| `.gitleaksignore` | الـ **baseline**: بصمات (`commit:file:rule:line`) للنتائج القديمة المعروفة في التاريخ فقط. لا يحتوي أي قيمة سرية |
| `.github/workflows/ci.yml` → job `gitleaks` | يفحص الـ commits الجديدة في كل push/PR ويفشل على أي سر جديد |
| `scripts/ops/tests/gitleaks-selftest.sh` | يثبت أن القواعد تمسك كل شكل مزروع، وأن الـ placeholders تمر، وأن بصمة الـ baseline لا تخفي سرًّا جديدًا |
| `scripts/ops/render-env.sh` | يبني `.env` الإنتاجي من `scripts/ops/templates/production.env.example` + متغيرات البيئة (أسرار الـ GitHub Environment) |
| `scripts/ops/tests/render-env-test.sh` | تستات `render-env.sh` (offline) |
| `backend/tests/Unit/EnvExampleFilesHaveNoSecretsTest.php` | كل `.env.example` و`*.env.example` في الريبو: مفاتيح الأسرار فارغة (أو `null`)، ولا قيمة شكلها credential |

## 2. GitHub Environment `production` (خطوات الـ CTO، مرة واحدة)

> لا يقوم بها أي agent. الأسرار تُنسخ **مباشرة** من الخادم أو من مولّد الأسرار إلى واجهة GitHub، لا عبر chat أو ملفات.

1. Settings → Environments → **New environment** → الاسم `production`.
2. **Required reviewers**: أضف نفسك (كل deploy ينتظر موافقتك).
3. **Deployment branches and tags** → *Selected branches and tags* → أضف قاعدة tags `v*` (والفرع الذي سيُعتمد للإصدار عند تعريف OPS-3). هذا يمنع أي فرع آخر من قراءة أسرار الإنتاج.
4. أضف **Environment secrets** (القيم كلها جديدة، لا يُعاد استخدام أي قيمة ظهرت في تاريخ git):

| Secret | المصدر |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` على جهازك أو على الخادم، مرة واحدة |
| `DB_PASSWORD` | كلمة مرور المستخدم `app` (OPS-1، `/root/sroor-provision.env` → `MYSQL_APP_PASSWORD`) |
| `DB_AUDIT_PRUNER_PASSWORD` | **[CTO-2026-10-09]** كلمة مرور `sroor_audit_pruner` (`MYSQL_AUDIT_PRUNER_PASSWORD`، `vps-runbook.md` §5.1) |
| `DB_MIGRATOR_PASSWORD` | **[CTO-2026-10-09]** كلمة مرور `sroor_migrator` (`MYSQL_MIGRATOR_PASSWORD`). بتروح `deploy.env` المؤقت بس، **مش** `.env` التطبيق |
| `REDIS_PASSWORD` | OPS-1 → `REDIS_PASSWORD` |
| `MAIL_USERNAME`، `MAIL_PASSWORD` | مزوّد SMTP (**إلزامي**، Brevo أو SES SMTP، W1 Q6) |
| `BACKUP_ARCHIVE_PASSWORD` | **[CTO-2026-10-09]** `openssl rand -hex 32`. **إلزامي** (D4): فاضي = الـ release يفشل. احفظ نسخة منه خارج الخادم (password manager)؛ من غيره مفيش restore |
| `SENTRY_LARAVEL_DSN` | **[CTO-2026-10-09]** من مشروع Sentry (free tier). اختياري لحد OPS-7، والـ DSN مكانه `.env` الـ VPS بس |
| `TELEGRAM_BOT_TOKEN`، `TELEGRAM_CHAT_ID` | بوت التنبيهات (بوت **جديد**، ليس القديم) |
| `DEPLOY_SSH_PRIVATE_KEY` | المفتاح الخاص لمستخدم الـ deploy (OPS-1 §SSH)، يُولَّد خصيصًا للـ CI |
| `DEPLOY_SSH_KNOWN_HOSTS` | ناتج `ssh-keyscan -p <port> <host>` بعد التحقق من الـ fingerprint يدويًا |

5. أضف **Environment variables** (ليست سرية، لكن تختلف حسب البيئة): `APP_URL`، `SESSION_DOMAIN`، `SANCTUM_STATEFUL_DOMAINS`، `MAIL_HOST`، `MAIL_PORT`، `MAIL_FROM_ADDRESS`، `DEPLOY_HOST`، `DEPLOY_PORT`، `DEPLOY_USER`، و**[CTO-2026-10-09]** `DEPLOY_APP_ROOT` (اختياري، الافتراضي `/var/www/sroor`).
6. لاحقًا (OPS-5/OPS-2) تُضاف بنفس الطريقة أسرار الـ backup (Google OAuth client/refresh token) وأي حساب DB إضافي، **ومفتاحها يُضاف أولًا إلى `production.env.example` بقيمة فارغة** و**يتربط في `env:` بتاع خطوة «Render the production .env» في `release.yml`**؛ القالب هو الـ allowlist لما يصل للخادم.

قاعدة القرار: أي مفتاح قيمته فارغة في `scripts/ops/templates/production.env.example` = **مطلوب** وقت الـ render، إلا إن مُرّر صراحةً في `--optional`. **[CTO-2026-10-09]** `MAIL_MAILER`/`MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/`MAIL_PASSWORD`/`MAIL_FROM_ADDRESS`/`BACKUP_ARCHIVE_PASSWORD` مايتعملوش `--optional` أبدًا (`render-env.sh` يخرج بـ 2). `release.yml` بيمرّر `--optional SESSION_DOMAIN,SENTRY_LARAVEL_DSN` بس.

## 3. كيف يُبنى `.env` على الـ VPS

**[CTO-2026-10-09] منفَّذ في `.github/workflows/release.yml` (job ‏`deploy`)** — التفاصيل في [`deploy-runbook.md`](deploy-runbook.md) §1 و§3. باختصار: `render-env.sh` ← `check-env.sh` في الـ runner ← `deploy.env` منفصل للـ migrator ← رفع بـ `scp` لمجلد `incoming/...` (700) ← `deploy.sh` يعيد `check-env.sh` على الخادم ويركّب `shared/.env` ذريًا (600) مع نسخة `.env.previous` للـ rollback ← مجلد الرفع يتمسح في كل الحالات. المقتطف ده كان العقد الأصلي (OPS-4)، والـ workflow الفعلي ملتزم بيه:

```yaml
  deploy:
    environment: production            # يفعّل الـ secrets/vars أعلاه + موافقة الـ reviewer
    steps:
      - name: Render production .env (values never printed)
        env:
          APP_KEY: ${{ secrets.APP_KEY }}
          DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
          REDIS_PASSWORD: ${{ secrets.REDIS_PASSWORD }}
          MAIL_USERNAME: ${{ secrets.MAIL_USERNAME }}
          MAIL_PASSWORD: ${{ secrets.MAIL_PASSWORD }}
          TELEGRAM_BOT_TOKEN: ${{ secrets.TELEGRAM_BOT_TOKEN }}
          TELEGRAM_CHAT_ID: ${{ secrets.TELEGRAM_CHAT_ID }}
          APP_URL: ${{ vars.APP_URL }}
          SESSION_DOMAIN: ${{ vars.SESSION_DOMAIN }}
          SANCTUM_STATEFUL_DOMAINS: ${{ vars.SANCTUM_STATEFUL_DOMAINS }}
          MAIL_HOST: ${{ vars.MAIL_HOST }}
          MAIL_PORT: ${{ vars.MAIL_PORT }}
          MAIL_FROM_ADDRESS: ${{ vars.MAIL_FROM_ADDRESS }}
        run: |
          bash scripts/ops/render-env.sh scripts/ops/templates/production.env.example \
            "$RUNNER_TEMP/production.env" --optional SESSION_DOMAIN
          bash scripts/ops/check-env.sh "$RUNNER_TEMP/production.env"
      # ثم: scp إلى  <APP_ROOT>/shared/.env.new  بمفتاح الـ deploy،
      # وعلى الخادم:  check-env.sh .env.new --check-perms  ثم  mv -f .env.new .env  (ذري)،
      # وأخيرًا  rm -f "$RUNNER_TEMP/production.env"  في خطوة  if: always().
```

ضمانات `render-env.sh` (مغطاة في `render-env-test.sh`):
- كل مفتاح في القالب: إن وُجد متغير بيئة بنفس الاسم تُستبدل القيمة، وإلا تبقى قيمة القالب. المفاتيح غير الموجودة في القالب **تُتجاهل**.
- القيم تُكتب بين علامتي `'` فيخزنها phpdotenv حرفيًا (لا `${VAR}` ولا escapes)، ولا يُنفَّذ أي محتوى. القيمة التي فيها `'` أو سطر جديد أو control character تُرفض (أعد توليدها).
- الأخطاء تذكر **اسم المفتاح فقط**. عند أي خطأ لا يُكتب شيء ويبقى الملف السابق كما هو. الكتابة ذرية (`mktemp` + `mv`) بصلاحية `600`.
- لا قيمة تمر في argv (تظهر في `ps`)، ولا `set -x`.

**تدوير سر:** حدّثه في الخادم/المزوّد → حدّث الـ secret في GitHub → أعد تشغيل الـ release (يعيد كتابة `shared/.env`). لا تعدّل `shared/.env` يدويًا إلا في طوارئ، ثم انقل التعديل إلى GitHub فورًا وإلا سيمسحه الـ deploy التالي.

## 4. بوابة gitleaks في الـ CI

ماذا يفعل job `gitleaks` (`ci.yml`):
1. يثبّت gitleaks بإصدار مثبّت (`GITLEAKS_VERSION`) ويتحقق من SHA-256 قبل التشغيل.
2. يشغّل `gitleaks-selftest.sh`: سرّ مزروع من كل شكل يجب أن **يفشل**، الـ placeholders والإشارات `${{ secrets.X }}` يجب أن **تمر**، تعليق `gitleaks:allow` لا يتجاوز البوابة، وبصمة baseline لسطر قديم لا تخفي سرًّا جديدًا في نفس الملف.
3. يشغّل `render-env-test.sh`.
4. يفحص **الـ commits الجديدة فقط**: في الـ PR `base..head`، وفي الـ push `before..after`. عند التشغيل اليدوي أو فرع جديد بلا `before`: يفحص كل تاريخ `HEAD` مقابل `.gitleaksignore`.
5. `--redact` (لا قيمة في الـ log) و`--ignore-gitleaks-allow` (الطريق الوحيد للاستثناء هو ملف مراجَع).

تشغيل محلي (من جذر الريبو، بعد تثبيت gitleaks 8.30.1):

```bash
bash scripts/ops/tests/gitleaks-selftest.sh gitleaks
gitleaks git --config .gitleaks.toml --redact --no-banner --ignore-gitleaks-allow --log-opts="origin/feature/multi-tenant..HEAD" .
gitleaks git --config .gitleaks.toml --redact --no-banner --ignore-gitleaks-allow --log-opts="HEAD" .   # كل التاريخ مقابل الـ baseline
```

### عند فشل الـ job
1. **لا تضف الاستثناء.** احذف السر من الـ commit (أعد كتابة commits الفرع الخاص بك قبل الدمج؛ لا تُعاد كتابة تاريخ `feature/multi-tenant` المشترك).
2. إن كان الـ commit قد دُفع (push): السر **مُسرَّب** لأن الريبو public → دوّره فورًا، حتى لو حُذف لاحقًا.
3. ضع القيمة في `.env` + `config/*.php` بـ `env()`، أو في GitHub Secrets.

### الإيجابيات الكاذبة
- قيم الاختبار الوهمية: ابدأها بـ `fixture` (مثل `FixturePassword123` أو `fixture-secret-value`)؛ كل القواعد تتجاهل قيمة بهذه البادئة مكونة من حروف/أرقام/`-`/`_` فقط.
- الإشارة لمتغير (`${VAR}`، `${{ secrets.X }}`، `os.environ[...]`) لا تُعتبر سرًّا.
- غير ذلك: عدّل القاعدة في `.gitleaks.toml` بأضيق regex ممكن **وأضف حالة في `gitleaks-selftest.sh`** تثبت أن القاعدة ما زالت تمسك السر الحقيقي.
- `.gitleaksignore` للتاريخ القديم فقط، لا لجعل commit جديد يمر.

### إعادة توليد الـ baseline (نادرًا؛ مثلًا بعد دمج فرع قديم)
```bash
gitleaks git --config .gitleaks.toml --gitleaks-ignore-path /dev/null --redact --no-banner -f json -r /tmp/gl.json .
# استخرج حقل Fingerprint فقط من /tmp/gl.json وأضفه تحت ترويسة .gitleaksignore، ثم احذف /tmp/gl.json
```
التقرير مع `--redact` لا يحوي القيم، لكن يبقى خارج الريبو ويُحذف بعد الاستخدام.

## 5. النتائج القديمة (legacy) والتدوير

فحص كامل للتاريخ (8.30.1، 2026-10-08، القيم محجوبة): **91 نتيجة / 90 بصمة في 63 ملفًا**، كلها في `.gitleaksignore`. **كل القيم وراءها تُعتبر مسرّبة** (الريبو public) ولا تُستخدم في الـ VPS الجديد.

| المجموعة | الملفات | القاعدة | ما يجب تدويره |
|---|---|---|---|
| سكريبتات root الخاصة بـ Hostinger (`check_*`, `deploy_*`, `fix_*`, `sync_*`, `seed_*`, `test_*live*`, `verify_*`, `scratch_*`, `publish_windows_version.py`, `rebuild_cache.py`, `run_check.py`, `clean_*`, `clear_views.py`, `find_domain_root.py`, `finish_subdomain.py`, `test_val.php`) | 49 (48 `.py` + `test_val.php`) | `sroor-hardcoded-password` | كلمة مرور SSH لخادم Hostinger |
| سكريبتات نشر تحتوي `.env` مضمّنًا (`deploy_root_baraa.py`, `deploy_to_sroor_subdomain.py`, `deploy_with_mysql.py`, `fix_shipping_php83.py`) | 4 | `sroor-dotenv-secret`, `sroor-laravel-app-key` | كلمة مرور DB الحية + `APP_KEY` للمحل الحي |
| `.github/workflows/deploy.yml` | 1 | `sroor-url-token`, `sroor-dotenv-secret`, `sroor-laravel-app-key` | token الـ deploy webhook + أي `APP_KEY`/سر مضمّن |
| `update_webhook.php`, `public/update_webhook.php` | 2 | `sroor-hardcoded-password` | سر الـ webhook القديم (الكود الحالي يعتمد `DEPLOY_WEBHOOK_HMAC_SECRET` من `.env`) |
| `tests_e2e/config.py` | 1 | `sroor-dotenv-secret` | credentials اختبار E2E (تحقق: إن كانت لحساب حقيقي فدوّرها) |
| `.env.e2e`, `backend/phpunit.xml` (commit قديم) | 2 | `sroor-laravel-app-key`, `sroor-dotenv-secret` | مفاتيح اختبار محلية؛ لا تُستخدم في أي بيئة حقيقية |
| `.agents/skills/laravel-security/SKILL.md` (ونسخة `backend/`) | 2 | `sroor-dotenv-secret` | أمثلة توثيقية على الأرجح؛ تحقق مرة واحدة |
| `backend/tests/Feature/Api/ProfileApiTest.php`, `backend/tests/Feature/Seeders/DatabaseSeederSecurityTest.php` | 2 | `generic-api-key` | لا شيء: قيم اختبار وهمية. عند لمس هذه الأسطر (QA-4) غيّرها لبادئة `Fixture` وإلا سيفشل الـ job |
| `backend/tests/Feature/Api/SettingsSecretsApiTest.php` (commit `0127110`)، `backend/tests/Feature/Tenancy/MediaConnectionRoutingTest.php` (commit `97e0a28`) | 2 | `sroor-hardcoded-password`, `generic-api-key` | لا شيء: tokens بوت Telegram وهمية ومفاتيح إعدادات اختبار، أُضيفت للـ baseline بعد أول CI run (2026-10-08) لأنها دُفعت بالفعل. الملفات الحالية تُركّب الـ token من أجزاء وتستخدم بادئة `fixture`، فلا يعود أي منها للظهور |

**تذكرة التدوير** — TODO(CTO): أنشئ تذكرة واحدة باسم «تدوير الأسرار القديمة قبل أول عميل» تحتوي هذا الجدول، وتُغلق بالشروط:
1. خادم Hostinger: تغيير كلمة مرور SSH وتفعيل الدخول بالمفتاح فقط، تغيير كلمة مرور DB، `APP_KEY` جديد للمحل الحي (**تحذير:** تغيير `APP_KEY` يُبطل الجلسات وأي بيانات مشفرة بـ `encrypt()`؛ خطط له مع نافذة صيانة)، token/سر webhook جديد، وبوت Telegram جديد إن ظهر token البوت في أي مكان.
2. نقل القيم من السكريبتات/`deploy.yml` إلى متغيرات بيئة أو GitHub Secrets (الأسطر الحالية لا يلمسها أي agent).
3. الـ VPS الجديد يبدأ بأسرار جديدة بالكامل (قرار CTO 2026-10-08) — لا يعتمد على هذه التذكرة.

القرار الحالي (CTO، 2026-10-08): تأجيل التدوير على Hostinger (خادم اختبار) — لكنه يستضيف محلًا حيًّا على `main`، لذا التذكرة يجب أن تُنفَّذ قبل تحويل ذلك المحل إلى مستأجر أو قبل أول عميل مدفوع، أيهما أسبق.

## 6. الـ PR التجريبي (معيار القبول «يفشل على سر مزروع»)

مُثبت آليًا في كل تشغيل للـ CI عبر `gitleaks-selftest.sh` (16 حالة). للتجربة اليدوية الموثقة مرة واحدة على GitHub (يقوم بها الـ CTO؛ لا push من أي agent):

1. من `feature/multi-tenant` أنشئ فرعًا `chore/gitleaks-canary`.
2. أضف ملفًا `canary.py` فيه سطر واحد: `PASS = "<40 حرفًا عشوائيًا>"` (ولّدها بـ `openssl rand -hex 20`؛ قيمة لا تُستخدم في أي مكان).
3. افتح PR إلى `feature/multi-tenant` → المتوقع: job `gitleaks` **أحمر** والـ log يُظهر `sroor-hardcoded-password` و`canary.py` والقيمة `REDACTED`.
4. احذف الملف في commit جديد → يبقى الـ job أحمر (السر ما زال في commit داخل النطاق) — هذا مقصود. أغلق الـ PR واحذف الفرع دون دمج.
5. سجّل رابط الـ run هنا: `TODO(CTO): رابط الـ run الأحمر`.

نتيجة محلية (2026-10-08، Windows، gitleaks 8.30.1): `gitleaks-selftest.sh` = 16 passed / 0 failed؛ فحص كل تاريخ `HEAD` مع الـ baseline = exit 0؛ نفس الفحص بدون الـ baseline = exit 1 (91 نتيجة).
