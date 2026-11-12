# قياس الأداء والضغط (PERF-04)

> الهدف: نعيد قياس 2026-11-12 (10 محلات، 204 ألف فاتورة) بأمر واحد بعد كل إصلاح أداء، ونمسك أي رجوع للمشاكل اللي القياس ده كشفها.
> **محلي 100%**: كل الأدوات دي بتشتغل على جهاز المطوّر أو على container مؤقت. أمر التوليد بيرفض أي بيئة غير `local` / `testing`، وسكربت الضغط بيرفض أي host مش محلي. **ممنوع** تشغيلها على الإنتاج أو على قاعدة بيانات فيها بيانات حقيقية.

---

## 1. الأدوات

| الأداة | المكان | بتعمل إيه |
|---|---|---|
| `php artisan tenant:seed-perf {tenant}` | `app/Console/Commands/SeedPerfDataCommand.php` ← `App\Actions\Perf\SeedPerfDatasetAction` ← `App\Support\Perf\PerfDatasetBuilder` | يملا محل واحد ببيانات أداء ثابتة (نفس الأرقام على أي جهاز) |
| `php artisan tenant:perf-verify {tenant}` | `app/Console/Commands/VerifyPerfConsistencyCommand.php` ← `App\Actions\Perf\VerifyTenantConsistencyAction` | فحص تطابق للقراءة فقط بعد الضغط (كود خروج 1 لو فيه مشكلة) |
| `scripts/perf/load-test.sh` | bash + `ab` + `curl` + `php` | سيناريوهات الضغط + جدول النتائج + فحص التطابق |
| اختبارات PHPUnit | `tests/Feature/Console/*Perf*`، `tests/Feature/Concurrency/*`، `tests/Feature/Tenancy/CrossTenantDomainIsolationTest.php`، `tests/Feature/Api/IndexEndpointsSmokeTest.php`، `tests/Feature/Api/ReportsLargeDatasetPerformanceTest.php` | نفس الضمانات بشكل آلي في الـ CI |

---

## 2. توليد البيانات: `tenant:seed-perf`

```bash
cd backend
php artisan tenant:seed-perf perf1 --invoices=47000 --items=3000 --stores=3 --days=365
php artisan tenant:seed-perf perf2 --invoices=5000            # الباقي بالقيم الافتراضية
```

| الخيار | الافتراضي | المعنى |
|---|---|---|
| `--invoices` | 5000 | عدد فواتير البيع **بالظبط** |
| `--items` | 500 | عدد الأصناف (`PERF-I000000`…)، تلتهم بالوزن (كجم) والباقي بالقطعة |
| `--stores` | 3 | عدد الفروع؛ بيستخدم الفروع الموجودة الأول وبيعمل الناقص (`PERF-S2`…) |
| `--days` | 365 | أيام التاريخ لحد النهارده (يوم العمل حسب ساعة المحل) |
| `--customers` | `invoices/25` (بين 20 و2000) | عملاء الآجل |
| `--suppliers` | 20 | الموردين |

**بيولّد إيه:** فواتير بيع (نقدي / InstaPay / آجل) + بنودها + سند `PAY-INV` لكل فاتورة مدفوعة، تحصيلات `PAY-CUST` لتلت فواتير الآجل، مرتجعات مبيعات (`RET-SALES`، نقدي بسند `PAY-RET` سالب أو على الرصيد)، مشتريات (`PUR`) + بنودها + سداد عند الشراء (`PAY-PUR`) أو بعده (`PAY-SUPP`) أو آجل، مصروفين في اليوم (`EXP`)، رصيد افتتاحي لكل (فرع × صنف) + حركة مخزن لكل بند بالترتيب الزمني.

**الضمانات (متغطية باختبارات):**
- العدد بالظبط، ومجموع أرصدة الفروع = رصيد الصنف، ومفيش رصيد سالب (`stock:report-drift` و`tenant:perf-verify` بيطلعوا صفر).
- سند واحد لكل فاتورة نقدي، ورصيد كل عميل/مورد محسوب بخدمات التطبيق نفسها (`CustomerBalanceService` / `SupplierBalanceService`).
- الأرقام بنفس صيغة التطبيق وبتكمّل بعد آخر رقم موجود، وعدّادات `document_sequences` بتقف عند آخر رقم: أول بيع حقيقي بعد التوليد بياخد الرقم اللي بعده من غير تصادم.
- مفيش أي مستند متختم بعد لحظة التشغيل (آخر دقيقة مستبعدة)، فوردية تتفتح بعد التوليد مبتشوفش سندات مولّدة.
- **ثابت (deterministic):** مفيش random؛ كل اختيار دالة hash على رقم المستند، فنفس الخيارات = نفس الصفوف على أي جهاز.
- **ذرّي:** التوليد كله transaction واحدة (إدخال مجمّع على دفعات جواها)؛ لو فشل مفيش حاجة بتتكتب.
- **مرتين؟** التشغيل بيسيب علامة في `settings` (`perf_seed_marker`). تشغيل تاني بنفس الخيارات ميكتبش حاجة ويخرج بنجاح؛ بخيارات تانية بيرفض (كود 1). **مفيش `--fresh`** عن قصد: أداة أداء مينفعش تمسح بيانات محل ممكن يكون فيه بيانات تانية. عايز بيانات جديدة؟ اعمل محل جديد.
- **بيوقف أدوات المراقبة وهو شغال** (`App\Support\Perf\QuietInstrumentation`): Telescope وPulse بيقفوا تسجيل، الـ connection من غير event dispatcher (مفيش `QueryExecuted` لأي listener) ومن غير query log، وأحداث الـ Eloquent مقفولة. مع Telescope شغال التوليد كان أبطأ ~200 مرة. (Nightwatch مش متسطب في المشروع.)

**تبسيطات مقصودة:** مفيش خصومات ولا فواتير ملغاة ولا ورديات تاريخية، والشراء بنفس تكلفة الصنف (متوسط التكلفة ثابت). `tenant:populate-realistic-data` لسه موجود للبيانات "الواقعية" عن طريق الخدمات (بطيء، حجم ديمو)؛ مش أداة أداء.

---

## 3. فحص التطابق: `tenant:perf-verify`

```bash
php artisan tenant:perf-verify perf1                               # بدون لقطة
php artisan tenant:perf-verify perf1 --snapshot=/tmp/perf1.json    # قبل الضغط: يكتب أرصدة الفروع وآخر IDs
php artisan tenant:perf-verify perf1 --baseline=/tmp/perf1.json    # بعد الضغط: يقارن
```

الفحوصات (كل مشكلة بتطلع باسمها وأول 10 صفوف):

| الفحص | يفشل لو |
|---|---|
| `duplicate_number` | رقم مستند متكرر في الفواتير / السندات / المرتجعات / المشتريات / المصروفات |
| `stock_drift` | مجموع `store_stocks` ≠ `items.current_stock` |
| `negative_stock` | رصيد فرع بالسالب والإعداد مش سامح |
| `missing_voucher` / `voucher_mismatch` | فاتورة معتمدة سنداتها (بيع + تحصيل) مش بتساوي `paid_amount`، أو فاتورة نقدي من غير سند |
| `customer_balance` / `supplier_balance` | الرصيد المتخزن ≠ معادلة التطبيق من المستندات |
| `stock_vs_baseline` (مع `--baseline`) | رصيد الفرع ≠ البداية − المباع + المرتجع + أي حركة تانية من بعد اللقطة |

`invoices.invoice_number` عليه unique index، فالتكرار مينفعش يتكتب طول ما الـ index موجود؛ الاختبار (`PerfVerifyCommandTest`) بيشيل الـ index الأول (زي قاعدة الـ index ماوصلهاش) وبعدين يحقن التكرار. يعني الفحص ده خط دفاع تاني ورا الـ index.

---

## 4. سكربت الضغط: `scripts/perf/load-test.sh`

### الإعداد (مرة واحدة)
```bash
cp scripts/perf/perf.env.example scripts/perf/perf.env   # git-ignored
# املا PERF_BASE_URL و PERF_TENANTS و PERF_TOKENS (توكن Sanctum لكل محل بنفس الترتيب)
```
- التوكنات **محلية ومؤقتة** من محل تجريبي، مثلًا:
  `php artisan tinker --execute="echo App\Models\Tenant::find('perf1')->run(fn () => App\Models\User::query()->orderBy('id')->first()->createToken('perf')->plainTextToken);"`
- المستخدم لازم يكون غيّر كلمة المرور الأولى (`must_change_password = false`)، وإلا كل الطلبات بترجع 403 `auth.password_change_required`.
- ممكن تستخدم ملف إعداد تاني بـ `PERF_CONFIG=/path/to/file`.
- السكربت بيرفض أي host غير `localhost` / `127.0.0.1` / `*.localhost` / `*.test`، ومفيش خيار يتخطى ده (السكربت بيفتح ورديات وبيسجل مبيعات حقيقية). **مفيش أي كلمة سر أو توكن في الريبو.**

### التشغيل
```bash
# سيرفر محلي بأكتر من worker ومن غير Telescope/Pulse
cd backend && TELESCOPE_ENABLED=false PULSE_ENABLED=false PHP_CLI_SERVER_WORKERS=8 php artisan serve --port=8094 &
../scripts/perf/load-test.sh            # أو --skip-verify
```
السكربت لازم يتشغل بنفس متغيرات البيئة (قاعدة البيانات) بتاعة السيرفر، لأنه بينادي `php artisan tenant:perf-verify` قبل وبعد.

### السيناريوهات
| السيناريو | الطلب | التزامن |
|---|---|---|
| تصفح POS | `GET /pos/items?per_page=50` | 5 |
| قائمة الفواتير | `GET /invoices?per_page=25` | 5 |
| الرئيسية لوحدها | `GET /dashboard` | 1 |
| الرئيسية تحت ضغط | `GET /dashboard` | `PERF_USERS` (20) |
| الأصناف | `GET /items?per_page=50` | 1 |
| ملخص الخزينة | `GET /treasury/summary` | 1 |
| بيع متزامن عبر كل المحلات | `POST /pos/checkout` على كل محل في نفس الوقت | 4 لكل محل |
| 5 كاشير على فرع | `POST /pos/checkout` | 5 |
| تقرير سنة جنب البيع | `GET /reports/comprehensive?from=…&to=…` + بيع | 2 + 5 |
| دفعة عزل | `GET /auth/me` مخلوط على كل المحلات، 24 بالتوازي؛ لازم كل رد يكون مستخدم نفس المحل، وتوكن محل على محل تاني = 401 | 24 |

`PERF_PERCENT` بيصغّر عدد الطلبات (مثلًا `PERF_PERCENT=25` على جهاز مشغول؛ أقل حاجة طلبين لكل سيناريو)، و`PERF_USERS` عدد مستخدمي الرئيسية المتزامنين. البيع بيستخدم صنف بالوزن وكمية `PERF_SALE_QTY` (0.050) عشان المخزون ميخلصش، وبيفتح وردية لو الفرع بيطلبها. الجدول بيطلع: السيناريو، عدد الطلبات، الوسيط، p95 (بالمللي ثانية، من زمن كل طلب في `ab -g`)، والفشل (Failed + Non-2xx). بعده `tenant:perf-verify --baseline` لكل محل. أي فشل ← كود خروج 1.

---

## 5. الاختبارات في الـ CI

| المجموعة | فيها | بتشتغل فين |
|---|---|---|
| `mysql` | `IndexEndpointsSmokeTest` (كل GET list، ومرة كمان على محل متولّد بمدى سنة و`X-Store-Id: all`)، `SeedPerfDataCommandTest`، `PerfVerifyCommandTest`، `CrossTenantDomainIsolationTest`، والباقي | job `mysql` (`phpunit.mysql.xml`)؛ اللي مش `mysql-only` بيشتغل كمان على sqlite في job `php` |
| `mysql-only` + `concurrency` | `ConcurrentBusinessDayTest` (يوم شغل كامل على 3 محلات في نفس الوقت)، `BranchSalesAndTransfersRaceTest` (3 فروع: 72 بيع + 18 تحويل + سباق 6×300 من 1000)، `MysqlConcurrencyTest`، `MysqlLockConflictTest` | job `mysql`، وjob `mariadb` (MariaDB 11.8.8) بيشغل مجموعة `concurrency` |
| `mysql-only` | `ReportsLargeDatasetPerformanceTest` (50 ألف فاتورة → تقارير السنة 200 وذاكرة ≤ 64MB؛ الزمن `PERF_REPORT_MAX_SECONDS`) | job `mysql` |

تشغيل محلي على container مؤقت (مش قاعدة التطوير):
```bash
docker run -d --name perf-mysql -p 127.0.0.1:3421:3306 -e MYSQL_ALLOW_EMPTY_PASSWORD=yes mysql:8.4
cd backend
DB_HOST=127.0.0.1 DB_PORT=3421 DB_USERNAME=root DB_PASSWORD= php artisan test -c phpunit.mysql.xml --filter='SeedPerfDataCommandTest|PerfVerifyCommandTest|ConcurrentBusinessDayTest|BranchSalesAndTransfersRaceTest|CrossTenantDomainIsolationTest|IndexEndpointsSmokeTest'
docker rm -f perf-mysql
```
(نفس الكلام على `mariadb:11.8.8`.)

مفيش مجموعة nightly منفصلة لحد دلوقتي: أتقل اختبار (`ReportsLargeDatasetPerformanceTest`، 50 ألف فاتورة) أصلًا في job `mysql`. لو احتجنا حجم أكبر (مثلًا 200 ألف فاتورة) يبقى سكربت الضغط على container محلي هو المكان، مش الـ CI.

---

## 6. الأرقام المرجعية (baseline)

### قياس 2026-11-12 (قبل PERF-02، سكربتات مؤقتة برا الريبو)
10 محلات، 204 ألف فاتورة إجمالًا.

| السيناريو | الزمن |
|---|---|
| الرئيسية لوحدها | 0.16 – 0.39 ث |
| الرئيسية تحت 20 مستخدم | الوسيط 1.1 ث / p95 2 – 2.8 ث |
| تصفح POS | 0.30 ث |
| قائمة الفواتير | 0.34 ث |
| بيع | 0.29 ث |
| `/items?per_page=50` | 0.21 – 0.24 ث |
| ملخص الخزينة | 0.09 – 0.19 ث |

### أهداف PERF-02
الرئيسية لوحدها ≤ 150 مللي ثانية (~47 ألف فاتورة)، الرئيسية تحت 20 مستخدم p95 ≤ 800 مللي ثانية، `/items?per_page=50` ≤ 100 مللي ثانية.

### قياس PERF-04 (أول تشغيل للسكربت)
**الظروف (مهمة جدًا لقراءة الأرقام):** 2026-11-12، لابتوب Fedora بـ 8 أنوية **مشترك** وقت القياس مع test suites لمشاريع تانية (load average بين 41 و57 طول التشغيل)، MySQL 8.4 في container مؤقت على btrfs، السيرفر `php artisan serve` بـ 8 workers (PHP built-in server، مش FPM)، `APP_ENV=local`، Telescope/Pulse مقفولين، `CACHE_STORE=database`. 3 محلات: `perf1` = 47,000 فاتورة / 3,000 صنف / 3 فروع، و`perf2` و`perf3` = 5,000 فاتورة لكل واحد (كله بـ `tenant:seed-perf`). السكربت اتشغل بـ `PERF_PERCENT=50`.

| السيناريو | الطلبات | الوسيط ms | p95 ms | فشل |
|---|---|---|---|---|
| POS browse (pos/items، c=5) | 100 | 5018 | 7476 | 0 |
| Invoice list (c=5) | 100 | 3902 | 7215 | 0 |
| Dashboard alone (c=1) | 10 | 1334 | 2488 | 0 |
| Dashboard (20 users) | 100 | 18738 | 28912 | 0 |
| Items per_page=50 (c=1) | 50 | 616 | 1297 | 0 |
| Treasury summary (c=1) | 50 | 570 | 1426 | 0 |
| Sales across 3 tenants (c=4 each) | 60 | 10791 | 19275 | 0 |
| 5 cashiers, one branch | 50 | 5536 | 11727 | 0 |
| Year report next to sales | 5 | 19819 | 21719 | 4 (*) |
| Sales next to a year report | 30 | 11161 | 21593 | 0 |
| Isolation burst (3 محلات، 24 بالتوازي) | 60 | - | - | 0 |

(*) الـ 4 "فشل" كانوا `Length` في `ab` (حجم الرد اتغير لأن البيع شغال جنب التقرير)، مش أخطاء: التقرير نفسه رجع 200. السكربت بقى يشغّل `ab -l` عشان ميعدّش ده فشل، وتشغيل تاني صغير (`PERF_PERCENT=10`) بعد التعديل خلص بـ 0 فشل في كل السيناريوهات وكود خروج 0.

فحص التطابق بعد التشغيل: `perf1` و`perf2` و`perf3` كلهم متطابقين (مفيش رقم متكرر، المخزون = البداية − المباع، سند لكل فاتورة نقدي، أرصدة العملاء والموردين).

**مقارنة بأهداف PERF-02 على نفس الجهاز وقتها** (طلبات داخل نفس الـ process من غير web server، دافية، 6 مرات بعد أول طلب):

| الطلب على `perf1` | أسرع | الوسيط | الهدف |
|---|---|---|---|
| `/ping` (مرجع: تكلفة الإطار لوحده) | 68 | 93 | - |
| `/dashboard` | 182 | 380 | ≤ 150 |
| `/items?per_page=50` | 331 | 905 | ≤ 100 |
| `/treasury/summary` | 205 | 653 | - |
| `/pos/items?per_page=50` | 167 | 374 | - |
| `/invoices?per_page=25` | 238 | 305 | - |

الأرقام دي **مش صالحة للحكم على الأهداف**: حتى `/ping` واخد 70-90 مللي ثانية بسبب الحمل على الجهاز (القياس الأصلي كان على جهاز فاضي). اللي بتثبته: كل السيناريوهات بترجع من غير أخطاء ومن غير خلط بين المحلات، والتطابق سليم تحت الضغط. لازم نعيد التشغيل على جهاز فاضي (محلي؛ الأدوات دي بترفض staging والإنتاج) قبل ما نقول إن أهداف PERF-02 اتحققت؛ ولاحظ إن `/items?per_page=50` أبطأ نسبيًا من الباقي حتى هنا.
