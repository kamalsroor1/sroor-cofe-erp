# سجل تعديل: Larastan + بوابات جودة CI لفرع feature/multi-tenant
* **التاريخ والوقت:** 2026-10-08 02:30
* **الدور المفعل:** backend-architect
* **الهدف:** إضافة تحليل ساكن (Larastan level 5 مع baseline) وworkflow جديد للـ CI يمنع دمج كود مكسور في `feature/multi-tenant`، بدون أن يعطّل الكود القديم غير المنسق.

## 1. الملفات المعدلة
* `[MODIFIED]` `backend/composer.json` — إضافة `larastan/larastan:^3.13` في `require-dev` + سكربتين: `analyse` و `analyse:baseline`.
* `[MODIFIED]` `backend/composer.lock` — 3 حزم جديدة فقط: `larastan/larastan v3.13.0`، `phpstan/phpstan 2.3.0`، `iamcal/sql-parser v0.7`. لم تتغير أي حزمة أخرى.
* `[NEW]` `backend/phpstan.neon` — level 5، المسارات `app/` و `routes/` و `database/`، `tmpDir` داخل `storage/framework/cache/phpstan` (متجاهَل في git).
* `[NEW]` `backend/phpstan-baseline.neon` — baseline **مؤقت** (805 خطأ في 616 مدخل).
* `[NEW]` `.github/workflows/ci.yml` — ثلاث jobs متوازية: `php` و `frontend` و `desktop`. لم يُلمس `deploy.yml`.
* `[MODIFIED]` `CLAUDE.md` — سطر `composer analyse` في قسم Commands.
* `[MODIFIED]` `.claude/rules/backend-architecture.md` — قسم "Static analysis & CI gates": أي PHP جديد/معدّل يمر من Larastan level 5 بدون إضافة للـ baseline.
* `[MODIFIED]` `AGENTS.md` — بند 19 كمرآة لنفس القاعدة.

## 2. القرارات التقنية
* **إصدار Larastan:** `v3.13.0` (أحدث إصدار مستقر). يتطلب `php ^8.2` و `illuminate/* ^11.44.2 || ^12.4.1 || ^13` و `phpstan/phpstan ^2.3`، أي يدعم Laravel 13 / PHP 8.3 رسمياً. الـ `config.platform.php = 8.3.30` في composer.json ضمن أن الحل متوافق مع 8.3.
* **level 5:** يلتقط الأخطاء الحقيقية (أنواع المعاملات، علاقات غير موجودة، خصائص غير معرّفة) بدون ضوضاء levels 6+ (missing iterable types/generics) اللي كانت هتضخم الـ baseline بلا فائدة.
* **إدخال `routes/` و `database/`:** تجربة منفصلة عليهم أعطت 20 خطأ فقط (منها `env()` خارج config في `routes/tenant.php`، و`match` غير مكتمل في migration الـ pulse)، فالضوضاء قليلة والفائدة حقيقية — تم إدخالهم.
* **`reportUnmatchedIgnoredErrors: false`:** لأن ملفات Phase 0 بتتغير الآن؛ أي إصلاح لخطأ موجود في الـ baseline كان هيكسر الـ CI. يُقترح تحويله لـ `true` بعد إعادة توليد الـ baseline النهائي.
* **حد الذاكرة:** PHPStan ليس له مفتاح neon لحد الذاكرة، فتم تمريره في سكربت composer (`--memory-limit=2G`).
* **استثناء ملفين مكسورين مؤقتاً من التحليل:** `app/Actions/Tenants/DeleteTenantAction.php` و `app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php` فيهم أخطاء syntax (كل متغيرات `$...` اتمسحت في commit `606da74b` بتاريخ 2026-08-22). أخطاء الـ parse لا يمكن وضعها في baseline فكانت هتخلي التحليل أحمر دائماً. الاستثناء معلّم في `phpstan.neon` بـ "REMOVE AS SOON AS THESE ARE FIXED". **هذا bug حي وليس دين مقبول** (انظر قسم 4).
* **Pint في CI على الملفات المتغيرة فقط:** `pint --test` محلياً فشل على 418 ملف (منهم 274 في `app/`). تشغيله على الكل كان هيمنع أي merge. الـ CI يحسب الملفات المتغيرة (`git diff --diff-filter=ACMR` مقابل `pull_request.base.sha` أو `github.event.before`، مع fallback لـ `HEAD~1`) ويشغّل `pint --test` عليها. ملاحظة: أي ملف قديم يُلمس لازم يتنسق بالكامل.
* **Prettier على الملفات المتغيرة فقط:** نفس المنطق لملفات `.js/.vue/.css` داخل `backend/` مع استبعاد `public/` و `android/` وملفات `defaultTranslations.*` المولّدة. يتم استدعاء `npx --no-install prettier --check` مباشرة بدلاً من `npm run format:check` لأن السكربت غالباً يحمل glob خاص به، وإضافة ملفات بعده كانت هتفحص الشجرة كلها.
* **مكان `npm run build`:** في job الـ `frontend` مع تثبيت PHP + composer هناك (لأن `prebuild` يشغّل `php artisan lang:export`). الرفض: (أ) البناء داخل job الـ php يخلط إشارات الفشل ويطوّل الـ job الحرج؛ (ب) `--ignore-scripts` كان هيبني بترجمات قديمة. التكلفة: composer install إضافي من الكاش.
* **لا أسرار:** الاختبارات تعتمد على `phpunit.xml` (sqlite `:memory:` + APP_KEY للاختبار)، و `.env.example` يُنسخ ثم `key:generate`. لا يوجد أي `secrets.*` في الـ workflow، والصلاحيات `contents: read`.
* **job الـ desktop:** `ELECTRON_SKIP_BINARY_DOWNLOAD=1` لأن الـ lint لا يحتاج binary الـ Electron.
* **الـ triggers:** `push` و `pull_request` على `feature/multi-tenant` فقط + `workflow_dispatch`. `main` مستبعد عمداً (كود single-tenant مختلف ويخدمه `deploy.yml`).

## 3. التحقق والاختبار
* `composer require --dev "larastan/larastan:^3.13"` — نجح، 3 حزم جديدة. `composer audit` يظهر 4 advisories موجودة مسبقاً (laravel/framework، league/commonmark ×2، league/flysystem) لا علاقة لها بالتغيير.
* `composer analyse:baseline` — فشل في المرة الأولى بسبب أخطاء الـ parse في الملفين المذكورين؛ بعد استثنائهم نجح وولّد 805 خطأ / 616 مدخل.
* `composer analyse` — **أخضر** (0 أخطاء مقابل الـ baseline)، ~17 ثانية مع الكاش.
* توزيع الـ baseline حسب الـ identifier: `property.notFound` 411، `larastan.relationExistence` 128، `nullsafe.neverNull` 91، `argument.type` 44، `nullCoalesce.expr` 31، `method.notFound` 25، `method.nonObject` 18، `assign.propertyType` 13، `argument.unresolvableType` 12، `nullCoalesce.offset` 9، والباقي أقل من 5 لكل نوع (منها `class.notFound` 4 — ناتجة عن الملفين المكسورين، و`larastan.noEnvCallsOutsideOfConfig` 3).
* توزيعه حسب المجلد: `app/Http` 456، `app/Services` 170، `app/Actions` 133، `app/Models` 18، `database/seeders` 10، `app/Console` 8، `database/migrations` 4، `routes/` 6.
* `./vendor/bin/pint --test` (قراءة فقط) — 418 ملف يفشل + خطأي parse.
* محاكاة خطوة Pint الخاصة بالـ CI محلياً على `HEAD~3..HEAD` (6 ملفات) — فشلت كما هو متوقع (xargs exit 123)، أي الآلية تعمل.
* ملف `ci.yml` تم التحقق من أنه YAML صالح (3 jobs). **لم يتم تشغيله فعلياً على GitHub** — أول push سيكون أول تشغيل.
* `php artisan test` محلياً (PHP 8.4.12، sqlite `:memory:`): 479 اختبار، 478 نجح، 1 فشل، 3942 assertion، ~7 دقائق. الفاشل: `AppUpdateApiTest::test_download_apk_throws_404_when_valid_platform_has_no_apk_file` (توقّع 404 واستلم 200). الـ controller والاختبار كلاهما معدّلان وغير مُرسلين ضمن Phase 0، فالفشل يخص العمل الجاري وليس هذه المهمة. **الـ job `php` سيكون أحمر حتى يُحل.**
* سكربتات `lint` و `format:check` أُضيفت من الوكيل الآخر أثناء العمل (`backend/package.json` و `desktop/package.json`)، وتأكد أن `format:check` يحمل قائمة مسارات ثابتة، وهو ما يبرر استدعاء Prettier مباشرة على الملفات المتغيرة. لم يتم تشغيل `npm run lint` ولا `npm run build` محلياً في هذه المهمة.

## 4. ملاحظات / ديون تقنية
* **Bug حرج (يحتاج debugger فوراً):** `DeleteTenantAction` و `UpdateTenantDatabaseConfigAction` مكسورين syntax منذ commit `606da74b`؛ endpoints السوبر أدمن لحذف مستأجر وتعديل إعدادات قاعدة بياناته ترمي `ParseError` (500). بعد الإصلاح: احذف سطري الاستثناء من `phpstan.neon` ثم `composer analyse:baseline`.
* **الـ baseline مؤقت:** يجب إعادة توليده (`composer analyse:baseline`) بعد انتهاء Phase 0، لأن الملفات تتغير الآن.
* **تمرير Pint/Prettier الشامل مرة واحدة** بعد Phase 0 (commit منفصل `style: …`)، ثم تحويل خطوتي CI لفحص كل الملفات (`pint --test` و `npm run format:check`).
* بعد استقرار الـ baseline: تحويل `reportUnmatchedIgnoredErrors` إلى `true` حتى يُجبر حذف المدخلات المصلَحة.
* `env()` مستخدم خارج `config/` في `app/Http/Resources/TenantResource.php` و `app/Services/TenantProvisionerService.php` و `routes/tenant.php` — يرجع `null` مع `config:cache` في الإنتاج.
* علاقات الموديلات بدون return types (مثل `Invoice::items()`) هي مصدر معظم أخطاء `relationExistence` و `property.notFound`؛ إضافة `: BelongsTo` / `: HasMany` ستقلص الـ baseline بشكل كبير.
* Pint بدون `pint.json` يفحص أيضاً `android/app/src/main/assets/public/update_webhook.php` و `public/` — يُقترح `pint.json` يستبعدهم.
* `deploy.yml` (لفرع main) يستخدم `composer update` بدل `composer install` — غير قابل للتكرار؛ لم يُعدّل لأنه خارج النطاق.
* فحص مقترح لاحقاً: التأكد في CI أن `defaultTranslations.*` المولّدة متطابقة مع `lang/` (`git diff --exit-code` بعد `lang:export`).
