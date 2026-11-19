# سجل تعديل: إصلاحات جاهزية الإطلاق — المرحلة 1 (كروت P0 من Trello "Sroor Erp")
* **التاريخ:** 2026-11-09 (الـ branch: `feature/multi-tenant`، من غير push)
* **الدور المفعل:** تنفيذ كروت Trello عن طريق agents فرعية، والسجل ده مكتوب لاحقًا من `git show` لكل commit.
* **الهدف:** قفل بنود P0 من تقرير جاهزية الإطلاق ([الملفات](../../launch-readiness/analysis/)) — أمان، مخزون، مرتجعات، فوترة/باقات، POS، عمليات.

> **السجل مكتوب لاحقًا** من محتوى الـ commits. أرقام نجاح الـ CI/الـ suite مش مذكورة هنا لأنها ماتشغلتش على GitHub لسه (مفيش push).

## 0. الـ commits
| الكارت | commit | الوصف |
|---|---|---|
| FE-01 | `554d2e21` | جرس الإشعارات بقى يعرض تنبيهات النظام الحقيقية بدل قائمة وهمية |
| R3 | `cb54c52f` | الحد الأدنى لسعر البيع ماعادش يتحط = التكلفة تلقائيًا |
| PRD-10 | `04097d71` | إخفاء ميزة عروض الأسعار (غير مبنية) من كتالوج الباقات |
| POS-02 | `48ad9e33` | عميل الكاش (walk-in) لازم يدفع كامل |
| BE-02 | `4cd83288` | حذف المرتجع بيعكس المخزون والرصيد والتكلفة |
| OPS-04 | `edc99670` | تنبيهات Telegram نصية للباك أب + حذف مسار إرسال dump |
| BE-06 | `a6aa6a89` | إيقاف سكريبت النشر القديم |
| SEC-01 / BE-05 / PRD-02 | `ad761e16` | فرض حالة المستأجر على كل request + sweep يومي |
| POS-01 | `e65cc143` | endpoint بحث باركود دقيق + طابور مسح مرتب |
| BE-04 | `e964f1bd` | صف `store_stocks` لكل فرع، وفحص المخزون ماعادش بيتخطى |
| BE-01 | `110fea01` | المرتجع مربوط بسطور الفاتورة/الشراء |
| SEC-06 / BE-12 / PRD-01 | `b3b8d878` | فرض ميزات وحدود الباقة في الـ backend |
| — | `0cbdaf56` | إعادة توليد ترجمات الواجهة + أرقام تليفون وهمية في اختبار المرتجعات |

## 1. التفاصيل لكل كارت

### FE-01 — جرس الإشعارات
* **المشكلة:** الجرس كان بيعرض قائمة ثابتة (عميل ومبلغ مخترعين، وعدّاد 3) لكل فرع.
* **التعديل:** `Components/Layout/NotificationsBell.vue` (جديد) بيعرض التنبيهات الراجعة من `/system/context`، والعدّاد بيظهر بس لو أكبر من صفر، مع حالة فاضية مترجمة وskeleton. `SpaLayout.vue` اتخفف. مفاتيح جديدة في `lang/{ar,en}/nav.php`.

### R3 — الحد الأدنى للسعر
* **المشكلة:** صنف يتحفظ من غير `min_selling_price` كان بياخد `cost_price` كحد أدنى، والكاشير كان بيشوفه كأنه سعر الجملة.
* **التعديل:** القيمة الناقصة = `0.000` (من غير حد أدنى)؛ التحديث من غير المفتاح بيحافظ على القيمة المخزنة؛ فورم الأصناف ماعادش بيملا الحد الأدنى بسعر البيع؛ هيدر الـ POS بيخفي الحد الأدنى لو صفر.
* **أمر جديد:** `php artisan items:report-min-price-equals-cost [--tenant=ID] [--fix]` — dry-run افتراضيًا، `--fix` بيرجّع القيمة لـ `0.000` للأصناف المتأثرة.

### PRD-10 — عروض الأسعار
* `quotations.manage` مالوش routes ولا شاشات لكنه كان معروض وقابل للبيع في الباقات. بقى `is_public=false` في الكتالوج (`FeatureCatalog`) مع data migration مركزية للتثبيتات الموجودة (`2026_11_09_220000_hide_unbuilt_quotations_feature`). قوائم الباقات في الكونسول وتفاصيل المستأجر و`PlanResource` بتتجاهل المفاتيح المخفية، و`UpdatePlanAction` بيحافظ على قيمتها المخزنة. البحث عن المفاتيح المخفية متخزّن (memoized) لكل request.

### POS-02 — عميل الكاش
* **المشكلة:** البيع الآجل/الجزئي كان مقبول على عميل الكاش العام فبيطلع ديون ماحدش يحصّلها.
* **التعديل:** `InvoiceService` بيرفض أي نوع دفع غير كاش لعميل walk-in برد 422 مترجم (بعد إعادة الـ `client_uuid` عشان retry الأوفلاين يفضل شغال). بيغطي `/invoices` و`/pos/checkout` وcheckout الـ blender. الـ resources بقت بترجّع `is_walk_in`، والـ POS بيعطّل الآجل/الجزئي ويرجّع لكاش. مفتاح جديد في `lang/*/invoices.php` و`pos.php`.

### BE-02 — حذف المرتجع
* **المشكلة:** حذف مرتجع مبيعات أو مشتريات كان بيسيب حركة المخزون ورصيد الطرف ومتوسط التكلفة زي ما هم.
* **التعديل:** `DeleteReturnAction` بيعكس كل ده في transaction واحدة مع row locks مرتبة: مرتجع المبيعات يرجّع المخزون ويعيد حساب رصيد العميل؛ مرتجع المشتريات يرجّع المخزون ويعيد حساب رصيد المورد ويسترجع `weighted_avg_cost` من العمود الجديد `return_items.wac_before` (وصيغة عكسية للسجلات القديمة).
* **سلوكيات:** الحذف اللي هيخلي المخزون سالب بيترفض 422 (إلا لو السالب مسموح)؛ الحذف الثاني = 404؛ استرجاع مرتجع من سلة المحذوفات بقى ممنوع (زر الاسترجاع مخفي في `TrashTable.vue`).
* **Migration (tenant):** `2026_11_09_210000_add_wac_before_to_return_items_table`.

### OPS-04 — النسخ الاحتياطي
* خط الباك أب المشفّر لكل مستأجر كان موجود. اتشال `DatabaseBackupService` و`TelegramService::sendDatabaseBackupNotification` (كانوا ممكن يحيوا مسار إرسال dump غير مشفر لـ Telegram).
* فشل الباك أب أو restore-test بيبعت تنبيه **نصي فقط** لـ Telegram لو `BACKUP_TELEGRAM_CHAT_ID` متظبط (`Support/Backup/BackupTelegramAlert.php`).
* `docs/07-operations/backup-restore.md` اتضاف له خطوات restore rehearsal على الـ VPS.

### BE-06 — سكريبت النشر القديم
* `deploy_root_baraa.py` كان بيعمل migrate لمستأجر واحد بـ `|| true`، ويحذف صف `tenant_sroor`، ويشغّل seeders وبيانات demo. دلوقتي بيرفض يشتغل من غير `SROOR_ALLOW_LEGACY_DEPLOY`، وخطوة الـ migrate بتعمل لكل المستأجرين وتفشل النشر عند أي خطأ، من غير حذف أو seeding. أسطر الأسرار ماتلمستش.
* `scripts/ops/tests/deploy-test.sh` بيحرس `release/deploy.sh` من: seeders، tinker، `migrate:fresh`، migrate لمستأجر واحد، `|| true`، وmigrate قبل الباك أب. `AGENTS.md` و`.claude/rules/security-and-operations.md` اتحدّثوا.

### SEC-01 / BE-05 / PRD-02 — حالة المستأجر
* **الجديد:** `EnsureTenantActive` middleware بعد حل الـ tenancy على كل مسارات tenant API وgroup الـ web.
  * `trial`/`active`: وصول كامل؛ بعد انتهاء التجربة/الفترة المدفوعة: **TENANT_GRACE_DAYS** (افتراضي 3) أيام وصول كامل، بعدها read-only.
  * `read-only`: GET/HEAD/OPTIONS فقط، والكتابة = 423 `subscription.read_only`، **باستثناء** login, logout, تبديل الفرع، **إغلاق الوردية**، تحديث البروفايل، وحساب الـ blender. method spoofing بيتقيّم على الـ verb الفعلي.
  * `suspended`/`cancelled`/`archived`: 403 `subscription.access_blocked` إلا logout, me, branding, translations, ping, تحديثات التطبيق. غير المصادَق يشوف 403 عام من غير كشف الحالة، و`/auth/me` بيرجّع `user` و`system` و`subscription` بس.
* **أمر جديد (يومي في `routes/console.php`):** `php artisan tenants:apply-lifecycle [--dry-run]` — بيحرّك المستأجرين عبر `TransitionTenantStatusAction` الموجود (مع audit)، بيعزل فشل كل مستأجر، بيبلّغ عن المستأجرين المستحقين للأرشفة بدل ما يؤرشفهم، وبيعلّم trial/past-due اللي من غير deadline.
* **إعداد:** `TENANT_GRACE_DAYS=3` في `.env.example` و`config/tenant_lifecycle.php`. الواجهة بتعرض تنبيه مترجم للأكواد الجديدة (`Services/api.js`). مسارات الكونسول المركزي ماتأثرتش.

### POS-01 — بحث الباركود
* **المشكلة:** المسح كان بيضغط Enter على نتيجة بحث "contains" لسه شغالة، فممكن يضيف صنف غلط أو يضيع المسح.
* **الجديد:** `GET /api/v1/pos/lookup` (`api.pos.lookup`، `store.active`): تطابق تام على `items.code` للفرع الفعال (التكلفة مخفية من غير `items.view_cost`)، وبيفك باركود الميزان (وزن/سعر) بإعدادات الميزان الموجودة (bcmath، تحقق check digit، رفض الأصناف من غير سعر)، و404 مترجم لو مفيش. `LookupPosBarcodeAction` + `LookupPosBarcodeRequest` + `PosLookupItemResource`.
* **الواجهة:** `useBarcodeScanner` بيرتّب المسحات في طابور، مبيضيفش حاجة لو مفيش نتيجة، وبيحافظ على الكتابة اليدوية كبديل حتى أوفلاين.

### BE-04 — مخزون الفروع
* **المشكلة:** الفروع الجديدة ماكانش ليها صفوف `store_stocks`، و`deductStock` كان بيتخطى فحص الفرع لو الصف ناقص، فالبيع من فرع جديد مكانش بيفحص مخزون.
* **التعديل:** `StoreStockInitializer` بيزرع صفوف لكل صنف/فرع بالجملة عند إنشاء فرع أو صنف. الصف الناقص = صفر: البيع يترفض 422 مترجم (`InsufficientStockException`) إلا لو إعداد **`allow_negative_stock`** شغال (وقتها الصف بيتعمل تحت lock). صف محذوف soft ماعادش يصطدم بالـ unique index.
* **Migration (tenant):** `2026_11_10_100000_backfill_missing_store_stocks` — بيضيف الصفوف الناقصة من غير اختراع مخزون: الباقي (`current_stock` ناقص الصفوف الموجودة) يروح للفرع الرئيسي، باقي الفروع 0، والأصناف المخصصة أكتر من اللازم بتتسجل في اللوج بس، وإعادة التشغيل ماتغيرش حاجة.

### BE-01 — المرتجعات مربوطة بالمستند
* **المشكلة:** المرتجع كان بيقبل أي صنف/كمية/سعر وبيدّي العميل رصيد.
* **التعديل:** كل سطر مرتجع بيشاور على سطر فاتورة أو شراء؛ الكمية محدودة بالمتبقي بعد المرتجعات غير المحذوفة (محسوبة تحت lock، المستند قبل الطرف)؛ القيمة المردودة = صافي قيمة السطر في السيرفر (حصة خصم السطر والفاتورة، والوحدة الأخيرة تاخد الباقي بالظبط)؛ أسعار العميل متجاهلة؛ الطرف والفرع لازم يتطابقوا. الفواتير/المشتريات اللي عليها مرتجعات حية ماتتعدلش ولا تتلغي ولا تتحذف.
* **Endpoints جديدة:** `GET /api/v1/return-sources/{type}` و`GET /api/v1/return-sources/{type}/{id}` (`store.active:concrete`) لتغذية شاشة إنشاء المرتجع (`ReturnSourceController`, `ReturnSourceService`).
* **صلاحية جديدة:** `returns.without_invoice` — مرتجع من غير مستند، للأدمن افتراضيًا ومتعمل لها backfill على المستأجرين الحاليين (`2026_11_09_220100_seed_returns_without_invoice_permission`)، ومعرّفات الربط مش ممكن تتحقن من المسار ده.
* **Migration (tenant):** `2026_11_09_220000_add_source_line_links_to_return_items_table`.
* **الواجهة:** شاشة إنشاء المرتجع بقت تختار مستند وسطوره القابلة للإرجاع (`ReturnSourcePicker`, `ReturnSourceLinesTable`, `useReturnSource`).

### SEC-06 / BE-12 / PRD-01 — فرض الباقة
* **المشكلة:** ميزات وحدود الباقة كانت مفروضة في الـ SPA بس.
* **التعديل:** middleware `feature:<key>` على المسارات بيرجّع 403 `subscription.feature_unavailable` للوحدات اللي الباقة ملهاش (POS, الأصناف, المشتريات, التحويلات, blender, المصروفات, المدفوعات, الورديات, الخزينة, المرتجعات, التقارير, سجل التدقيق, اختبار Telegram) — بعد الـ auth فالضيف يفضل 401. `PlanEnforcer` بيفحص حدود users/stores/warehouses/vans/items جوه transaction الإنشاء تحت tenant lock (fallback بـ `Cache::lock` قبل أول فرع)، وعند استرجاع المحذوفات وتغيير نوع الفرع. اللي تجاوز الحد بيحتفظ ببياناته لكن مايقدرش يضيف (403 `subscription.limit_reached`). الباقة اللي عليها مستأجرين ماتتحذفش.
* **المستأجرين من غير باقة (legacy):** مش بيتفرض عليهم إلا لو `ENTITLEMENTS_ENFORCE_PLANLESS=true`. أمر جديد: `php artisan tenants:report-planless [--assign=<plan-slug>]`.
* الواجهة بتعرض تنبيهات مترجمة للكودين.

### `0cbdaf56`
* إعادة توليد `defaultTranslations.{js,json}` للمفاتيح الجديدة (`npm run lang:export`)، واستبدال أرقام تليفون حقيقية بوهمية في `ReturnsAgainstSourceApiTest` عشان `NoHardcodedRealPhonesTest`.

## 2. ملخص الإضافات
| النوع | العناصر |
|---|---|
| Endpoints | `GET /pos/lookup`، `GET /return-sources/{type}`، `GET /return-sources/{type}/{id}` |
| أوامر artisan | `items:report-min-price-equals-cost`، `tenants:apply-lifecycle` (يومي)، `tenants:report-planless` |
| إعدادات/env | `TENANT_GRACE_DAYS` (3)، `BACKUP_TELEGRAM_CHAT_ID`، `ENTITLEMENTS_ENFORCE_PLANLESS` (false)، `SROOR_ALLOW_LEGACY_DEPLOY`، إعداد المستأجر `allow_negative_stock` |
| صلاحيات | `returns.without_invoice` |
| أكواد أخطاء | `subscription.read_only` (423)، `subscription.access_blocked` (403)، `subscription.feature_unavailable` (403)، `subscription.limit_reached` (403) |
| Migrations مركزية | `2026_11_09_220000_hide_unbuilt_quotations_feature` |
| Migrations tenant | `2026_11_09_210000_add_wac_before_to_return_items_table`، `2026_11_09_220000_add_source_line_links_to_return_items_table`، `2026_11_09_220100_seed_returns_without_invoice_permission`، `2026_11_10_100000_backfill_missing_store_stocks` |

## 3. قرارات المالك
* **فترة السماح:** 3 أيام وصول كامل بعد انتهاء التجربة/الفترة المدفوعة، قابلة للتغيير بـ `TENANT_GRACE_DAYS`.
* **المرتجعات** مربوطة بالفواتير/المشتريات؛ المرتجع بدون مستند بصلاحية `returns.without_invoice` (أدمن افتراضيًا).
* **وضع read-only:** مسموح فيه إغلاق الوردية وتحديث البروفايل (مع login/logout/تبديل الفرع وحساب الـ blender)، لأن منعهم بيعلّق الكاشير.
* المستأجر المؤرشف/الملغي/الموقوف: حظر كامل مع استثناءات الخروج والـ branding والتحديثات.

## 4. إجراءات مطلوبة من المالك (مفتوحة)
1. **تدوير الأسرار المسرّبة** (بيانات SSH وtoken الـ webhook الموجودة في سكريبتات و CI متتبعة في git) — ما اتلمستش في أي commit هنا.
2. **تعطيل `deploy.yml`** (النشر التلقائي القديم) والاعتماد على `release.yml` → `scripts/ops/deploy.sh`.
3. **إعداد الباك أب على Google Drive** + تظبيط `BACKUP_TELEGRAM_CHAT_ID` + عمل **restore rehearsal** حسب `docs/07-operations/backup-restore.md`.
4. تشغيل `php artisan tenants:report-planless` ثم `--assign=<plan>` للمستأجرين من غير باقة (قبل تفعيل `ENTITLEMENTS_ENFORCE_PLANLESS`).
5. **مراجعة ميزات الباقة Basic** — الفرض بقى فعلي في السيرفر، فأي ميزة ناقصة من الباقة هتتقفل فعلًا.
6. تشغيل `php artisan items:report-min-price-equals-cost` (dry-run) ومراجعة النتيجة **قبل** `--fix`.
7. **إصدار نسخ Android وDesktop جديدة** عشان شاشة المرتجعات الجديدة.
8. ضبط `TENANT_GRACE_DAYS` في `.env` الإنتاج لو القيمة مختلفة عن 3، والتأكد إن `tenants:apply-lifecycle` ظاهر في `schedule:list`.
9. مراجعة تقرير `tenants:apply-lifecycle --dry-run` قبل أول تشغيل فعلي (هيحرّك مستأجرين فعليًا).

## 5. ملاحظات
* مفيش ملف tracker لحالة جاهزية الإطلاق في `docs/launch-readiness/` — المجلد فيه تحليلات وPDF وصور (غير متتبع في git)، فماتعدّلش. حالة الكروت على Trello.
* **التحقق:** السجل ده ماشغّلش أي اختبار؛ كل commit فيه ملفات اختبار خاصة بيه (مثلًا `ReturnsAgainstSourceApiTest`, `PlanEnforcementApiTest`, `TenantLifecycleEnforcementTest`, `StoreStockCompletenessTest`)، لكن نتائج تشغيلها الفعلية (Pint / Larastan / PHPUnit) ماتتسجلتش هنا ولازم تتأكد على CI بعد أول push.
* **الدور المفعل:** backend-architect / frontend-vue / devops-engineer حسب الكارت (غير موثّق بدقة في الـ commits).
