# كتالوج إعدادات المستأجر (Tenant Settings Catalog)

> **التاريخ:** 2026-10-08 · **الحالة:** مراجعة 2 — أسئلة §7 الـ 12 اتجاوبت من الـ CTO بتاريخ 2026-10-09 (التعديلات معلَّمة **[CTO-2026-10-09]**). فاضل تأكيدين فرعيين بس في حد الائتمان (§7 بند 3) · **الفرع:** `feature/multi-tenant`
> **المصادر:** جرد الكود الحالي (قراءة فقط، المسارات تحت `backend/`) + بحث في مراكز المساعدة الرسمية للمنافسين (Loyverse، Square، Shopify POS، Odoo POS، Lightspeed، Zoho Inventory/POS، Foodics، Daftra، Qoyod، Rewaa).
> **القرارات المحترمة:** `docs/01-overview/product-overview.md`، قرارات الـ CTO بتاريخ 2026-10-08، و`docs/04-ux-ui/pos-competitive-study.md` §8.
> **تنبيه:** أي بند مكتوب عليه **(غير مؤكد)** معناه إن المصدر كان snippet من بحث، أو الصفحة رجّعت خطأ، أو مفيش منافس بيوثقه. النصوص هنا صياغتنا، ومفيش نص منسوخ من المصادر.

**مفتاح الأعمدة:**
- **النطاق:** `platform` (super-admin)، `tenant`، `store` (فرع/مخزن/سيارة)، `user`/`role`، `device` (جهاز كاشير).
- **الكود:** ✅ موجود ويعمل · 🟡 جزئي/مكسور · ❌ ناقص.
- **الباقة:** «الكل» = core في كل الباقات. غير كده نكتب الـ feature key: `reports.advanced`، `transfers.manage`، `purchases.reorder`، `audit.logs`، `mixes.manage`، `pos.offline`، `api.access`، وETA (add-on في Phase 3).
- **الأولوية:** Must / Should / Could.
- **المرحلة:** P1a/P1b (حسب `docs/05-planning/phase-1-plan.md`)، P2، P3.
- **المخاطر:** 💰 مال · 📦 مخزون · 🔒 أمان.

---

## 1. الملخص

| الأولوية | العدد | منها في Phase 1 (1a/1b) |
|---|---|---|
| Must | 38 (منها 2 مشروطين بقالب البيع بالوزن) | 38 |
| Should | 125 | 52 |
| Could | 69 | 0 |
| **الإجمالي** | **232 بند** بعد إزالة التكرار بين المجالات | 90 |

> **مراجعة 1 (2026-10-08):** اتنقل 3 بنود من Must لـ Should P1a (`account.billing_contact`، `reports.branding_header`، `locale.default`)، واترقّى `pos.customer_display.enabled` لـ Must (قرار POS §8 بند 8)، واتضاف مجال المشتريات والتوزيع (§3.11، 8 بنود: 4 Should في P2 و4 Could).
>
> **مراجعة 2 [CTO-2026-10-09]:** دخل Phase 1 تلات بنود: `inventory.negative_stock.policy` (P2 ← P1a، Q2)، و`locale.business_day_cutoff` (P2 ← P1a، Q6)، و`branding.powered_by` (Could P2 ← Should P1a حسب الباقة، Q8). و`notifications.channels.telegram` بقى ببوت المنصة في P1a (Q5)، وكان محسوب أصلًا في Phase 1.

بعض البنود بتجمع كذا مفتاح في سطر واحد، زي `loyalty.*` (8 مفاتيح) و`einvoice.eta.*` (5) و`receivables.reminders.*` (5). كده العدد الفعلي للمفاتيح أكبر من 240. وعروض الأسعار، وتنبيه دخول الموظفين، وتذكير الموردين متسجلين كملاحظات Could/P3 برّه العدد.

التوزيع على المجالات (بنود): نقطة البيع والورديات 35، والفواتير والطباعة 33، والمخزون 32، والمالية والضريبة 25، والعملاء والموردين 22، والمستخدمين والأمان 22، والإشعارات 18، والهوية والتوطين 18، والحساب والاشتراك 11، والتقارير 8، والمشتريات والتوزيع 8. ولما يتكرر بند بين مجالين، بيتسجل في مجال واحد بس: السالب في المخزون، وحد النقص في المخزون، وتذكير المديونيات في الإشعارات، ونهاية اليوم التجاري في التوطين، والضريبة على العميل في المالية.

**الصورة العامة:** الكود فيه حوالي 20 مفتاح إعداد بس. **أغلب إعدادات الطباعة مكسورة**: بتتحفظ ومحدش بيقراها. والـ A4 بيطبع بيانات وهمية. والمنصة مفيهاش ولا قاعدة تشغيل قابلة للضبط (خصم، سعر، وردية، دفع، سالب). المنافسين الأقوياء (Loyverse وOdoo وFoodics وZoho) بيدّوا المالك تحكم في كل ده. الفرصة اللي بتميّزنا: التحكم **على مستوى الفرع** (override)، وواتساب وTelegram بشكل native، وإعدادات البيع بالوزن.

### أهم 15 إعداد لازم يشتغلوا قبل أول بيع

| # | الإعداد | السبب |
|---|---|---|
| 1 | هوية المحل والفرع على الإيصال: `company.name/logo/legal_*` و`store.phone/address` | الـ A4 بيطبع تليفون وسجل تجاري ورقم ضريبي وهميين، وده عيب قانوني وتجاري |
| 2 | `receipt.header_lines` و`receipt.footer_text` (الموجود `invoice_footer_note` مكسور) | أول حاجة المالك بيطلبها |
| 3 | `printing.paper_width` و`printing.auto_print` لكل جهاز | 58/80 مم. حاليًا في localStorage على Electron بس |
| 4 | `finance.currency` + `locale.timezone` + الأرقام الغربية (ثابتة) | قرار CTO §10، ‏SETG-1/2/6 |
| 5 | `payments.methods`: قائمة واحدة لطرق الدفع، مع فودافون كاش وإنستاباي | فيه 8 أماكن (7 Form Requests بقوائم `in:` حرفية + ثابت `CHECKOUT_PAYMENT_METHODS`) بـ 3 مجموعات قيم مختلفة، ومفيش قيمة صريحة لفودافون كاش (بس `e_wallet`) 💰 |
| 6 | `pos.default_customer_id` (العميل النقدي) | الاسم hardcoded بثلاث صيغ مختلفة |
| 7 | `shift.required_to_sell` | البيع حاليًا مش مربوط بوردية خالص 💰 |
| 8 | `pos.discount.max_percent` + `pos.manager_approval.actions` + PIN المدير | قرار POS §8 بند 6. مفيش أي حد للخصم دلوقتي 💰 |
| 9 | `pos.price.floor_policy` (البيع تحت أقل سعر أو تحت التكلفة) | `unit_price` شرطه `min:0` بس 💰 |
| 10 | `invoices.cancel.cashier_window` | قرار CTO §9 |
| 11 | `pos.credit_sale.enabled` + `customers.credit.limit_mode` | الآجل بقى tender جوه `payments[]`، و`credit_limit` مالوش عمود في الـ DB 💰 |
| 12 | `inventory.units` (الوحدات المسموحة) | محفوظة ومش مستخدمة، والقائمة hardcoded في 6 أماكن |
| 13 | `pos.scale_barcode.*` لكل فرع (Must لو المالك اختار قالب البيع بالوزن) | المحلات اللي بتبيع بالوزن من ضمن السوق المستهدف (قرار POS §8 بند 3) |
| 14 | الأدوار والصلاحيات + فروع كل مستخدم (`security.roles`، `security.user_store_access`) | موجودة جزئيًا. صلاحية `settings.manage` مش متعمل لها seed أصلًا 🔒 |
| 15 | قفل العملة بعد أول فاتورة + إخفاء الأسرار (`telegram_bot_token`) | الـ GET بيرجّع التوكن نص صريح 🔒. **[CTO-2026-10-09]** الإخفاء اتعمل في SETG-7 (W1)، والتوكن لكل مستأجر هيتشال خالص لصالح بوت المنصة (Q5) |

---

## 2. مبادئ نظام الإعدادات

### 2.1 الطبقات وترتيب الحل (resolution)

```
code default  ←  platform default (super-admin)  ←  tenant  ←  store override  ←  user / device override
```

- كل إعداد بيعلن **أعلى نطاق مسموح** بيه. مثلًا `finance.currency` نطاقه tenant بس، و`printing.paper_width` نطاقه device.
- الـ override على مستوى الفرع **اختياري**. القيمة `inherit` معناها «زي إعداد المحل». الشاشة بتعرض القيمة الموروثة ومصدرها.
- الـ super-admin يقدر **يقفل** إعداد على قيمة معينة (`locked_by_platform`)، زي الأرقام الغربية أو مدة التوكن 30 يوم. الإعداد المقفول بيظهر للمالك read-only.
- تفضيلات الجهاز (الطابعة، عرض الورق، الصوت، إظهار الكتالوج) بتتنقل من `localStorage` لـ **device profile** مسجّل على السيرفر ومربوط بالجهاز. ده عشان الإعدادات متضيعش لما نمسح المتصفح أو نعيد تثبيت التطبيق. وبيفضل فيه fallback محلي (P2، بالتوازي مع APP/OFFL).

### 2.2 التخزين

- **Registry مُنمَّط في الكود** (توسيع SETG-1): `app/Settings/Definitions/*.php` أو `config/tenant-settings.php`. كل تعريف فيه: `key`، `type` (bool/int/decimal-string/enum/json/text/secret/file)، `default`، `max_scope`، `rules`، `permission`، `feature` (الباقة)، `sensitive`، `group`، `advanced`، `aliases_ar` (عشان البحث).
- **القيم** في جدول `settings` الموجود في tenant DB، بعد ما نضيف `scope_type` و`scope_id` (nullable = tenant) و`updated_by`، ومفتاح unique على (`key`، `scope_type`، `scope_id`). الفلوس بتتخزن **string decimal** وبتتحسب بـ bcmath، مش float.
- **الـ cache:** نفضل على `TenantCache` (موجود في `app/Support/TenantCache.php`)، مفتاح واحد لكل مستأجر + مفتاح لكل فرع، وبيتعمل له invalidate عند الكتابة. ونستبدل `rememberForever` الحالي بـ bump للـ version، عشان التغيير يظهر في الطلب اللي بعده.
- **إعدادات المنصة** في `platform_settings` المركزي (BRND-1)، **ومينفعش** تتحط في جدول `settings` بتاع المستأجر. الحالة دي سبب الـ bug الحالي في الـ super-admin (§5).
- **الأسرار** (`telegram_bot_token`، ومفاتيح ETA، وwebhook secrets) بتتخزن بـ `encrypted` cast، **write-only**: الـ API بيرجّع `is_set: true` بس، ومش بتتصدّر ولا بتظهر في الـ audit.
- **الملفات** (اللوجو، الختم) بتروح medialibrary على disk المستأجر (BRND-5/PKG-2)، والإعداد بيشاور على الـ media id.

### 2.3 الصلاحيات (مين يعدّل إيه)

| المجموعة | المالك (owner/admin) | المدير (manager) | الكاشير |
|---|---|---|---|
| الحساب والاشتراك، الأمان، الضريبة وETA، العملة والمنطقة الزمنية | ✅ | ❌ (يشوف بس) | ❌ |
| نقطة البيع، الطباعة، المخزون، العملاء، الإشعارات | ✅ | ✅ **في فروعه بس** (store override) | ❌ |
| تفضيلات الجهاز والمستخدم (الطابعة، الثيم، اللغة) | ✅ | ✅ | ✅ لنفسه/لجهازه |

- صلاحيات جديدة: `settings.view`، `settings.manage`، ومعاهم صلاحية لكل مجموعة حساسة: `settings.security.manage`، `settings.finance.manage`، `settings.account.manage`. **حاليًا** الحماية شغالة على `hasRole('admin') || can('roles.manage') || can('settings.manage')`، و`settings.manage` **مش موجودة في `PermissionsSeeder`**.
- الـ manager مقيّد بالفروع اللي في `StoreAccess`. مينفعش يعمل override لفرع مش بتاعه، ولازم test عزل يغطي ده.
- **إعدادات الرقابة للمالك بس، حتى على مستوى الفرع:** الإعدادات اللي بتتحكم في صلاحيات الـ manager نفسه مينفعش يضعّفها بـ store override. القائمة: `pos.manager_approval.actions`، `pos.discount.max_percent`، `pos.price.floor_policy`، `inventory.negative_stock.*`، `shift.variance_tolerance`، `invoices.cancel.cashier_window`، `customers.credit.limit_mode`، `pos.returns.window_days`. الكتابة عليها (tenant أو store) محتاجة صلاحية جديدة `settings.controls.manage`، ومش بتتدّي للـ manager في الـ seed. والـ registry بيعلّم الإعدادات دي بـ `control: true`.
- **[CTO-2026-10-09] Q7 — اتعتمد:** المصفوفة اللي فوق معتمدة كما هي: المالك يعدّل كل حاجة، والـ manager يعدّل نقطة البيع والمخزون والطباعة في فروعه بس، والتفضيلات الشخصية للكل، والحساب والأمان والمالية للمالك بس. و`settings.controls.manage` **صلاحية مستقلة** (مش مدموجة في `settings.finance.manage`)، تقدر تتدّي لمدير عام موثوق من غير صلاحيات المالية، ومش بتتدّي لمديري الفروع في الـ default.
- **test مطلوب:** manager معاه `settings.manage` وعنده `StoreAccess` على الفرع، بيحاول يعمل override لـ `pos.discount.max_percent` أو `inventory.negative_stock.policy` على فرعه ← 403، والقيمة متتغيرش. ونفس الطلب من المالك ← 200. وmanager على فرع مش بتاعه ← 403 (عزل).

### 2.4 التحقق والقيم الافتراضية

- قواعد الـ Form Request **بتتولد من الـ registry**، بدل القائمة اليدوية في `UpdateSettingsRequest.php`. أي قيمة مش صالحة ترجع 422 برسالة مترجمة.
- لكل إعداد default آمن **يحافظ على السلوك الحالي**: `negative_stock=block`، `vat_enabled=false`، `shift.required_to_sell=false` في الكود. **[CTO-2026-10-09] Q1:** القيمة الفعلية بتيجي من القالب: إلزامي في التجزئة والوزن، واختياري في الجملة والسيارات، والمالك يقدر يغيّرها في الاتجاهين (§2.8). والمحل الحالي بعد الترحيل لسه سؤال مفتوح (هل بيشتغل بورديات النهارده؟).
- **إعدادات غير قابلة للتغيير بعد أول حركة:** `finance.currency`، `inventory.costing_method`، `inventory.batch_tracking` (لما يتفعّل). الواجهة بتعرض السبب.
- **التغييرات الحساسة تسري على المستندات الجديدة بس:** الترقيم، والضريبة، والتقريب. الفاتورة المصدرة immutable (قرار §11).

### 2.5 سجل التعديلات

- كل تعديل إعداد بيتسجل فيه: القيمة القديمة، الجديدة، النطاق، المستخدم، IP، والجهاز. الأسرار بتتسجل بقيمة `***`. فيه حاليًا جدول `activity_logs` مخصص (`ActivityLogService`)، والمخطط اعتماد `spatie/laravel-activitylog` في Phase 1 (PKG).
- عرض السجل مربوط بـ `logs.view`، ومدة الاحتفاظ مرتبطة بباقة `audit.logs` (§3.6).

### 2.6 الربط بالباقات (plan gating)

- الـ registry بيحدد `feature` لكل إعداد، والتحقق بيعدّي على `TenantEntitlementService` / Pennant (قرار الحزم). الإعداد المقفول بالباقة بيظهر بقفل وزر «ترقية/أضف add-on»، **ومش بيستخفى**، لأن ظهوره بيساعد في البيع.
- عند الـ downgrade: **القيمة بتفضل محفوظة، والسلوك بيرجع للـ default**. مفيش حذف بيانات.
- الحدود (عدد المستخدمين والفروع والأجهزة) **مش إعدادات للمستأجر**. دي entitlements بيحددها الـ super-admin.

### 2.7 التصدير والاستيراد

- تصدير JSON فيه `schema_version`، والأسرار والملفات مش بتتصدر. والاستيراد بيعمل **dry-run diff** الأول («هيتغير 12 إعداد») وبعدين التأكيد، وكل ده بيتسجل في الـ audit.
- الاستخدام: نسخ إعدادات فرع لفرع جديد، ونقل إعدادات المحل الحالي (ترحيل main ← tenant)، وتهيئة عملاء خدمة الـ onboarding.

### 2.8 قوالب «إعدادات موصى بها حسب نوع النشاط» (onboarding)

في أول تشغيل، المالك يختار نوع النشاط، والقالب يملأ القيم (ويقدر يغيرها بعد كده):

**[CTO-2026-10-09] Q11:** القوالب في 1a تلاتة بس: تجزئة، وجملة وتوزيع، وبيع بالوزن (SETG-12). قالب الموبايلات والإلكترونيات (سيريال) وقالب الصيدلية والبقالة (صلاحية وتشغيلات) بييجوا مع الميزات دي في Phase 3، والملابس (مقاسات وألوان) بعد كده.

| الإعداد | تجزئة | جملة وتوزيع | بيع بالوزن (عطارة/بن/لحوم/حلويات) |
|---|---|---|---|
| `pos.default_customer_id` | عميل نقدي | لا شيء (العميل إلزامي) | عميل نقدي |
| `pos.customer_required` | `credit_only` | `always` | `credit_only` |
| `pos.credit_sale.enabled` | false | true | false |
| `pricing.price_lists.enabled` | false | true (جملة/قطاعي) | false |
| `pos.scale_barcode.enabled` | false | false | **true** |
| `inventory.quantity_decimals` | 0 | 0 | **3** |
| `pos.cash_rounding.enabled` | true (0.50) | false | true (0.50) |
| `printing.paper_width` | 80mm | A4 | 80mm |
| `receipt.show.customer_balance` | false | **true** | false |
| `customers.credit.limit_mode` | warn | **block** | warn |
| `shift.required_to_sell` | true (إلزامي) | false (اختياري، مندوب/سيارة) | true (إلزامي) — **[CTO-2026-10-09] Q1**، والمالك يغيّرها في الاتجاهين |
| `inventory.negative_stock.policy` | block | warn | warn (الوزن الفعلي بيختلف) |

> **[CTO-2026-10-09]** قيم `inventory.negative_stock.policy` في القوالب دي اقتراح الكتالوج. قرار Q2 حدّد الـ default = `block` ومذكرش القوالب، فقيم الجملة والوزن تتأكد عند تنفيذ SETG-12. وقيم `customers.credit.limit_mode` مستنية تأكيد default الـ `warn` (§7 بند 3).

القوالب بتتخزن JSON في الكود. والـ super-admin يقدر يعدّلها من `platform_settings` في P2.

---

## 3. الكتالوج حسب المجال

### 3.1 نقطة البيع والورديات (POS & Shifts)

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `pos.default_customer_id` | العميل الافتراضي | العميل اللي بيتحط تلقائيًا في كل فاتورة جديدة (عميل نقدي). العميل النقدي كيان واحد على مستوى المستأجر، والفرع يقدر يختار غيره | tenant (+store) | fk = walk-in | [Daftra](https://docs.daftra.com/en/?p=3989) | ❌ hardcoded بثلاث صيغ: `GetPOSBootstrapDataAction.php:152-154` (الـ fallback array)، `GetDailyJournalAction.php:99`، `GetDashboardOverviewAction.php:205` | الكل | Must | P1a | — |
| `pos.default_payment_method` | طريقة الدفع الافتراضية | الطريقة اللي بتكون مختارة في شاشة الدفع | store | fk payment_method = cash | [Daftra](https://docs.daftra.com/en/?p=3989)، [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/) | ❌ | الكل | Should | P1a | — |
| `pos.customer_required` | إلزام اختيار عميل | مش مطلوب أبدًا / مطلوب للآجل بس / مطلوب دايمًا | store | enum `never\|credit_only\|always` = `credit_only` | [Odoo](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/payment_methods.html) | 🟡 الآجل بيطلب عميل (غير مؤكد في الواجهة) | الكل | Should | P1a | 💰 |
| `pos.credit_sale.enabled` | السماح بالبيع الآجل في الكاشير | الآجل كـ tender جوه `payments[]`، ومعاه صلاحية `pos.credit_sale` | store | bool = true | [Foodics](https://foodics.helpjuice.com/96010-manage-customers/558936-customers-overview)، [Odoo](https://cybrosys.com/blog/how-to-configure-payment-methods-in-odoo-17-pos) | 🟡 نوع «partial» موجود، والعقد الموحد مخطط (POS §8) | الكل | Must | P1a | 💰 |
| `pos.discount.mode` | مستوى الخصم المسموح | خصم على السطر، أو على الفاتورة، أو الاتنين، نسبة أو مبلغ | tenant | enum `line\|invoice\|both` = `both` | [Daftra](https://www.daftra.com/features/sub_feature/8)، [Zoho](https://www.zoho.com/us/inventory/help/settings/preferences.html) | 🟡 الاتنين موجودين ومفيش تحكم | الكل | Should | P2 | 💰 |
| `pos.discount.max_percent` | أقصى خصم من غير موافقة | أي نسبة أعلى منه بتحتاج `pos.discount_over_limit` أو PIN مدير. بيتظبط لكل دور، وينفع override لمستخدم. إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7] | role/user | decimal = cashier 0.000، manager 100.000 | [Qoyod](https://www.qoyod.com/en/knowledge-base/managing-user-permissions-in-qoyod-creating-custom-roles-and/)، [Shopify](https://help.shopify.com/manual/sell-in-person/setup/staff-permissions) | ❌ مفيش أي حد (`StorePOSInvoiceRequest.php:88-98`)، والموجود `invoices.discount` بس | الكل | Must | P1a | 💰 |
| `pos.discount.require_customer` | الخصم يتطلب عميل | ممنوع الخصم على عميل نقدي | tenant | bool = false | [Foodics](https://help.foodics.com/hc/en-us/articles/4406613681554-Cashier-App-Settings) (غير مؤكد) | ❌ | الكل | Could | P3 | 💰 |
| `pos.price.floor_policy` | البيع تحت أقل سعر أو تحت التكلفة | سماح / تحذير / منع وموافقة مدير، لما السعر المعدّل يقل عن `min_selling_price` أو التكلفة. **رسالة التحذير متعرضش رقم التكلفة** لمستخدم معندوش `items.view_cost`، وتقول «أقل من الحد المسموح» بس. إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7] | tenant (+store) | enum `allow\|warn\|block` = `warn` | [Odoo module](https://apps.odoo.com/apps/modules/19.0/restrict_saleprice_change) (غير مؤكد كميزة native) | 🟡 `min_selling_price` موجود ومش بيمنع حاجة. `unit_price` شرطه `min:0` بس | الكل | Must | P1a | 💰 |
| `pos.price_override.enabled` | تعديل السعر في الكاشير | مربوط بصلاحية `pos.price_override` (قرار POS §8) | role | permission، cashier = false | [Lightspeed](https://retail-support.lightspeedhq.com/hc/articles/360036516153) | ❌ | الكل | Must | P1a | 💰 |
| `pos.manager_approval.actions` | العمليات اللي محتاجة PIN مدير | لكل عملية: مسموح / ممنوع / بموافقة. العمليات: خصم فوق الحد، تعديل سعر، تحت التكلفة، مرتجع، مرتجع بدون فاتورة، فتح الدرج، حذف معلّقة، إلغاء، **البيع بالسالب (`sell_negative`)**. في P1a بتتنفذ كـ map ثابت في الكود (كل الحساس = `approval`)، وتبقى قابلة للتعديل من الشاشة في P2. إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7] | tenant (+store) | json map، الافتراضي: كل الحساس = `approval` | [Shopify](https://changelog.shopify.com/posts/set-up-manager-approvals-for-more-control-over-new-pos-permissions) (نموذج «بموافقة» بس، الصفحة مش بتذكر PIN ولا صلاحية تعديل السعر)، [Lightspeed](https://shopkeep-support.lightspeedhq.com/?p=50811) | ❌ (POSB-4) | الكل | Must (P1a map ثابت، P2 قابل للضبط) | P1a | 💰🔒 |
| `pos.void_line.require_reason` | سبب حذف سطر بعد الحفظ | سبب إلزامي لحذف صنف من فاتورة معلّقة أو محفوظة | tenant | bool = true | [Foodics](https://help.foodics.com/hc/en-us/articles/4406613681554-Cashier-App-Settings) (غير مؤكد) | ❌ | الكل | Should | P2 | 💰 |
| `pos.returns.window_days` | مدة السماح بالمرتجع | بعدها المرتجع بيحتاج مدير. القيمة 0 معناها مفيش حد. إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7] | store | int = 14 | [Foodics](https://help.foodics.com/hc/en-us/articles/4406613681554-Cashier-App-Settings)، [Odoo module](https://apps.odoo.com/apps/modules/19.0/eg_pos_product_refund_days_limit) | ❌ | الكل | Should | P2 | 💰 |
| `pos.returns.require_original_invoice` | المرتجع من فاتورة أصلية بس | لو الإعداد مقفول، المرتجع بدون فاتورة بيحتاج `pos.return_without_invoice` | store | bool = true | [Foodics](https://help.foodics.com/hc/en-us/articles/21577983674524-How-to-return-order-in-Foodics-Black-Cashier) | 🟡 `returns.manage` موجودة بس | الكل | Should | P1a | 💰📦 |
| `pos.returns.require_reason` | سبب المرتجع | سبب من قائمة يقدر المالك يعدّلها، وبيظهر في التقارير | tenant | bool = true + list | [Shopify](https://changelog.shopify.com/posts/returns-and-exchanges-in-cart-for-pos) | ❌ | الكل | Should | P2 | — |
| `pos.returns.restock_default` | المرتجع يرجع للمخزون تلقائيًا | ينفع يتغير مع كل مرتجع لو المستخدم معاه صلاحية (للتالف مثلًا) | store | bool = true | [Shopify](https://changelog.shopify.com/posts/returns-and-exchanges-in-cart-for-pos) | 🟡 بيرجع دايمًا (غير مؤكد) | الكل | Should | P2 | 📦 |
| `pos.returns.refund_methods` | طرق رد المرتجع | نقدي / رصيد للعميل / نفس طريقة الدفع. العميل النقدي ليه نقدي بس (قرار §9) | tenant | json = `[cash, customer_credit]` | [Shopify](https://help.shopify.com/en/manual/customers/store-credit) | ❌ | الكل | Should | P1a | 💰 |
| `pos.hold.enabled` | تعليق الفواتير (Hold) | الفواتير المعلّقة بتتحفظ على السيرفر (قرار POS §8 بند 7) | store | bool = true | [Loyverse](https://help.loyverse.com/help/loyverse-back-office) | 🟡 (POSB-5) | الكل | Should | P1a | 📦 |
| `pos.hold.max_per_user` / `pos.hold.expiry_hours` | حد الفواتير المعلّقة ومدتها | بيمنع تراكم فواتير منسية بتحجز مخزون | store | int = 20 / int = 24 | (غير مؤكد، اقتراح) | ❌ | الكل | Could | P2 | 📦 |
| `pos.quick_keys` | الأصناف السريعة لكل فرع | صفحات أزرار وأقسام ظاهرة في الكاشير (قرار POS §8 بند 9) | store (+device) | json layout | [Odoo](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/configuration.html) | ❌ | الكل | Must | P1a | — |
| `pos.visible_categories` | الأقسام الظاهرة في الجهاز | قصر جهاز على أقسام معينة | device | json = all | [Odoo](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/configuration.html) | ❌ | الكل | Could | P2 | — |
| `pos.customer_display.enabled` | شاشة العميل | نافذة تانية في Electron، أو شاشة تانية في Android (قرار POS §8 بند 8) | device | bool = false | (Square/Shopify، غير مؤكد) | ❌ (POSB-7) | الكل | Must (اترقّى: في الإصدار الأول حسب قرار POS §8 بند 8) | P1a | — |
| `pos.scale_barcode.enabled` | باركود الميزان | قراءة ملصقات الميزان اللي فيها وزن أو سعر. الصنف لازم يكون `is_weighted` | store | bool = false | [Loyverse](https://help.loyverse.com/help/barcodes-with-embedded-weight) (الوزن بس: بادئة 20/02، كود 5 أرقام، الوزن بالجرام)، [Daftra](https://docs.daftra.com/en/?p=8567) (الوزن والسعر) | 🟡 `is_weighted` (POSB-2)، ومفيش parser | الكل | Must لو اتختار قالب البيع بالوزن، وإلا Should (§6.2 SETG-11) | P1a (SETG-11 لكل فرع، بيكمّل POSB-2) [CTO-2026-10-09 Q10] | 💰📦 |
| `pos.scale_barcode.format` | صيغة باركود الميزان | البادئة (20–29)، طول الكود، والقيمة وزن ولا سعر، وعدد الأرقام، والقاسم، وcheck digit. السعر المضمّن في الباركود موثّق عند Odoo وDaftra بس | store | json = `{prefix:'20', code_len:5, value:'weight', value_len:5, divisor:1000}` | [Odoo](https://www.odoo.com/documentation/user/13.0/inventory/barcode/operations/barcode_nomenclature.html)، [Daftra](https://docs.daftra.com/en/?p=8567) | ❌ | الكل | Must لو اتختار قالب البيع بالوزن، وإلا Should | P1a | 💰 |
| `pos.cash_rounding.enabled` | تقريب النقدي | تقريب إجمالي الدفع النقدي بس، والكارت والمحفظة بيفضلوا بالمبلغ الدقيق. فرق التقريب بيظهر على الإيصال وبيتسجل في حساب أرباح/خسائر تقريب | tenant | bool = false | [Loyverse](https://help.loyverse.com/help/how-work-cash-rounding)، [Odoo](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/pricing/cash_rounding.html)، [Shopify](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/cash-rounding-on-pos) | ❌ | الكل | Should | P2 | 💰 |
| `pos.cash_rounding.interval` | قيمة التقريب | 0.25 / 0.50 / 1.00. Loyverse بيوثّق 0.01 و0.05 و0.10 و0.50 و1.00 بس، و**0.25 اقتراح مننا** يناسب فئات الجنيه | tenant | decimal-string = `0.500` | نفس المصادر (0.25: اقتراح) | ❌ | الكل | Should | P2 | 💰 |
| `pos.cash_rounding.method` | طريقة التقريب | لأقرب قيمة (النص لفوق) / لأقرب قيمة (النص لتحت) / لفوق دايمًا / لتحت دايمًا | tenant | enum = `nearest_up` | نفس المصادر | ❌ | الكل | Should | P2 | 💰 |
| `pos.cash_in_out.reasons` | أسباب الإيداع والسحب من الدرج | قائمة الأسباب، ومعاها صلاحية `pos.cash_in_out` | tenant | list | [Loyverse](https://help.loyverse.com/help/shift-management-loyverse-pos) | ❌ (POSB-8) | الكل | Should | P1a | 💰 |
| `pos.offline.enabled` | البيع بدون إنترنت | **حاليًا: شريط + منع البيع.** في P2: بيع نقدي/walk-in بس، و queue idempotent (قرار §12) | device | bool = false | [Odoo forum](https://odoo.com/forum/help-1/whats-the-mechanism-of-pos-offline-283217) | ❌ (OFFL-1 شريط بس) | `pos.offline` (Pro+/add-on Basic) | Should | P2 | 💰📦 |
| `pos.keyboard_shortcuts` | اختصارات الكيبورد | المتصفح Ctrl+H وCtrl+Space، وElectron بالـ F-keys (قرار POS §8 بند 5). بعد كده يبقى قابل للتخصيص | device | json = default | (غير مؤكد كإعداد عند المنافسين) | 🟡 ثابتة | الكل | Could | P2 | — |
| `pos.sound.enabled` | أصوات الكاشير | صوت عند المسح وعند الخطأ | device | bool = true | (غير مؤكد) | 🟡 localStorage `pos_sound_enabled` | الكل | Could | P2 | — |
| `shift.required_to_sell` | إلزام وردية مفتوحة قبل البيع | الكاشير ميقدرش يبيع من غير وردية مفتوحة على الجهاز أو المستخدم | store | bool = `false` في الكود. **[CTO-2026-10-09] Q1:** القيمة من القالب: `true` للتجزئة والوزن، و`false` للجملة والسيارات، والمالك يغيّرها. لو الإعداد مقفول، نافذة إلغاء الكاشير بتتحسب `same_day`. المحل الحالي بعد الترحيل: مفتوح | [Loyverse](https://help.loyverse.com/help/shift-management-loyverse-pos)، [Square](https://square.com/help/us/en/article/5152) | ❌ `InvoiceService` مش بيرجع لـ `CashShift` | الكل | Must | P1a (SETG-9) | 💰 |
| `shift.default_opening_float` | عهدة الافتتاح الافتراضية | مبلغ بيتملي مسبقًا عند فتح الوردية | store | decimal = `0.000` | [Square](https://squareup.com/help/ca/article/6346) | ❌ | الكل | Could | P2 | 💰 |
| `shift.blind_close` | الإقفال الأعمى | الكاشير ميشوفش المبلغ المتوقع وهو بيعد. ده نفس صلاحية «رؤية المتوقع» | role | bool = true للكاشير | [Loyverse](https://help.loyverse.com/help/how-manage-access-rights-employees) | ❌ | الكل | Should | P1a | 💰 |
| `shift.require_count_on_close` | إلزام عدّ النقدية | العدّ إلزامي، ونقدر نعده بالفئات (200/100/50…) | store | bool = true؛ denominations = EGP | [Odoo](https://www.odoo.com/documentation/14.0/applications/sales/point_of_sale/shop/cash_control.html) | 🟡 (غير مؤكد) | الكل | Should | P1a | 💰 |
| `shift.variance_tolerance` | حد السماح لفرق الخزينة | أي فرق أكبر منه بيحتاج سبب وPIN مدير، وبيبعت تنبيه. الفرق بيتسجل في حساب عجز/زيادة. إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7] | store | decimal = `0.000` | [Odoo (Authorized Difference)](https://hibou.io/docs/point-of-sale-pos-67/pos-advanced-cash-control-1335) | ❌ | الكل | Should | P2 | 💰 |

### 3.2 الفواتير والإيصالات والطباعة

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `receipt.header_lines` | ترويسة الإيصال | سطور نص عادي (من غير HTML) تحت الاسم: عنوان، تليفونات، سجل تجاري | tenant (+store) | text[] ≤ 6 | [Loyverse](https://help.loyverse.com/help/how-add-text-receipts)، [Odoo](https://odoo.com/documentation/18.0/applications/sales/point_of_sale/receipts_invoices.html) | ❌ (BRND-5) | الكل | Must | P1a | 🔒 (XSS) |
| `receipt.footer_text` | تذييل الإيصال | رسالة شكر وسياسة الاستبدال | tenant (+store) | text = '' | [Loyverse](https://help.loyverse.com/help/how-add-text-receipts)، [Shopify](https://help.shopify.com/manual/sell-in-person/setup/receipts/customize-receipts/) | 🟡 `invoice_footer_note` بيتحفظ ومش بيتعرض (`SettingsPrintingSection.vue:96`) | الكل | Must | P1a | 🔒 |
| `receipt.show_logo` | إظهار الشعار | الشعار نفسه في `company.logo` (§3.8) | tenant (+store) | bool = true | [Rewaa](https://intercom.help/rewaa/en/articles/8633629-sales-invoice-settings) (غير مؤكد) | 🟡 `show_print_logo` dead، والواجهة في `BrandingTab.vue` يتيم | الكل | Must | P1a | — |
| `receipt.show_company_name` / `receipt.show_subtitle` | إظهار الاسم والوصف | — | tenant (+store) | bool = true | [Zoho templates](https://www.zoho.com/invoice/help/settings/templates.html) | 🟡 بيتحفظوا ومش مستخدمين | الكل | Must | P1a | — |
| `receipt.show.cashier_name` | اسم الكاشير | — | store | bool = true | [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/) | 🟡 (غير مؤكد) | الكل | Should | P1a | — |
| `receipt.show.customer_info` | بيانات العميل | الاسم والتليفون والعنوان، لو فيه عميل مسجّل | store | json = `{name:true, phone:true, address:false}` | [Loyverse](https://help.loyverse.com/help/customer-info-receipt) | 🟡 | الكل | Should | P1a | 🔒 (PII) |
| `receipt.show.customer_balance` | رصيد العميل على الإيصال | مديونية العميل بعد الفاتورة | store | bool = false | [Lightspeed](https://x-series-support.lightspeedhq.com/hc/articles/360000371516) (غير مؤكد) | 🟡 `thermal_show_customer_balance` مش مستخدم | الكل | Should | P1a | 🔒 |
| `receipt.show.payment_breakdown` | تفاصيل الدفع | كل طريقة دفع والمدفوع والباقي (عقد الدفع المقسم option A) | store | bool = true | [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/) | 🟡 | الكل | Must | P1a | 💰 |
| `receipt.show.discounts` | الخصم والسعر قبل الخصم | — | store | bool = true | [Shopify](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/receipt-management/customize-receipts/receipt-editors/receipt-visual-editor) | 🟡 | الكل | Should | P1a | — |
| `receipt.show.item_sku` | كود أو باركود الصنف | — | store | bool = false | [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/) | ❌ | الكل | Could | P2 | — |
| `receipt.show.notes` | ملاحظات الفاتورة والأصناف | — | store | bool = true | [Loyverse](https://help.loyverse.com/help/customer-info-receipt) | 🟡 | الكل | Could | P2 | — |
| `receipt.reprint_marker` | علامة «نسخة» عند إعادة الطباعة | رقم النسخة ووقت الطباعة، عشان النسخة المعادة متتقدمش على إنها الأصل | tenant | bool = true | [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/) | ❌ | الكل | Should | P1a | 💰 (احتيال) |
| `receipt.qr.mode` | رمز QR | لا شيء / رابط الفاتورة / ETA (Phase 3) | store | enum = `none` | [Odoo](https://odoo.com/documentation/18.0/applications/sales/point_of_sale/receipts_invoices.html)، [Rewaa](https://intercom.help/rewaa/en/articles/8633629-sales-invoice-settings) | 🟡 `print_show_qr` موجود، وQR مش بيترسم أصلًا | الكل | Should | P2 | 🔒 (الرابط العام لازم signed) |
| `receipt.barcode.enabled` | باركود رقم الفاتورة | عشان المرتجع وإعادة الطباعة بالمسح | store | bool = true | [Shopify](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/receipt-management/customize-receipts/receipt-editors/receipt-visual-editor)، [Lightspeed](https://x-series-support.lightspeedhq.com/hc/articles/360000371516) | ❌ | الكل | Should | P2 | — |
| `receipt.return_footer_text` | تذييل إيصال المرتجع | سياسة المرتجع | store | text = '' | [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/)، [Square](https://square.com/help/us/en/article/5060) | ❌ | الكل | Should | P2 | — |
| `receipt.language` | لغة الإيصال | عربي / إنجليزي / الاتنين | store | enum = `ar` | [Loyverse](https://help.loyverse.com/help/how-change-language) | ❌ | الكل | Could | P3 (الإنجليزي مؤجَّل، قرار §10) | — |
| `printing.paper_width` | مقاس الورق | 58 مم / 80 مم / A4، لكل نوع مستند | device | enum: POS = `80mm`، الفاتورة = `a4` | [Rewaa](https://intercom.help/rewaa/en/articles/8633629-sales-invoice-settings) (58/80 غير مؤكد) | 🟡 localStorage `desktop_paper_width` في Electron بس | الكل | Must | P1a | — |
| `printing.default_printer` | الطابعة الافتراضية | — | device | string | — | 🟡 localStorage `desktop_thermal_printer` | الكل | Should | P1a | — |
| `printing.auto_print` | الطباعة بعد الدفع | تلقائي / اسأل / لا. ونقدر نتخطى شاشة اختيار الإيصال | device | enum = `auto` | [Odoo](https://odoo.com/documentation/18.0/applications/sales/point_of_sale/receipts_invoices.html)، [Square](https://squareup.com/help/us/en/article/8669-manage-receipt-settings-on-square-point-of-sale) | 🟡 (غير مؤكد) | الكل | Should | P1a | — |
| `printing.copies` | عدد النسخ | لكل نوع مستند | device | int 1–3 = 1 | [Square](https://squareup.com/help/us/en/article/8669-manage-receipt-settings-on-square-point-of-sale) (N نسخ غير مؤكد) | ❌ | الكل | Should | P2 | — |
| `pos.drawer.auto_open` | فتح الدرج بعد البيع النقدي | — | device | bool = true | [Daftra](https://docs.daftra.com/en/?p=3989) (غير مؤكد) | 🟡 (fallback في APP-5) | الكل | Should | P1a | 💰 |
| `invoice.a4.accent_color` | لون قالب A4 | — | tenant | enum/hex = theme | — | 🟡 `invoice_primary_color` dead | الكل | Could | P2 | — |
| `invoice.terms_text` | الشروط والأحكام على الفاتورة | ضمان واستبدال | tenant | text = '' | [Daftra](https://www.daftra.com/blog/?p=1935)، [Zoho](https://zstatic.zohostatic.com/ke/invoice/help/estimate/estimate-preferences.md) | ❌ | الكل | Should | P2 | — |
| `invoice.stamp_image` | صورة ختم أو توقيع | بتظهر على A4 | tenant | file | [Rewaa](https://intercom.help/rewaa/en/articles/8633629-sales-invoice-settings) (غير مؤكد) | ❌ | الكل | Could | P3 | — |
| `invoice.watermark_text` | علامة مائية | «نسخة» أو «غير مدفوعة» | tenant | string | [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/) | ❌ | الكل | Could | P3 | — |
| `invoice.table.columns` | أعمدة جدول الأصناف | إظهار/إخفاء وإعادة تسمية | tenant | json | [Zoho](https://www.zoho.com/invoice/help/settings/templates.html) | ❌ | الكل | Could | P3 | — |
| `invoice.internal_copy_with_cost` | نسخة داخلية فيها التكلفة | مربوطة بـ `items.view_cost` | tenant | bool = false | (غير مؤكد، اقتراح) | ❌ | الكل | Could | P3 | 🔒 |
| `invoicing.numbering.series` | ترقيم المستندات | بادئة ولاحقة وعدد الأرقام لكل نوع مستند ولكل فرع. التغيير بيسري على الجديد بس | store | json = `INV-{STORE}-` + تسلسل | [Zoho](https://www.zoho.com/inventory/help/settings/transaction-number-series.html)، [Qoyod](https://www.qoyod.com/knowledge-base/%d8%a7%d9%85%d9%83%d8%a7%d9%86%d9%8a%d8%a9-%d8%aa%d8%b9%d8%af%d9%8a%d9%84-%d8%aa%d8%b3%d9%84%d8%b3%d9%84-%d8%a7%d9%84%d8%b1%d9%82%d9%85-%d8%a7%d9%84%d9%85%d8%b1/) | ❌ hardcoded: `InvoiceService.php:887`، `CreateExpenseAction.php:22`، `PaymentService.php:52` (uniqid) | الكل | Should | P2 | 💰 (لازم unique + lock) |
| `invoicing.numbering.start_number` | رقم البداية | مهم لترحيل المحل الحالي | store | int = 1 | [Zoho](https://www.zoho.com/inventory/help/settings/transaction-number-series.html) | ❌ | الكل | Should | P2 | 💰 |
| `invoicing.numbering.reset_period` | تصفير الترقيم سنويًا | — | tenant | enum `none\|yearly` = `none` | [Zoho](https://www.zoho.com/inventory/help/settings/transaction-number-series.html) | ❌ | الكل | Could | P3 | 💰 |
| `invoices.cancel.cashier_window` | نافذة إلغاء الفاتورة للكاشير | الكاشير يلغي جوه نفس الوردية بس، وبعدها المدير. السبب إلزامي وفيه audit، وممنوع لو الفاتورة عليها مرتجع (قرار §9). `same_shift` محتاج وردية: لو `shift.required_to_sell=false` والفاتورة مالهاش وردية، النافذة بتتحسب `same_day` تلقائيًا. إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7] | tenant | enum `same_shift\|same_day\|none` = `same_shift` | (قرار CTO) | 🟡 `invoices.cancel` موجودة، والنافذة ❌ | الكل | Must | P1a (SETG-9؛ الـ fallback لـ `same_day` اتأكد في Q1 [CTO-2026-10-09]) | 💰📦 |
| `sharing.whatsapp.enabled` | مشاركة الفاتورة بواتساب | رابط من شاشة الفاتورة. **[CTO-2026-10-09] Q12:** المشاركة اليدوية بالرابط مجانية في كل الباقات | tenant | bool = true | [Daftra](https://docs.daftra.com/en/?p=3907) (غير مؤكد) | 🟡 موجودة، وكود الدولة `20` hardcoded (`GetInvoiceDetailsAction.php:29-30`) | الكل | Should | P1a | 🔒 (PII في الرابط) |
| `sharing.message_templates` | قوالب الرسائل | متغيرات زي `{customer_name}` و`{invoice_no}` و`{total}` و`{link}` | tenant | json لكل قناة | [Shopify](https://help.shopify.com/manual/sell-in-person/setup/receipts/customize-receipts/) | ❌ | الكل | Should | P2 | — |

> عروض الأسعار (`quotations.default_validity_days`، `quotations.default_terms`، `quotations.auto_convert`): **مفيش موديول عروض أسعار في الكود** (مفيش Model)، فاتسجلت كـ **Could / P3** برّه العدد.

### 3.3 المخزون

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `inventory.units` | الوحدات المسموحة | قائمة وحدات المستأجر، وجاية من كتالوج المنصة | tenant | list (من `global_system_units`) | [Zoho POS](https://www.zoho.com/en-sa/pos/resources/help/item-preferences.html) | 🟡 `inventory_units` بيتحفظ، و`ItemsView.vue:97` بيستخدم قائمة hardcoded، وفيه 6 نسخ تانية | الكل | Must | P1a (SETG-10) | — |
| `inventory.negative_stock.policy` | البيع بالسالب | منع / تحذير / سماح. في `warn` الكاشير مبيكملش لوحده: بيحتاج صلاحية `pos.sell_negative_stock` أو موافقة مدير حسب `sell_negative` في `pos.manager_approval.actions`. **استثناء (قرار CTO §12):** المزامنة بعد الـ offline دايمًا بتقبل السالب وتبعت تنبيه بدل الرفض، مهما كانت السياسة. إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7]. **[CTO-2026-10-09] Q2:** الثلاث حالات متاحة في Phase 1 لكل المستأجرين، وإعداد رقابة للمالك حتى على مستوى الفرع. في `warn` البيع بيحتاج PIN مدير أو صلاحية `pos.sell_negative_stock`، وكل بيع بالسالب بيتسجل ويظهر في تقرير. المزامنة بعد الـ offline (Phase 2) دايمًا بتقبل السالب وتبعت تنبيه | tenant | enum `block\|warn\|allow` = `block` (السلوك الحالي) | [Loyverse](https://help.loyverse.com/help/negative-stock-alerts)، [Zoho POS](https://www.zoho.com/en-sa/pos/resources/help/item-preferences.html) (الثلاث حالات `block\|warn\|allow` صياغتنا مبنية عليهم، اقتراح)، [Daftra](https://docs.daftra.com/en/?p=16678) (مفتاح تشغيل/إيقاف بس) | ❌ دايمًا منع (`StockService.php:47-48,64-67,280`) | الكل | Should | **P1a** (SETG-9) [CTO-2026-10-09] | 📦💰 |
| `inventory.negative_stock.store_override` | السالب لكل فرع | `inherit\|block\|warn\|allow`. مثلًا الفرع يسمح والمخزن الرئيسي يمنع. **[CTO-2026-10-09]** حتى على الفرع، الكتابة للمالك أو `settings.controls.manage` بس (Q2) | store | enum = `inherit` | [Foodics](https://changelog.foodics.com/negative-stock-control-45TE5i) (override لكل فرع غير مؤكد، **ميزة تميّزنا**) | ❌ | الكل | Should | P2 | 📦 |
| `inventory.negative_stock.internal_ops` | منع السالب في العمليات الداخلية | التحويل والهالك والمرتجع للمورد. ثابت على منع. **مش بيتطبق على مزامنة الـ offline** (قرار CTO §12): الخصم عند المزامنة بيعدّي ويبعت تنبيه | tenant | bool = true (مقفول) | [Foodics](https://changelog.foodics.com/negative-stock-control-45TE5i) | ✅ ضمنيًا | الكل | Should | P2 | 📦 |
| `inventory.quantity_decimals` | خانات الكمية العشرية | للعرض والإدخال (0–3)، والتخزين بيفضل `DECIMAL(12,3)` | tenant | int = 3 | [Zoho POS](https://www.zoho.com/en-sa/pos/resources/help/item-preferences.html) | 🟡 (غير مؤكد في العرض) | الكل | Should | P1a | 📦 |
| `inventory.weight_units` | وحدات الوزن المسموحة | جم / كجم | tenant | list = `[kg, g]` | [Square](https://squareup.com/help/us/en/article/6694) | 🟡 `is_weighted` (POSB-2) | الكل | Should | P1a | 📦 |
| `inventory.low_stock.default_threshold` | حد النقص الافتراضي | بيتطبق لو الصنف مالوش حد خاص. وفيه override لكل فرع | tenant (+store) | decimal = `5.000` | [Rewaa](https://intercom.help/rewaa/en/articles/9979040-platform-notifications) (غير مؤكد)، [Square](https://squareup.com/help/article/8333-create-inventory-alerts) | 🟡 `items.min_stock_level` = 5، و`store_stocks.min_stock` مش مستخدم | الكل | Should | P1a | 📦 |
| `inventory.track_stock_default` | تتبع المخزون للأصناف الجديدة | الخدمات مش بتتتبع | tenant | bool = true | [Loyverse](https://help.loyverse.com/help/low-stocks) | ❌ (غير مؤكد) | الكل | Should | P2 | 📦 |
| `inventory.multi_unit.enabled` | وحدات متعددة وتحويلها | كرتونة = 12 قطعة، وفيه وحدة شراء ووحدة بيع | tenant | bool = false | [Odoo](https://www.odoo.com/documentation/user/12.0/inventory/settings/products/uom.html) | ❌ مفيش جدول تحويل | الكل | Should | P2 | 📦💰 |
| `inventory.store_price_override` | سعر بيع مختلف لكل فرع | — | tenant | bool = true | [Odoo](https://www.odoo.com/documentation/17.0/applications/sales/point_of_sale/pricing/pricelists.html) | ✅ `store_stocks.custom_selling_price` | الكل | Should | P1a | 💰 |
| `inventory.costing_method` | طريقة التكلفة | متوسط مرجّح، ونعرضه read-only. التغيير ممنوع بعد أي حركة | tenant | enum = `weighted_average` (مقفول) | [Zoho](https://www.zoho.com/inventory/kb/items/item-inventory-evaluation.html) | ✅ WAC | الكل | Could | P3 | 💰 |
| `inventory.cost_scope` | نطاق متوسط التكلفة | موحّد للشركة / لكل فرع | tenant | enum = (يحتاج تأكيد من الكود) | [Foodics](https://help.foodics.com/hc/en-us/articles/17585701041692-Cost-Guide-in-Foodics-Console) | ❓ (غير مؤكد) | الكل | Could | P3 | 💰 |
| `inventory.landed_cost.enabled` | تحميل مصاريف الشراء على التكلفة | — | tenant | bool = false | [Zoho POS](https://www.zoho.com/en-sa/pos/resources/help/item-preferences.html) | ❌ | الكل | Could | P3 | 💰 |
| `inventory.reorder.mode` | قواعد إعادة الطلب | مقفول / اقتراح / مسودة أمر شراء | store | enum = `suggest` | [Odoo](https://www.odoo.com/documentation/12.0/nl/applications/inventory_and_mrp/purchase/replenishment/flows/setup_stock_rule.html) | ❌ | `purchases.reorder` | Should | P2 | 📦 |
| `inventory.reorder.include_incoming` | احتساب الوارد | — | tenant | bool = true | [Odoo](https://www.odoo.com/documentation/12.0/nl/applications/inventory_and_mrp/purchase/replenishment/flows/setup_stock_rule.html) | ❌ | `purchases.reorder` | Could | P3 | 📦 |
| `inventory.count.requires_approval` | اعتماد الجرد قبل الترحيل | — | store | bool = true | [Foodics](https://help.foodics.com/hc/en-us/articles/4408256285458-Submitting-Inventory-Counts) (الاعتماد غير مؤكد) | ❌ | الكل | Should | P2 | 📦💰 |
| `inventory.count.blind` | الجرد الأعمى | إخفاء الكمية المتوقعة | store | bool = false | (غير مؤكد) | ❌ | الكل | Could | P2 | 📦 |
| `inventory.count.freeze_sales` | إيقاف البيع أثناء الجرد | — | store | enum = `allow_and_reconcile` | (غير مؤكد) | ❌ | الكل | Could | P3 | 📦 |
| `inventory.adjustment.reasons` | أسباب التسوية | تالف / منتهي / فقد / عيّنة، والسبب إلزامي | tenant | list + bool = true | [Shopify Stocky](https://help.shopify.com/en/manual/products/inventory/stocky/inventory-management/stock-adjustments) | ❌ | الكل | Should | P2 | 📦💰 |
| `inventory.adjustment.approval_threshold` | اعتماد التسوية فوق قيمة | — | store | decimal nullable | [Zoho](https://www.zoho.com/inventory/help/warehouses/transfer-orders.html) (غير مؤكد للتسويات) | ❌ | الكل | Could | P2 | 💰 |
| `inventory.transfer.requires_approval` | اعتماد التحويل قبل الإرسال | — | tenant | bool = false | [Zoho](https://www.zoho.com/inventory/help/warehouses/transfer-orders.html) | ❌ | `transfers.manage` | Should | P2 | 📦 |
| `inventory.transfer.receive_mode` | استلام التحويل | تلقائي / الفرع يأكد الاستلام (in-transit) | tenant | enum = `confirm_receipt` | [Foodics](https://foodics.helpjuice.com/558902-send-and-receive-items-using-transfer-transaction) | 🟡 (غير مؤكد) | `transfers.manage` | Should | P2 | 📦 |
| `inventory.barcode.auto_generate` | باركود داخلي تلقائي | بادئة 2x عشان ميتعارضش مع GS1 | tenant | json = `{enabled:true, symbology:'EAN13', prefix:'2'}` | [Odoo](https://www.odoo.com/documentation/user/13.0/inventory/barcode/operations/barcode_nomenclature.html) | ❌ (غير مؤكد) | الكل | Should | P2 | — |
| `inventory.label_templates` | قوالب الملصقات | المقاس والمحتوى | tenant | json | [Lightspeed](https://retail-support.lightspeedhq.com/hc/articles/228842627) (غير مؤكد)، [Loyverse](https://loyverse.com/advanced-inventory) | ❌ | الكل | Should | P2 | — |
| `inventory.composite_items.enabled` | الأصناف المركبة والخلطات | — | tenant | bool = false | [Zoho POS](https://www.zoho.com/en-sa/pos/resources/help/item-preferences.html) | 🟡 Mix Builder | `mixes.manage` | Could | P2 | 📦💰 |
| `inventory.mix.waste_percent_default` | نسبة الهالك الافتراضية للخلطة | بديل «فاقد التحميص» (قرار §14) | tenant | decimal = `0.000` | — | 🟡 (wording قهوة) | `mixes.manage` | Could | P2 | 💰 |
| `inventory.duplicate_item_names` | السماح بتكرار الاسم | — | tenant | bool = false | [Zoho POS](https://www.zoho.com/en-sa/pos/resources/help/item-preferences.html) | ❌ | الكل | Could | P3 | — |
| `inventory.batch_tracking` | التشغيلات (Lot) | ميتقفلش تاني بعد أول حركة | tenant | bool = false | [Daftra](https://docs.daftra.com/en/?p=4503) | ❌ | الكل | Could | P3 | 📦 |
| `inventory.expiry.enabled` + `alert_days` + `sell_expired_policy` | الصلاحية | تتبع التاريخ، والتنبيه قبله بـ 30 يوم، وسياسة بيع المنتهي (سماح/تحذير/منع) | tenant | bool = false / int = 30 / enum = `warn` | [Daftra](https://docs.daftra.com/en/?p=8437)، [Odoo](https://open-exam-prep.com/study-guides/odoo-19/inventory-warehouse/traceability-valuation) | ❌ | الكل | Could | P3 | 📦 |
| `inventory.removal_strategy` | استراتيجية الصرف | FIFO / FEFO | tenant | enum = `fifo` | [Odoo](https://open-exam-prep.com/study-guides/odoo-19/inventory-warehouse/traceability-valuation) | ❌ | الكل | Could | P3 | 📦 |
| `inventory.serial_tracking` | الأرقام التسلسلية | للموبايلات والإلكترونيات | tenant | bool = false | [Daftra](https://docs.daftra.com/en/?p=4506)، [Zoho POS](https://www.zoho.com/en-sa/pos/resources/help/item-preferences.html) | ❌ | الكل | Could | P3 | 📦 |
| `inventory.storage_locations` | مواقع داخل المخزن (رف/خانة) | — | tenant | bool = false | [Odoo](https://odoo-users.readthedocs.io/en/stable/inventory.html) | ❌ | Enterprise؟ | Could | P3 | — |

### 3.4 العملاء والموردين

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `customers.credit.limit_mode` | تطبيق حد الائتمان | مقفول / تحذير / منع. تخطي المنع بيحتاج صلاحية `customers.credit_limit.manage` أو PIN مدير (Zoho بيقصر التخطي على صلاحية مخصوصة). إعداد رقابة (§2.3): الكتابة بصلاحية `settings.controls.manage` بس [CTO-2026-10-09 Q7]. **[CTO-2026-10-09] Q3:** في Phase 1a داخل POSB-1: عمود `customers.credit_limit` + الوضع على مستوى المستأجر. تجاوز الحد في `block` بيحتاج PIN مدير معاه الصلاحية (اسم الصلاحية يتوحّد مع POSB-4 بند (ج) في خطة Phase 1). شاشة الدفع في الـ POS بتعرض الرصيد والحد والرصيد بعد البيعة. **مستني تأكيد:** الحد الفاضي = بلا حد، وdefault المستأجر الجديد = `warn` | tenant | enum `none\|warn\|block` = `warn` (مستني تأكيد) | [Zoho](https://www.zoho.com/inventory/help/contacts/customer-credit-limit.md)، [Daftra](https://docs.daftra.com/en/?p=7865) | 🟡 `CustomerResource.php:21` بيرجّع `credit_limit`، و**مفيش عمود في الـ migrations** | الكل | Must | P1a (POSB-1) | 💰 |
| `customers.credit.default_limit` | الحد الافتراضي للعميل الجديد | 0 معناها مفيش آجل لحد ما يتحدد | tenant | decimal = `0.000` | [Lightspeed](https://x-series-support.lightspeedhq.com/hc/en-us/articles/5455734924175) | ❌ | الكل | Should | P1a | 💰 |
| `customers.credit.warning_percent` | التحذير قبل بلوغ الحد | — | tenant | decimal nullable | [Odoo module](https://apps.odoo.com/apps/modules/17.0/pos_credit_limit) | ❌ | الكل | Could | P2 | 💰 |
| `customers.credit.include_open_orders` | احتساب الفواتير المعلّقة ضمن الحد | قيمة الفواتير المعلّقة (Hold) للعميل بتتحسب مع رصيده قبل مقارنته بالحد. عند Zoho الخيار ده خاص بأوامر البيع (sales orders)، وإحنا بنطبقه على المعلّقة (اقتراح) | tenant | tenant | bool = false | [Zoho](https://www.zoho.com/inventory/help/contacts/customer-credit-limit.md) | ❌ | الكل | Could | P3 | 💰 |
| `customers.credit.period_days` | مدة السماح بالأيام | بيمنع أو يحذّر لو أقدم فاتورة عدّت المدة | tenant (+customer) | int nullable | [Daftra](https://docs.daftra.com/en/?p=7865) | ❌ | الكل | Should | P2 | 💰 |
| `customers.payment_terms.default` | شروط الدفع للعملاء | فوري / 7 / 15 / 30 / آخر الشهر | tenant (+customer) | enum = `due_on_receipt` | [Zoho](https://www.zoho.com/inventory/help/contacts/contact-details-page.html) | ❌ | الكل | Should | P2 | 💰 |
| `suppliers.payment_terms.default` | شروط الدفع للموردين | — | tenant (+supplier) | enum = `net_30` | [Zoho](https://www.zoho.com/inventory/help/contacts/contact-details-page.html) | ❌ | الكل | Should | P2 | 💰 |
| `customers.opening_balance.enabled` | الأرصدة الافتتاحية | مدين أو دائن صريح، والتعديل بعد الترحيل بصلاحية | tenant | bool = true | [Daftra](https://docs.daftra.com/en/tutorial/supplier-opening-balance/)، [Qoyod](https://www.qoyod.com/en/knowledge-base/how-to-record-opening-balances-in-qoyod-accounts-customers-s/) | ✅ في `CreateCustomerAction`/`CreateSupplierAction` (قيود التعديل غير مؤكدة) | الكل | Should | P1a | 💰 |
| `customers.store_credit.enabled` | الرصيد الدائن للعميل | استرداد المرتجع أو الإلغاء كرصيد (قرار §9) | tenant | bool = true؛ expiry_days nullable | [Shopify](https://help.shopify.com/en/manual/customers/store-credit) | 🟡 رصيد العميل موجود | الكل | Should | P1a | 💰 |
| `customers.payment_allocation` | توزيع التحصيل | الأقدم أولًا / يدوي | tenant | enum = `oldest_first` | [Foodics](https://foodics.helpjuice.com/96010-manage-customers/558936-customers-overview) (غير مؤكد) | 🟡 | الكل | Should | P2 | 💰 |
| `customers.required_fields` | الحقول الإلزامية | — | tenant | list = `[name, phone]` | [Daftra](https://docs.daftra.com/en/?p=17048) (غير مؤكد للإلزام) | 🟡 ثابتة في الـ Request | الكل | Should | P2 | — |
| `customers.visible_fields` | الحقول الاختيارية الظاهرة | — | tenant | json | [Daftra](https://docs.daftra.com/en/?p=17048) | ❌ | الكل | Could | P3 | — |
| `customers.type_mode` | أفراد / شركات / الاتنين | الشركات بيظهر لها الرقم الضريبي والسجل التجاري | tenant | enum = `both` | [Daftra](https://docs.daftra.com/en/?p=17048) | ❌ | الكل | Could | P3 | — |
| `customers.custom_fields` | حقول مخصصة | — | tenant | json schema | [Daftra](https://docs.daftra.com/en/?p=17048) | ❌ | الكل | Could | P3 | — |
| `customers.code_numbering` | ترقيم أكواد العملاء والموردين | — | tenant | `C-`، `S-` | [Daftra](https://docs.daftra.com/en/?p=17048) (غير مؤكد) | ❌ | الكل | Could | P3 | — |
| `customers.groups.enabled` | مجموعات العملاء | — | tenant | bool = false | [Lightspeed](https://x-series-support.lightspeedhq.com/hc/articles/201379170) | ❌ | الكل | Should | P2 | — |
| `pricing.price_lists.enabled` | قوائم الأسعار (جملة/قطاعي/خاص) | — | tenant | bool = false | [Odoo](https://www.odoo.com/documentation/17.0/applications/sales/point_of_sale/pricing/pricelists.html)، [Zoho POS](https://www.zoho.com/en-in/pos/resources/help/pricelist.html) | 🟡 `min_selling_price` مستخدم كسعر جملة (`GetPOSBootstrapDataAction.php:89-90`) | الكل | Should | P2 | 💰 |
| `pricing.price_lists.default_per_store` | القائمة الافتراضية لكل فرع | — | store | fk nullable | [Odoo](https://www.odoo.com/documentation/17.0/applications/sales/point_of_sale/pricing/pricelists.html) | ❌ | الكل | Should | P2 | 💰 |
| `pricing.price_lists.auto_apply_customer` | تطبيق قائمة العميل تلقائيًا | — | tenant | bool = true | [Odoo](https://www.odoo.com/documentation/17.0/applications/sales/point_of_sale/pricing/pricelists.html) | ❌ | الكل | Should | P2 | 💰 |
| `pricing.price_lists.cashier_can_switch` | الكاشير يغيّر القائمة | مربوط بصلاحية | store | bool = false | [Odoo](https://www.odoo.com/documentation/17.0/applications/sales/point_of_sale/pricing/pricelists.html) | ❌ | الكل | Should | P2 | 💰 |
| `loyalty.*` | برنامج الولاء | التفعيل، ومعدل الاكتساب، وقيمة النقطة، والحد الأدنى للفاتورة، وتأخير الاحتساب، والصلاحية، وقواعد الاستبدال، والطباعة على الإيصال (8 مفاتيح) | tenant | bool = false … | [Loyverse](https://help.loyverse.com/help/how-customer-loyalty-program)، [Foodics](https://help.foodics.com/hc/en-us/articles/4406613610130-Loyalty-Settings)، [Square](https://squareup.com/help/us/en/article/5744) | ❌ | add-on مقترح | Could | P3 | 💰 |
| `customers.statement.default_period` | الفترة الافتراضية لكشف الحساب | — | tenant | enum = `current_month` | [Zoho](https://www.zoho.com/inventory/help/contacts/contact-details-page.html) | 🟡 `customers.statement` موجودة | الكل | Could | P2 | — |

> `customers.tax_exempt` اتنقل للمالية (§3.5). وتذكير المديونيات اتنقل للإشعارات (§3.7).

### 3.5 المالية والضريبة وطرق الدفع

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `finance.currency` | العملة الأساسية | ISO-4217. **تتقفل بعد أول فاتورة** | tenant | string = `EGP` | [Loyverse](https://help.loyverse.com/help/how-set-currency)، [Zoho](https://www.zoho.com/inventory/help/settings/organization-settings.html) | ❌ `ج.م` hardcoded في `lang/*/common.php:5` و8 ملفات backend | الكل | Must | P1a (SETG-1) | 💰 |
| `finance.money_decimals` | خانات عرض المبالغ | التخزين بيفضل 3 خانات | tenant | int 0–3 = 2 | [Qoyod](https://www.qoyod.com/en/knowledge-base/decimal-display-format-and-decimal-place-editing-limits/) | 🟡 ثابتة على 2 (`useFormatters.js:9-18`) | الكل | Should | P1a | 💰 |
| `finance.calc_rounding` | قاعدة تقريب الحساب | half-up في المجاميع، بدل الـ truncation الحالي. **قاعدة منصة مقفولة، مش اختيار للمالك**. **[CTO-2026-10-09] Q4 — اتعتمد (SETG-13):** half-up موحّد، الحساب الداخلي بـ scale 3، والعرض بعدد خانات عملة المستأجر، ونفس القاعدة بالظبط في PHP وJS. الضريبة بتتحسب مرة واحدة على صافي الفاتورة. بيسري على **الفواتير الجديدة بس**. تقريب النقدي (لـ 0.5 أو 1) إعداد منفصل في P2 (`pos.cash_rounding.*`) | platform | enum = `half_up` (مقفول) | [Qoyod](https://www.qoyod.com/en/knowledge-base/decimal-display-format-and-decimal-place-editing-limits/) | 🟡 truncation في `helpers/decimal.js:12` | الكل | Must | P1a | 💰 |
| `finance.foreign_currencies` | العملات الأجنبية | — | tenant | list = [] | [Qoyod](https://www.qoyod.com/en/knowledge-base/multi-currency-support-for-foreign-currencies/) | ❌ | Enterprise؟ | Could | P3 | 💰 |
| `payments.methods` | طرق الدفع | نقدي (ثابت) + كارت + فودافون كاش + إنستاباي + فوري + تحويل + آجل. لكل طريقة: اسم، ونوع، وتفعيل، وترتيب. **[CTO-2026-10-09] Q10:** قائمة واحدة في SETG-8 (1a) | tenant | list = `[cash, card, credit]` | [Loyverse](https://help.loyverse.com/help/configuring-payment-types-loyverse)، [Odoo](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/payment_methods.html)، [Shopify](https://help.shopify.com/en/manual/sell-in-person/getting-started/setup-payment-method/enable-payments) | ❌ 8 أماكن (7 Form Requests بقوائم `in:` حرفية + ثابت واحد)، بـ 3 مجموعات قيم: **(A)** `cash,instapay,wallet,bank,visa,e_wallet,bank_transfer` في `CollectCustomerPaymentRequest.php:20`، `PaySupplierRequest.php:20`، `StoreDailyJournalExpenseRequest.php:22`. **(B)** `cash,instapay,e_wallet,visa,bank_transfer,check,other` في `StoreCustomerPaymentReceiptRequest.php:25`، `StoreSupplierPaymentVoucherRequest.php:25`، وهي نفس الثابت `CHECKOUT_PAYMENT_METHODS` في `Requests/Concerns/ValidatesCheckoutPayments.php:15` (اللي بيستخدمه `StorePOSInvoiceRequest.php:87` و`StoreSalesInvoiceRequest.php:56`، مش قائمة حرفية). **(C)** `cash,instapay,e_wallet,visa,bank_transfer,check` في `StoreExpenseRequest.php:24`، `UpdateExpenseRequest.php:24`. ومفيش قيمة صريحة لفودافون كاش، بس `e_wallet` (و`wallet` في A) | الكل | Must | P1a | 💰 |
| `payments.method.requires_reference` | رقم مرجعي إلزامي | لتحويلات المحافظ وإنستاباي | tenant | bool per method = true للمحافظ | (غير مؤكد، اقتراح MENA) | ❌ | الكل | Should | P2 | 💰 |
| `payments.method.treasury_id` | ربط الطريقة بخزنة أو حساب | — | tenant | fk | [Daftra/Enerpize](https://docs.enerpize.com/user_manual/how-to-link-a-payment-method-with-a-specific-treasury/)، [Odoo](https://www.odoo.com/documentation/18.0/applications/sales/point_of_sale/payment_methods.html) | 🟡 (غير مؤكد) | الكل | Should | P2 | 💰 |
| `payments.methods_per_store` | الطرق المفعّلة لكل فرع أو جهاز | — | store/device | list = all | [Shopify](https://help.shopify.com/en/manual/sell-in-person/getting-started/setup-payment-method/enable-payments) | ❌ | الكل | Should | P2 | 💰 |
| `treasury.cash_boxes` | الخزائن والحسابات البنكية | خزنة لكل فرع أو وردية | tenant | list | [Daftra](https://docs.daftra.com/en/?p=4878) | 🟡 treasury + `treasury_transfers` موجودين (تفاصيل الخزائن غير مؤكدة) | الكل | Should | P2 | 💰 |
| `treasury.box_permissions` | صلاحيات الإيداع والسحب لكل خزنة | — | tenant | json | [Daftra](https://docs.daftra.com/en/?p=4878) | ❌ | الكل | Should | P2 | 💰🔒 |
| `expenses.categories` | تصنيفات المصروفات | — | tenant | list | [Enerpize](https://docs.enerpize.com/user_manual/comprehensive-expenses-guide/) | 🟡 (مفيش Model للتصنيفات، غير مؤكد) | الكل | Should | P2 | — |
| `finance.period_lock_date` | قفل الفترات | منع التعديل على الحركات قبل تاريخ معين، والفتح للمالك بس مع audit | tenant | date nullable | [Qoyod](https://www.qoyod.com/en/releases/fiscal-year-controls/) | ❌ | الكل | Should | P2 | 💰 |
| `finance.fiscal_year_start` | بداية السنة المالية | — | tenant | month = 1 | [Qoyod](https://www.qoyod.com/en/knowledge-base/settings/fiscal-years/)، [Zoho](https://www.zoho.com/inventory/help/settings/organization-settings.html) | ❌ | الكل | Could | P3 | — |
| `sales.total_rounding` | تقريب إجمالي الفاتورة لكل الطرق | — | tenant | enum = `none` | [Zoho](https://www.zoho.com/us/inventory/help/settings/preferences.html) | ❌ | الكل | Could | P3 | 💰 (يتعارض مع cash rounding: واحد بس يتفعّل) |
| `tax.vat_enabled` | تفعيل ضريبة القيمة المضافة | — | tenant | bool = **false** (قرار §11) | [Qoyod](https://www.qoyod.com/en/knowledge-base/settings/general-settings/)، [Loyverse](https://help.loyverse.com/help/how-configur-taxes) | ❌ (SETG-4) | الكل | Should | P1b حقول / P2 حساب | 💰 |
| `tax.registration_no` | الرقم الضريبي | بيتطبع على الفاتورة | tenant | string nullable | [Qoyod](https://www.qoyod.com/en/knowledge-base/settings/general-settings/) | 🟡 `tenants.tax_number` موجود ومش في `TenantResource` | الكل | Must | P1a (للطباعة) | — |
| `tax.activity_code` | كود النشاط (ETA) | — | tenant | string nullable | [Odoo EG](https://www.odoo.com/documentation/latest/applications/finance/fiscal_localizations/egypt.html) | ❌ | الكل | Should | P1b | — |
| `tax.prices_include_tax` | الأسعار شاملة الضريبة | — | tenant | bool = true | [Foodics](https://help.foodics.com/hc/en-us/articles/4406605505042-General-Settings)، [Zoho](https://www.zoho.com/us/inventory/help/settings/preferences.html) | ❌ | الكل | Should | P1b / P2 | 💰 |
| `tax.rates` | نسب الضريبة | 14% / معفى / جدول، ولكل نسبة `eta_code` | tenant | list = `[VAT 14%, Exempt 0%]` | [Loyverse](https://help.loyverse.com/help/how-configur-taxes)، [Square](https://squareup.com/help/us/en/article/5061-create-and-manage-your-tax-settings) | ❌ | الكل | Should | P1b / P2 | 💰 |
| `tax.item_override` | ضريبة لكل صنف | `items.tax_type`، `tax_rate`، `egs_code` | tenant | bool = true | [Square](https://squareup.com/help/us/en/article/5061-create-and-manage-your-tax-settings) | ❌ (SETG-4) | الكل | Should | P1b | 💰 |
| `tax.discount_before_tax` | الخصم قبل الضريبة | — | tenant | bool = true | [Zoho](https://www.zoho.com/us/inventory/help/settings/preferences.html)، [Qoyod](https://www.qoyod.com/en/knowledge-base/discount-settings-in-qoyod-fixed-amount-vs-percentage-and-de/) | ❌ | الكل | Should | P2 | 💰 |
| `tax.receipt_breakdown` | تفصيل الضريبة على الإيصال | مبسّط / كامل | store | enum = `simplified` | [Qoyod](https://www.qoyod.com/en/knowledge-base/customizing-invoice-and-receipt-formats-in-point-of-sale-and/) | ❌ | الكل | Should | P2 | — |
| `customers.tax_exempt` | عميل معفى | — | customer | bool = false | [Zoho](https://www.zoho.com/inventory/help/contacts/contact-details-page.html) | ❌ | الكل | Could | P2 | 💰 |
| `sales.b2b_tax_invoice` | فاتورة ضريبية للشركات | بالرقم الضريبي للمشتري | tenant | bool = false | [Rewaa](https://intercom.help/rewaa/en/articles/8630748-tax-invoices-b2b) | ❌ (الـ A4 اسمه «ضريبية» ومفيهوش ضريبة) | الكل | Could | P2 | 💰 |
| `einvoice.eta.*` | الفاتورة والإيصال الإلكتروني المصري | التفعيل، وClient ID/Secret (مشفّر)، والبيئة، والتوقيع (USB token/PIN)، وETA branch id لكل فرع، والإيصال الإلكتروني للـ POS (5 مفاتيح) | tenant/store | bool = false … | [Daftra](https://docs.daftra.com/en/?p=8766)، [Odoo EG](https://www.odoo.com/documentation/latest/applications/finance/fiscal_localizations/egypt.html)، [Odoo e-receipt module](https://apps.odoo.com/apps/modules/19.0/pos_eg_eta_receipt) (غير مؤكد) | ❌ | ETA add-on | Could | P3 | 💰🔒 |

> ZATCA (السعودية) **مش داخل في الكتالوج**، لأن السوق المستهدف مصر. لو اتقرر نتوسع للخليج، يبقى add-on حسب الدولة.

### 3.6 المستخدمين والأمان

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `security.roles` | أدوار مخصصة ومصفوفة الصلاحيات | دور المالك مقفول | tenant | roles seeded: admin/cashier/storekeeper/accountant | [Loyverse](https://help.loyverse.com/help/how-manage-access-rights-employees)، [Zoho](https://www.zoho.com/inventory/kb/users-and-roles/custom-role.html)، [Qoyod](https://www.qoyod.com/en/knowledge-base/managing-user-permissions-in-qoyod-creating-custom-roles-and/) | ✅ `UpdateRolePermissionsAction` + `roles.manage`. 🟡 `settings.manage` وصلاحيات POS الجديدة مش متعمل لها seed | الكل | Must | P1a | 🔒 |
| `security.user_store_access` | فروع كل مستخدم | — | user | list (≥1) | [Foodics](https://foodics.helpjuice.com/558773-users-overview)، [Qoyod](https://www.qoyod.com/en/knowledge-base/products-costs/user-location-permissions/) | ✅ `StoreAccess` + `X-Store-Id` | الكل | Must | P1a | 🔒 |
| `security.user_permission_overrides` | صلاحيات خاصة لمستخدم فوق دوره | — | user | map nullable | [Qoyod](https://www.qoyod.com/en/knowledge-base/managing-user-permissions-in-qoyod-creating-custom-roles-and/) | 🟡 spatie بيدعمها | الكل | Could | P2 | 🔒 |
| `security.report_access` | التقارير المسموحة لكل دور | — | role | list | [Rewaa](https://intercom.help/rewaa/en/articles/9926637-reports) | 🟡 `reports.view` صلاحية واحدة | الكل | Should | P2 | 🔒 |
| `security.user_treasury_access` | الخزائن المسموحة للمستخدم | — | user | list | [Qoyod](https://www.qoyod.com/en/knowledge-base/managing-user-permissions-in-qoyod-creating-custom-roles-and/) | ❌ | الكل | Could | P2 | 💰🔒 |
| `security.backoffice_access` | دخول لوحة الإدارة | الكاشير يدخل POS بس | role | bool = false للكاشير | [Loyverse](https://help.loyverse.com/help/how-manage-access-rights-employees) | 🟡 (غير مؤكد) | الكل | Should | P1a | 🔒 |
| `pos.pin.enabled` | دخول وتبديل وموافقة بالـ PIN | PIN مشفّر hash وunique على مستوى المستأجر | tenant | bool = true؛ length = 4 | [Loyverse](https://help.loyverse.com/help/pin-code-access)، [Square](https://squareup.com/help/us/en/article/8357-require-passcodes-at-point-of-sale) | ❌ (POSB-4) | الكل | Must | P1a | 🔒 |
| `pos.pin.length` | طول الـ PIN | 4–6 | tenant | int = 4 | [Square](https://squareup.com/help/us/en/article/8357-require-passcodes-at-point-of-sale) | ❌ | الكل | Should | P1a | 🔒 |
| `pos.pin.self_change` | الموظف يغيّر الـ PIN بنفسه | — | tenant | bool = false | [Qoyod](https://www.qoyod.com/en/knowledge-base/pos-point-of-sale/wpos-change-password-pin-2fa/) | ❌ | الكل | Could | P2 | 🔒 |
| `pos.idle_lock_minutes` | قفل الشاشة بعد الخمول | 0 = مقفول | store | int = 5 | [Shopify](https://help.shopify.com/manual/sell-in-person/setup/ipad-pos-autolock) | ❌ | الكل | Should | P2 | 🔒 |
| `pos.require_pin_each_sale` | PIN قبل كل بيعة | — | store | enum = `never` | [Lightspeed](https://retail-support.lightspeedhq.com/hc/articles/360036516153) | ❌ | الكل | Could | P2 | 🔒 |
| `pos.allowed_users_per_device` | الموظفين المسموحين على الجهاز | — | device | list = all | [Odoo](https://lindacoach.odoo.com/slides/slide/new-employee-login-screen-in-odoo-18-pos-odoo-18-point-of-sale-odoo-18-new-features-odoo-18-183) (غير مؤكد) | ❌ | الكل | Could | P2 | 🔒 |
| `security.session_lifetime_days` | مدة جلسة المستأجر | المنصة محددة 30 يوم sliding (قرار Q-B9). المالك يقدر **يقصّرها** بس | tenant | int ≤ 30 = 30 | (غير مؤكد عند المنافسين) | 🟡 (IDEN) | الكل | Could | P2 | 🔒 |
| `security.enforce_2fa` | فرض التحقق بخطوتين | مقفول / للمالك والأدمن / لكل مستخدمي الإدارة. الـ PIN مستثنى | tenant | enum = `off` | [Foodics](https://help.foodics.com/hc/en-us/articles/21050710198172-How-to-enable-Two-Factor-Authentication-for-your-Foodics-Console)، [Odoo](https://www.odoo.com/documentation/18.0/applications/general/users/2fa.html)، [Shopify](https://help.shopify.com/en/manual/organization-settings/security/two-step-authentication) | ❌ (Fortify للـ super-admin بس في P1) | الكل | Should | P2 | 🔒 |
| `security.password_policy` | سياسة كلمات المرور | الطول وأنواع الحروف | tenant | json = `{min_length:8}` | [OCA](https://odoo-modules.trobz.com/oca/server-auth/18.0/password_security.html) (add-on) | 🟡 قواعد ثابتة | الكل | Should | P2 | 🔒 |
| `security.login_lockout` | القفل بعد محاولات فاشلة | **قاعدة منصة** (throttle) وبتتطبق على الـ PIN كمان | platform | `{max:5, minutes:15}` | (غير مؤكد) | 🟡 throttle (IDEN-4.6) | الكل | Must | P1a | 🔒 |
| `security.ip_allowlist` | قائمة IP مسموحة | فيه خطر إن المالك يقفل على نفسه، لأن الـ IP في مصر غالبًا ديناميكي | tenant | list = [] | (غير مؤكد) | ❌ | Enterprise | Could | P3 | 🔒 |
| `security.login_hours` | ساعات الدخول | — | role | list = [] | [Square](https://square.com/help/us/en/article/5742) (أقرب مكافئ) | ❌ | الكل | Could | P3 | 🔒 |
| `devices.activation` | تفعيل جهاز كاشير بكود | كود بيتولد من اللوحة، وفيه إلغاء للجهاز المفقود | store | bool = true؛ expiry = 48h | [Square](https://squareup.com/help/article/8339)، [Foodics](https://foodics.helpjuice.com/557738-download-cashier-app) | ❌ (quick-login للاختبار بس) | الكل | Should | P2 | 🔒 |
| `security.audit_retention_days` | مدة الاحتفاظ بسجل النشاط | — | tenant | int = 365 (حد أقصى حسب الباقة) | [Zoho](https://www.zoho.com/invoice/help/settings/backup-your-data.html) (غير مؤكد لمنتجات POS) | 🟡 `activity_logs` موجود | `audit.logs` | Should | P2 | 🔒 |
| `security.audit_log_ip_device` | تسجيل IP والجهاز | — | tenant | bool = true (مقفول) | [Zoho Creator](https://help.zoho.com/portal/en/kb/creator/developer-guide/operations/audit-trail/articles/understand-audit-trail) | 🟡 | الكل | Should | P1a | 🔒 |
| `security.field_level` | صلاحيات على مستوى الحقل | — | role | map | [Zoho](https://www.zoho.com/inventory/kb/users-and-roles/custom-role.html) | ❌ | Enterprise | Could | P3 | 🔒 |

> صلاحيات قائمة بذاتها، **مش إعدادات** (ومعمولة كـ permissions): `pos.credit_sale`، `pos.price_override`، `pos.discount_over_limit`، `pos.return`، `pos.return_without_invoice`، `pos.cash_in_out`، `pos.no_sale_drawer`، `pos.sell_negative_stock` (لسياسة `warn` في السالب)، `settings.controls.manage` (§2.3)، `items.view_cost` (موجودة)، `invoices.cancel` (موجودة)، `customers.credit_limit.manage`. ودخول الدعم الفني (impersonation) موجود في §3.10.

### 3.7 الإشعارات والأتمتة

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `notifications.channels.telegram` | Telegram | chat ids متعددة + زر اختبار. **[CTO-2026-10-09] Q5:** بوت **واحد للمنصة**. المحل بيربط من زر «ربط Telegram» (deep link بكود `/start`)، والمخزَّن chat ids بس لكل مستأجر أو مستخدم، ولكل مستلم يختار أنواع الإشعارات. مفيش توكن بوت لكل مستأجر. تنبيهات تشغيل المنصة بتروح لـ chat منفصل للـ CTO | tenant (+user) | list = [] | (مفيش منافس native، **ميزة تميّزنا**) | 🟡 `telegram_bot_token`/`chat_id` شغالين، والتوكن **كان بيتسرّب في الـ GET** (اتقفل في SETG-7)، والـ jobs مقفولة globally ومش tenant-aware | الكل | Should | **P1a** (OPS-10 في خطة Phase 1) [CTO-2026-10-09] | 🔒 |
| `notifications.channels.email` | البريد | — | tenant | bool = true | [Square](https://squareup.com/help/article/3843) | ❌ (IDEN-3.7) | الكل | Should | P1b | — |
| `notifications.channels.whatsapp` | واتساب (API) | عن طريق provider، وفيه تكلفة رسائل. **[CTO-2026-10-09] Q12:** add-on شهري فيه عدد رسائل، ولما يخلص المالك يشحن رصيد رسائل مدفوع مقدمًا. schema الفوترة في Phase 1 لازم يدعم نوع add-on `credits` ورصيد | tenant | bool = false | [Qoyod + WATI](https://www.qoyod.com/marketplace/notifications/wati/) (Zapier، مش native) | ❌ | add-on شهري + رصيد مقدم | Could | P3 | 💰 (تكلفة) |
| `notifications.channels.sms` | SMS | **[CTO-2026-10-09] Q12:** نفس نموذج واتساب: add-on شهري برصيد رسائل + شحن مقدم | tenant | bool = false | [Qoyod + Unifonic](https://www.qoyod.com/marketplace/notifications/unifonic/) | ❌ | add-on شهري + رصيد مقدم | Could | P3 | 💰 |
| `notifications.subscriptions` | اشتراكات كل مستخدم | كل مستخدم يختار أنواع الإشعارات اللي توصله | user | json (المالك بتوصله كلها) | [Foodics](https://help.foodics.com/hc/en-us/articles/4406606538898-Activating-Users-Notification) | ❌ | الكل | Should | P2 | — |
| `notifications.daily_summary.enabled` | الملخص اليومي | — | tenant | bool = true | [Square](https://squareup.com/help/article/3843) | 🟡 job ثابت 23:59 ومقفول (`routes/console.php:32-47`) | الكل | Should | P1b | — |
| `notifications.daily_summary.time` | وقت الملخص | أو «بعد آخر وردية» | tenant | time = 23:30 | [Loyverse](https://help.loyverse.com/help/low-stocks) (وقت ثابت) | ❌ | الكل | Should | P2 | — |
| `notifications.daily_summary.sections` | محتوى الملخص | — | tenant | list | (غير مؤكد كإعداد native) | ❌ | الكل | Could | P2 | — |
| `notifications.daily_summary.send_when_zero` | الإرسال يوم مفيهوش مبيعات | إشارة إن «المحل مفتحش» | tenant | bool = false | (Square مش بيبعت، غير مؤكد) | ❌ | الكل | Could | P2 | — |
| `notifications.low_stock.enabled` / `.mode` | تنبيه النقص | فوري / ملخص يومي في وقت محدد | tenant | bool = true / enum = `daily_digest` | [Loyverse](https://help.loyverse.com/help/low-stocks)، [Zoho](https://www.zoho.com/ca/inventory/kb/items/item-reorder-notification.html) | 🟡 job Telegram ثابت | الكل | Should | P2 | 📦 |
| `notifications.out_of_stock.enabled` | تنبيه النفاد أو السالب | مهم بعد offline sync (قرار §12) | tenant | bool = true | [Rewaa](https://intercom.help/rewaa/en/articles/9979040-platform-notifications) (غير مؤكد) | ❌ | الكل | Should | P2 | 📦 |
| `notifications.shift_close.enabled` | تقرير قفل الوردية (Z) | — | store | bool = true | [Square](https://community.squareup.com/t5/Payments-Troubleshooting/Email-Notifications-Stopped-after-November-3rd/td-p/617059)، [Odoo module](https://apps.odoo.com/apps/modules/17.0/pos_z_report) | 🟡 job «وردية متأخرة» ثابت كل ساعتين | الكل | Should | P2 | 💰 |
| `notifications.shift_variance.alert` | تنبيه عجز أو زيادة الوردية | فوق `shift.variance_tolerance` | store | bool = true | [Odoo module](https://apps.odoo.com/apps/modules/17.0/pos_closure_approval) | ❌ | الكل | Should | P2 | 💰 |
| `notifications.large_discount.threshold_percent` | تنبيه الخصم الكبير | 0 = مقفول | store | decimal = 20 | (غير مؤكد كتنبيه native) | ❌ | الكل | Should | P2 | 💰 |
| `notifications.cancel_refund.alert` | تنبيه الإلغاء والمرتجع | فوق مبلغ معين | store | json = `{cancel:true, refund_min:'0.000'}` | [Square](https://square.com/help/us/en/article/5822) | ❌ | الكل | Should | P2 | 💰 |
| `notifications.large_transaction.threshold` | تنبيه العمليات الكبيرة | — | tenant | decimal nullable | [Zoho](https://www.zoho.com/inventory/help/settings/automation.html) | ❌ | الكل | Could | P3 | 💰 |
| `receivables.reminders.*` | تذكير العملاء بالمستحقات | التفعيل، والجدول (أيام قبل الاستحقاق وبعده)، والقالب، واستثناء عميل، وملخص داخلي للمتأخرات (5 مفاتيح) | tenant | bool = false … | [Zoho](https://www.zoho.com/inventory/help/settings/reminders.html)، [Odoo](https://documentation-user.readthedocs.io/en/stable/accounting/receivables/customer_payments/followup.html) | ❌ | الكل (القناة ممكن تكون مدفوعة) | Should | P3 | 💰 |
| `integrations.webhooks` + `automation.rules` | Webhooks وقواعد الأتمتة | signed headers، **ومفيش توكن في الـ URL** (قاعدة أمان المشروع) | tenant | list = [] | [Lightspeed](https://x-series-api.lightspeedhq.com/docs/webhooks)، [Zoho](https://www.zoho.com/inventory/help/settings/automation.html) | ❌ | `api.access` | Could | P3 | 🔒 |

> تنبيه دخول الموظفين، وتذكير مستحقات الموردين، وحدود الإرسال اليومية (حد على مستوى المنصة): **Could / P3**، برّه العدد.

### 3.8 الهوية والتوطين

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `company.name` | الاسم التجاري | — | tenant | string (required) | [Foodics](https://help.foodics.com/hc/en-us/articles/4406605505042-General-Settings)، [Zoho](https://www.zoho.com/inventory/help/settings/organization-settings.html) | ✅ `company_name` | الكل | Must | P1a | — |
| `company.subtitle` | الوصف تحت الاسم | — | tenant | string | — | ✅ | الكل | Should | P1a | — |
| `company.logo_light` / `company.logo_dark` | الشعار | png/jpg/webp، ومفيش SVG، وبيتعمل re-encode | tenant | file | [Loyverse](https://help.loyverse.com/help/how-add-logo-receipts)، [Square](https://squareup.com/help/us/en/article/5424) | 🟡 **الرفع مش بيتخزن** (`UpdateSettingsAction.php:16`)، والملفات `/logo*.png` مشتركة بين كل المستأجرين | الكل | Must | P1a (BRND-5) | 🔒 |
| `company.phone` / `company.address` | تليفون وعنوان المحل | — | tenant | string | [Zoho](https://www.zoho.com/inventory/help/settings/organization-settings.html) | 🟡 بيتحفظوا ومش مستخدمين في الطباعة | الكل | Must | P1a | — |
| `company.commercial_register` | السجل التجاري | — | tenant | string nullable | [Foodics](https://www.foodics.com/zatca-phase-2/) | 🟡 `tenants.commercial_register` مش في `TenantResource`، والـ A4 بيطبع `123456` | الكل | Must | P1a | — |
| `store.phone` / `store.address` | بيانات الفرع على الإيصال | لو موجودة بتغلب على بيانات المحل | store | string | [Odoo](https://odoo.com/documentation/18.0/applications/sales/point_of_sale/receipts_invoices.html) | 🟡 موجودة في `stores` ومش مستخدمة في الطباعة | الكل | Must | P1a | — |
| `company.name_localized` / `store.name_localized` | الاسم باللغة التانية | — | tenant/store | string nullable | [Foodics](https://help.foodics.com/hc/en-us/articles/4406754159634-Creating-Branches) | ❌ | الكل | Could | P3 | — |
| `branding.theme_color` | لون التطبيق | palette أو hex. لازم تحقق بـ regex | tenant | string = emerald | — | ✅ (التحقق `max:50` بس، وفيه تعارض في الـ default بين emerald وamber) | الكل | Should | P1a (BRND-11) | 🔒 |
| `branding.powered_by` | عبارة «بواسطة …» على الإيصال | **[CTO-2026-10-09] Q8:** حسب الباقة: إجبارية في التجربة وBasic، والمحل يقدر يشيلها في Pro، ومقفولة افتراضيًا في Enterprise. النص من اسم المنصة في إعدادات المنصة، والسطر **مخفي لحد ما يتحدد اسم تجاري** (مفيش اسم محايد على الإيصالات) | tenant | bool، الـ default حسب الباقة | (غير مؤكد) | ❌ | حسب الباقة | Should | P1a (BRND-12) | — |
| `locale.default` | اللغة الافتراضية للمحل | `ar` بس في الإصدار الأول (قرار §10) | tenant | enum = `ar` | [Loyverse](https://help.loyverse.com/help/account-settings-in-back-office) | 🟡 localStorage `app_locale` | الكل | Should (عمليًا قيمة منصة مقفولة على `ar` في الإصدار الأول) | P1a (SETG-3) | — |
| `user.locale` | لغة المستخدم | — | user | enum nullable | [Loyverse](https://help.loyverse.com/help/account-settings-in-back-office) | ❌ | الكل | Should | P1a (SETG-3) | — |
| `locale.timezone` | المنطقة الزمنية | للعرض وحدود اليوم بس، والتخزين مش بيتغير (Q-S1) | tenant | tz = `Africa/Cairo` | [Foodics](https://help.foodics.com/hc/en-us/articles/4406605505042-General-Settings)، [Zoho](https://www.zoho.com/inventory/help/settings/organization-settings.html) | 🟡 `APP_TIMEZONE` global | الكل | Must | P1a (SETG-2) | 💰 (حدود التقارير) |
| `locale.business_day_cutoff` | نهاية اليوم التجاري | للمحلات اللي بتشتغل بعد نص الليل. التقارير والملخص بيتجمّعوا على اليوم التجاري. **[CTO-2026-10-09] Q6:** بيتضاف في Phase 1 مع SETG-2، وبيتطبق على كل التقارير والـ dashboard وحدود يوم الوردية. شرطه الأول: توحيد جانب الكتابة على `TenantClock` | tenant | time = `00:00` | [Square (close of day)](https://squareup.com/help/us/en/article/5439)، [Foodics](https://foodics.helpjuice.com/96011-more/561508-manage-general-settings) | ❌ | الكل | Should | **P1a** (SETG-2) | 💰 |
| `locale.digits` | الأرقام | غربية 0-9. **مقفول بقرار CTO** | platform | `western` (مقفول) | — | 🟡 SETG-6 | الكل | Must | P1a | — |
| `locale.date_format` | صيغة التاريخ | — | tenant | enum = `dd/mm/yyyy` | [Zoho](https://www.zoho.com/inventory/help/settings/organization-settings.html) | ❌ | الكل | Could | P2 | — |
| `locale.phone_country_code` | كود الدولة | لواتساب وتنسيق الأرقام | tenant | string = `20` | — | ❌ hardcoded `GetInvoiceDetailsAction.php:29-30` | الكل | Should | P1a | — |
| `user.theme_preference` | الوضع الفاتح أو الداكن | — | user | enum | — | ✅ | الكل | Should | P1a | — |
| `device.profile_sync` | مزامنة تفضيلات الجهاز على السيرفر | الطابعة، الورق، الصوت، الكتالوج، الشريط الجانبي، البصمة | device | — | — | 🟡 كلها localStorage | الكل | Should | P2 | — |

### 3.9 التقارير

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `reports.branding_header` | ترويسة التقارير المطبوعة | الاسم والشعار والعملة من الإعدادات | tenant | (مشتق) | — | 🟡 `ReportPrintController.php:76` فيه `ج.م` hardcoded | الكل | Should (مش لازم لأول بيعة) | P1a | — |
| `reports.default_store_scope` | النطاق الافتراضي | كل الفروع أو الفرع الحالي | user | enum = `current` | [Loyverse](https://loyverse.com/back-office) | 🟡 | الكل | Should | P2 | 🔒 (عزل) |
| `reports.default_period` | الفترة الافتراضية | اليوم / الأسبوع / الشهر | user | enum = `today` | — | ❌ | الكل | Could | P2 | — |
| `reports.export.permission` | صلاحية التصدير | منفصلة عن العرض | role | permission `reports.export` | [Loyverse](https://help.loyverse.com/help/exporting-data-from-loyverse-account) | ❌ | الكل | Should | P2 | 🔒 |
| `reports.show_cost_profit` | إظهار التكلفة والربح | — | role | permission `items.view_cost` | [Loyverse](https://help.loyverse.com/help/how-manage-access-rights-employees) | ✅ | الكل | Should | P1a | 🔒 |
| `reports.include_cancelled` | إظهار الملغي في التقارير | — | user | bool = false | [Square](https://square.com/help/us/en/article/6146) (غير مؤكد كإعداد) | ❌ | الكل | Could | P2 | 💰 |
| `reports.scheduled` | التقارير المجدولة | التكرار والوقت والصيغة والمستلمين | tenant | list = [] | [Zoho](https://www.zoho.com/ae/inventory/help/reports/managing-reports.html) | ❌ | `reports.advanced` | Should | P3 | — |
| `reports.dashboard_widgets` | عناصر لوحة التحكم | — | user | list | — | ❌ | الكل | Could | P3 | — |

### 3.10 الحساب والاشتراك

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `account.shop_code` | كود المحل والنطاق | read-only. بيستخدمه تطبيق Android | tenant | string | — | ✅ domains | الكل | Must | P1a | — |
| `account.plan_usage` | الباقة والاستخدام | الباقة، والـ add-ons، والاستخدام مقابل الحدود، والـ nudge | tenant | read-only | [Shopify](https://help.shopify.com/en/manual/your-account/manage-account?r_done=1)، [Daftra](https://docs.daftra.com/en/?p=4418) | ❌ (ENTI-2.9) | الكل | Must | P1a | — |
| `account.billing_contact` | جهة الفوترة | اسم وبريد وتليفون لاستلام فواتير الاشتراك والتنبيهات | tenant | json | [Shopify](https://help.shopify.com/en/manual/your-account/manage-account?r_done=1) | ❌ | الكل | Should (مش لازم لأول بيعة) | P1a | — |
| `account.subscription_payments` | فواتير الاشتراك ورفع إيصال الدفع | InstaPay أو فودافون كاش، والـ super-admin بيفعّل (قرار §8) | tenant | — | [Daftra](https://docs.daftra.com/en/?p=4418) (إرفاق صورة التحويل) | ❌ (ENTI-3) | الكل | Must | P1a | 💰🔒 (upload) |
| `account.billing_cycle` | دورة الفوترة | شهري / سنوي | tenant | enum = `monthly` | [Daftra](https://docs.daftra.com/en/?p=4418) | ❌ | الكل | Should | P1a | 💰 |
| `account.billing_tax_info` | بيانات الفاتورة الضريبية للاشتراك | الأسعار من غير ضريبة 14%. وفيه الرقم الضريبي للعميل لو B2B | tenant | json | — | ❌ | الكل | Should | P1a | 💰 |
| `account.addon_requests` | طلب add-on أو ترقية | يدوي لحد ما Paymob يجهز | tenant | — | [Shopify](https://help.shopify.com/en/manual/your-account/manage-account?r_done=1) | ❌ | الكل | Should | P1a | 💰 |
| `account.support_access` | دخول الدعم الفني | **[CTO-2026-10-09] Q9:** `allowed` (الافتراضي) أو `on_request`: الدعم يطلب، والمالك يوافق، والموافقة صالحة حوالي ساعة. **مفيش وضع «ممنوع».** موظفو الدعم (support): في `on_request` لازم موافقة، وجلساتهم ظاهرة للمالك (السبب والمدة، كـ «دعم المنصة»). الـ super-admin: دخول صامت، من غير موافقة ولا إشعار ولا أي أثر في سجل نشاط المستأجر، **لكن كل جلسة بتتسجل** في سجل audit داخلي للمنصة (`CentralAuditLog`) يشوفه الـ CTO بس، ومفيش جلسة من غير تسجيل. القرار ده بيعدّل جزء من قرار ظهور الـ impersonation (Q-L8 في خطة Phase 1) | tenant | enum `allowed\|on_request` = `allowed` | (غير مؤكد) | ❌ (IDEN-2.5…2.12) | الكل | Should | P1a | 🔒 |
| `account.data_export` | تصدير كل بيانات المحل | ZIP أو Excel، للمالك بس، ورابط مؤقت | tenant | — | [Loyverse](https://help.loyverse.com/help/exporting-data-from-loyverse-account)، [Zoho](https://www.zoho.com/invoice/help/settings/backup-your-data.html) | ❌ | الكل | Should | P2 | 🔒 |
| `account.cancel_request` | طلب إلغاء أو إغلاق الحساب | أرشفة بعد نسخة احتياطية، والبيانات بتفضل 90 يوم (قرار §7) | tenant | — | [Shopify](https://help.shopify.com/en/manual/your-account/manage-account?r_done=1) | ❌ (الحذف معطّل W0) | الكل | Should | P2 | 🔒📦 |
| `account.owner_transfer` | نقل الملكية | — | tenant | — | (غير مؤكد) | ❌ | الكل | Could | P3 | 🔒 |

### 3.11 المشتريات والتوزيع وافتراضيات الأصناف

المشتريات core في كل الباقات (product-overview §5). و`wholesale_van` نوع فرع مسعّر (product-overview §4: سيارة في Pro و5 في Enterprise + add-on بـ 249)، فإعدادات السيارة بتظهر بس لو المستأجر عنده فرع من النوع ده. المنافسين هنا مش متراجعين بالتفصيل، فالبنود دي **(اقتراح)** لحد ما يتعمل بحث مخصص.

| key | الاسم | الوصف | النطاق | النوع والافتراضي | منافسين | الكود | الباقة | الأولوية | المرحلة | مخاطر |
|---|---|---|---|---|---|---|---|---|---|---|
| `purchases.update_selling_price_on_receive` | تحديث سعر البيع عند استلام المشتريات | مقفول / اقتراح السعر الجديد (المستخدم يأكد) / تلقائي بنفس هامش الربح. التلقائي محتاج `items.manage` | tenant (+store) | enum `off\|suggest\|auto` = `suggest` | (اقتراح) | ❌ | الكل | Should | P2 | 💰 |
| `purchases.cost_change_alert_percent` | تنبيه تغيّر التكلفة | تنبيه لو تكلفة الشراء الجديدة اختلفت عن المتوسط بأكتر من النسبة. 0 = مقفول | tenant | decimal = `0.000` | (اقتراح) | ❌ | الكل | Could | P3 | 💰 |
| `purchases.returns.require_original_invoice` | مرتجع المشتريات من فاتورة شراء أصلية | لو مقفول، المرتجع للمورد بدون فاتورة بيحتاج صلاحية. السالب ممنوع فيه (`inventory.negative_stock.internal_ops`) | tenant | bool = true | (اقتراح) | ❌ (غير مؤكد) | الكل | Should | P2 | 💰📦 |
| `van.end_of_day_reconciliation` | تسوية آخر اليوم للسيارة | تسوية مخزون السيارة (المحمّل − المباع − المرتجع = الباقي) ونقدية المندوب قبل إقفال اليوم، والفرق بيتسجل بسبب وموافقة | store (`wholesale_van`) | enum `off\|required` = `required` | (اقتراح) | ❌ | الكل (حسب حد السيارات في الباقة) | Should | P2 | 📦💰 |
| `van.allowed_customers` / `van.credit_sale.enabled` | عملاء خط السيارة والآجل فيها | قائمة العملاء أو المناطق المسموحة للمندوب، وهل الآجل مسموح من السيارة (مع `customers.credit.limit_mode`) | store (`wholesale_van`) | list nullable = all / bool = true | (اقتراح) | ❌ | الكل (حسب حد السيارات في الباقة) | Should | P2 | 💰🔒 |
| `items.default_tax_type` | نوع الضريبة الافتراضي للصنف الجديد | بيتملي في فورم الصنف (14% / معفى). مربوط بـ `tax.vat_enabled` | tenant | enum = `exempt` | (اقتراح) | ❌ | الكل | Could | P2 | 💰 |
| `items.sku_auto_generate` | توليد كود الصنف تلقائيًا | بادئة وتسلسل. مختلف عن الباركود (`inventory.barcode.auto_generate`) | tenant | json = `{enabled:false, prefix:'ITM-'}` | (اقتراح) | ❌ | الكل | Could | P3 | — |
| `pricing.default_markup_percent` | هامش الربح الافتراضي | بيقترح سعر البيع من التكلفة في فورم الصنف وعند الاستلام (مع `purchases.update_selling_price_on_receive`) | tenant | decimal nullable | (اقتراح) | ❌ | الكل | Could | P3 | 💰 |

---

## 4. شكل شاشة الإعدادات المقترح

**التابات** (بالترتيب، RTL، والأيقونات من lucide):

1. **عام**: الهوية والشعار، والتوطين (اللغة، والمنطقة الزمنية، ونهاية اليوم، والعملة)، ونوع النشاط (القالب).
2. **الفروع**: بيانات كل فرع والـ overrides بتاعته (انظر تحت).
3. **نقطة البيع والورديات**: العميل الافتراضي، والخصم والسعر، والموافقات، والمرتجع، والتعليق، والميزان، والتقريب، والوردية.
4. **الفواتير والطباعة**: الترويسة والتذييل ومعاينة حية للإيصال (حراري/A4)، والترقيم، والإلغاء، والمشاركة.
5. **المخزون والمشتريات**: الوحدات، والسالب، وحد النقص، والجرد، والتسويات، والتحويلات، والباركود والملصقات، وافتراضيات الأصناف، وإعدادات الاستلام والمرتجع للمورد. وإعدادات السيارة بتظهر في تاب «الفروع» على فرع `wholesale_van`.
6. **العملاء والموردين**: الائتمان، وشروط الدفع، وقوائم الأسعار، والحقول، والولاء.
7. **المالية والضرائب**: طرق الدفع، والخزائن، والمصروفات، والضريبة، وقفل الفترات، وETA (مقفول بالـ add-on).
8. **المستخدمين والأمان**: الأدوار (روابط للشاشة الموجودة)، والـ PIN، والجلسات، و2FA، وسجل النشاط.
9. **الإشعارات**: القنوات، والاشتراكات لكل مستخدم، والتنبيهات وأوقاتها.
10. **التقارير**.
11. **الحساب والاشتراك**: الباقة والاستخدام، والفواتير، والدعم، والتصدير.
12. **الجهاز** (بيظهر على Electron/Android): الطابعة، والورق، والدرج، وشاشة العميل، والصوت. والجهاز هو المكان اللي بيتحفظ فيه الـ device profile.

**البحث:** مربع بحث في الأعلى، بيدوّر في الاسم العربي والوصف والـ `aliases_ar` والـ key (للدعم الفني). النتيجة بتفتح التاب وتعمل highlight للإعداد. وفيه deep link بالشكل `/settings?k=pos.discount.max_percent`، عشان الدعم يبعت رابط مباشر.

**المتقدم:** كل تاب بيعرض الأساسي بس. وفيه مفتاح «إظهار الإعدادات المتقدمة» (بيتحفظ لكل مستخدم) بيظهر الإعدادات اللي `advanced=true`، زي صيغة باركود الميزان، والترقيم، والتقريب، والسالب لكل فرع.

**مستوى الفرع:** فيه selector فوق «النطاق: كل الفروع ▾ / فرع وسط البلد». في نطاق الفرع، كل إعداد قابل للـ override بيعرض:
- القيمة الموروثة، وجنبها شارة «موروث من المحل».
- زر «تخصيص لهذا الفرع»، وبعد التخصيص يظهر زر «رجوع للموروث».
- الإعدادات اللي نطاقها tenant بس بتظهر read-only.

والـ manager بيشوف بس الفروع اللي مسموحله بيها.

**عناصر عامة:**
- شارة «معدّل عن الافتراضي» + «استرجاع الافتراضي».
- قفل الباقة بزر ترقية.
- قفل المنصة بأيقونة ونص يوضح السبب.
- حفظ لكل قسم، وتحذير لو فيه تغييرات مش محفوظة.
- skeleton shimmer، وdark/light، وأهداف لمس 44px.
- على الموبايل: قائمة التابات، وبعدين صفحة التفاصيل.
- معاينة حية للإيصال جنب إعدادات الطباعة.
- «آخر تعديل بواسطة … منذ …» مأخوذة من الـ audit.

---

## 5. الفجوات الحرجة في الكود الحالي

| # | الفجوة | الدليل | الأثر |
|---|---|---|---|
| 1 | إعدادات الطباعة الستة بتتحفظ ومش بتتقري | `InvoicePrintView.vue`، `InvoiceShowThermalReceipt.vue`، `InvoiceShowA4Document.vue` | المالك يفتكر الإعداد اشتغل، وهو مشتغلش |
| 2 | الـ A4 بيطبع بيانات وهمية | `useInvoiceShow.js:30-33` (`01012345678`، `123456`، `987-654-321`)، و`TenantResource` مش بيرجّع `address/commercial_register/tax_number` | خطر قانوني على فاتورة اسمها «ضريبية» |
| 3 | رفع الشعار مش بيتخزن، والشعارات مشتركة بين كل المستأجرين | `UpdateSettingsRequest.php:39-41`، `UpdateSettingsAction.php:16`، `public/logo*.png` | تسرّب هوية بين المستأجرين 🔒 |
| 4 | `telegram_bot_token` بيرجع نص صريح في الـ GET، ورسالة الـ exception بتوصل للعميل، و`sendTestTelegram` بيحفظ من غير تحقق | `SettingController.php:51,92` | تسريب أسرار 🔒 |
| 5 | صلاحية `settings.manage` مستخدمة ومش متعمل لها seed. والإعدادات محمية فعليًا بـ `roles.manage`/admin | `SettingController.php:30`، `UpdateSettingsRequest.php:11-15`، `PermissionsSeeder.php` | مفيش طريقة نفوّض بيها مدير على الإعدادات من غير ما ياخد صلاحية الأدوار 🔒 |
| 6 | إعدادات المنصة في جدول `settings` بتاع المستأجر، ومفيش جدول مركزي (غير مؤكد في prod). والـ tests بتخبي ده | `SuperAdminApiController.php:328-390`، `tests/TestCase.php:14` | شاشة super-admin ممكن تفشل في الحفظ |
| 7 | الوحدات المحفوظة مش مستخدمة، والقائمة متكررة في 6 أماكن أو أكتر | `ItemsView.vue:97`، `ItemFormModal.vue:132`… | الإعداد ملوش أي أثر |
| 8 | 8 أماكن (7 Form Requests + ثابت واحد) بقوائم ثابتة لطرق الدفع، بـ 3 مجموعات قيم مختلفة (A/B/C)، ومفيش فودافون كاش صريح | §3.5 صف `payments.methods`، `Requests/Concerns/ValidatesCheckoutPayments.php:15` | تقارير الخزينة متلخبطة 💰 |
| 9 | البيع مش مربوط بوردية، ومفيش حد للخصم، ومفيش أرضية للسعر | `InvoiceService`، `StorePOSInvoiceRequest.php:88-98` | تسرّب فلوس 💰 |
| 10 | `credit_limit` من غير عمود في الـ migrations (موجود في sqlite محلي بس) | `CustomerResource.php:21` | الآجل من غير سقف 💰 |
| 11 | العملة `ج.م` hardcoded في 8 ملفات backend + lang، وكود الدولة `20`، واسم العميل النقدي بثلاث صيغ | §E في الجرد | بيمنع إن المنصة تبقى generic |
| 12 | الحساب بيعمل truncation على 3 خانات، والعرض على خانتين، ومفيش قاعدة تقريب موحدة | `helpers/decimal.js:12`، `useFormatters.js` | فروق قروش بين الواجهة والسيرفر 💰 |
| 13 | الترقيم hardcoded، والمدفوعات بـ `uniqid` | `InvoiceService.php:270,887`، `PaymentService.php:52` | ترقيم مش متسلسل ومش قابل للتدقيق 💰 |
| 14 | حدود الباقات والـ features مش متطبقة، و`FeatureGate.vue` مش مستخدم | `Tenant::hasFeature`، `TenantFeatureManager` | مفيش plan gating للإعدادات (ENTI) |
| 15 | routes قديمة بتشاور على methods مش موجودة | `routes/tenant.php:231-236` | 500 |
| 16 | تفضيلات الجهاز كلها في localStorage | `desktop_paper_width`، `pos_sound_enabled`… | بتضيع مع تغيير الجهاز أو مسح المتصفح |
| 17 | الـ jobs المجدولة أوقاتها ثابتة، ومقفولة globally، ومش tenant-aware | `routes/console.php:28-47` | الإشعارات عمليًا مش شغالة |
| 18 | تعارض الـ default في لون الثيم بين emerald وamber، وmax:50 من غير تحقق hex | `useSettings.js:113,219`، `stores/appConfig.js:11` | — |
| 19 | المكونات اليتيمة `BrandingTab/BackupTab/SystemTab/TelegramTab/ThemeTab.vue` | `Components/Settings/` | ارتباك عند التطوير (BRND-11 بيستخدم `BrandingTab`) |
| 20 | `users.show_print_subtitle` و`invoice_primary_color` و`logo_*_v` كلها dead | §A و§D | تنظيف |

---

## 6. الأثر على خطة Phase 1

### 6.1 مهام لازم تتوسع

| المهمة | التوسيع المقترح | جهد إضافي تقديري |
|---|---|---|
| **SETG-1** | بدل getters مُنمَّطة لأربع قيم: **registry كامل** (§2.2) + أعمدة `scope_type/scope_id/updated_by` + resolver فيه override للفرع + توليد قواعد الـ validation + أسرار write-only + cache version bump + audit لكل تعديل + seed لصلاحيات `settings.*`. لازم يخلص قبل أي إعداد جديد | +3 أيام |
| **SETG-5** | يتحول من «أقسام العملة والضريبة» إلى **shell شاشة الإعدادات** (§4): التابات، والبحث، والمتقدم، وselector الفرع، وشارات الموروث والقفل. ونقترح نقله من 1b إلى **1a** لأن إعدادات POS بتعتمد عليه. **[CTO-2026-10-09] Q10: اتنقل لـ 1a** | +3 أيام (ونقل للـ 1a) |
| **SETG-4** | يتضاف `tax.registration_no` و`company.commercial_register` **للطباعة في 1a**، حتى لو حساب VAT فضل في P2 | +0.5 يوم |
| **BRND-5** | إصلاح الشعار + `receipt.header_lines/footer_text` + **legal info في `TenantResource`** + `store.phone/address` | +1 يوم |
| **BRND-11** | التاب يبقى جوه shell الـ SETG-5، ويتحذف كل المكونات اليتيمة، وتتشال الواجهة القديمة `SettingsPrintingSection` | +0.5 يوم |
| **BRND-12** | توصيل كل مفاتيح `receipt.show.*` (6 الموجودين + cashier/payment_breakdown/discounts/reprint_marker) في `ThermalReceipt` وA4، وإزالة الـ placeholders من `useInvoiceShow.js` | +1.5 يوم |
| **POSB-4/5/8** | بيقروا من الـ registry: `pos.manager_approval.actions`، `pos.discount.max_percent`، `pos.price.floor_policy`، `pos.hold.*`، `pos.cash_in_out.reasons` | داخل التقدير الحالي |
| **SEC-1** | يتضاف لمراجعة التصميم: أسرار الإعدادات، وصلاحيات الـ manager على override الفرع، واستيراد الإعدادات | +0.5 يوم |

### 6.2 مهام جديدة مقترحة لـ Phase 1a

| id مقترح | المهمة | جهد |
|---|---|---|
| SETG-7 | **إصلاح أمني عاجل:** إخفاء `telegram_bot_token`، ومنع رسالة الـ exception، والتحقق في `sendTestTelegram`، وحذف routes `tenant.php:231-236` المكسورة | 0.5 |
| SETG-8 | توحيد **طرق الدفع** في جدول أو إعداد واحد (`payments.methods`) واستبدال قوائم `in:` الحرفية في الـ 7 Form Requests والثابت `CHECKOUT_PAYMENT_METHODS` (3 مجموعات قيم)، مع mapping للقيم القديمة (`wallet`/`bank`) | 2 |
| SETG-9 | قواعد البيع: `shift.required_to_sell`، و`pos.default_customer_id` (وتوحيد اسم العميل النقدي)، و`invoices.cancel.cashier_window` (مع fallback لـ `same_day` لو مفيش وردية)، وصلاحية `settings.controls.manage` + test منع الـ manager (§2.3)، و`customers.credit.limit_mode` + migration `credit_limit` | 3 |
| SETG-10 | `inventory.units` حقيقي (حذف 6 نسخ hardcoded)، و`inventory.low_stock.default_threshold` | 1 |
| SETG-11 | `pos.scale_barcode.*` لكل فرع (parser + tests بقيم حقيقية) | 2 |
| SETG-12 | قوالب نوع النشاط في الـ onboarding (§2.8) | 1.5 |
| SETG-13 | `finance.calc_rounding` موحّد (half-up) في `helpers/decimal.js` وbcmath، مع tests تطابق بين الواجهة والسيرفر | 1.5 |

**الإجمالي:** حوالي **+24 يوم عمل بشري** (حوالي 10–12 يوم مع الـ agents). لو محتاجين نقص، ده الترتيب: SETG-12 ← تسيبه P2، والبحث في SETG-5 ← P1b، وSETG-11 بس لو أول عميل مش بيبيع بالوزن.

> **[CTO-2026-10-09] Q10 — اتعتمد:** SETG-5 اتنقل لـ 1a، وSETG-8…13 كلها في 1a وموزعة على W2–W4، وSETG-7 اتنفذ في W1. التوزيع النهائي والجهد المعتمد في [`phase-1-plan.md`](../05-planning/phase-1-plan.md) §3.2 و§4.10، وفيه فروق عن الجدول اللي فوق: الائتمان طلع من SETG-9 لـ POSB-1 (Q3)، والسالب بثلاث حالات دخل SETG-9 (Q2) فبقى 4 أيام، وSETG-11 بقى يوم واحد لأن POSB-2 سلّم الـ parser والـ API في W1، و`business_day_cutoff` مع توحيد `TenantClock` بقى امتداد لـ SETG-2 في W2.

### 6.3 ما يتأجل

- **P1b:** حقول VAT/ETA وواجهتها (SETG-4، وقسم الضريبة جوه shell الـ SETG-5)، وقناة البريد، والملخص اليومي. (**[CTO-2026-10-09]** ربط Telegram ببوت المنصة بقى 1a.)
- **P2:** السالب لكل فرع (السياسة نفسها بقت P1a، [CTO-2026-10-09])، وقوائم الأسعار، والترقيم القابل للتخصيص، وتقريب النقدي، وحساب VAT، والتقريب بالفئات، والجرد والتسويات والتحويلات بالاعتماد، وطرق الدفع لكل فرع والخزائن، و2FA للمستأجر، وdevice profile، وتصدير البيانات، وoffline.
- **P3:** الولاء، والتشغيلات والصلاحية والسيريال، وETA، والعملات الأجنبية، وwebhooks والأتمتة، وواتساب/SMS API، والتقارير المجدولة، والإنجليزي ولغة الإيصال، وعروض الأسعار.

---

## 7. قرارات الـ CTO — ANSWERED 2026-10-09

الأسئلة الـ 12 اتجاوبت كلها بتاريخ 2026-10-09. القرار هو المرجع الملزم، ونص السؤال الأصلي متساب للتاريخ. الجداول في §2 و§3 اتحدّثت بعلامة **[CTO-2026-10-09]**، والمهام والـ waves في [`phase-1-plan.md`](../05-planning/phase-1-plan.md).

| # | السؤال (مختصر) | الحالة | قرار الـ CTO |
|---|---|---|---|
| 1 | default الـ `shift.required_to_sell`، والمحل الحالي بعد الترحيل؟ | **ANSWERED 2026-10-09** (فيه جزء مفتوح) | الـ default حسب قالب النشاط: إلزامي في التجزئة والوزن، واختياري في الجملة والسيارات، والمالك يغيّره في الاتجاهين. لما الورديات تبقى مقفولة، نافذة إلغاء الكاشير بتتحسب `same_day`. **مفتوح:** المحل الحالي بعد الترحيل (هل بيستخدم ورديات النهارده؟) |
| 2 | السالب في المخزون: منع في P1 والخيارات في P2، ولا `warn` في P1؟ | **ANSWERED 2026-10-09** | `inventory.negative_stock.policy` = `block`/`warn`/`allow` متاحة في Phase 1 لكل المستأجرين، والـ default `block`. إعداد رقابة للمالك بس حتى على مستوى الفرع. `warn` محتاج PIN مدير أو صلاحية `pos.sell_negative_stock`، وبيتسجل ويظهر في تقرير. المزامنة بعد الـ offline دايمًا بتقبل السالب وتبعت تنبيه ← SETG-9 |
| 3 | حد الائتمان في P1a ولا تأجيل؟ | **ANSWERED 2026-10-09** (فيه تأكيدين فرعيين مستنيين) | في Phase 1a: عمود `customers.credit_limit` + وضع على مستوى المستأجر `none`/`warn`/`block`. التجاوز في `block` بيحتاج PIN مدير معاه الصلاحية. شاشة الدفع في الـ POS بتعرض الرصيد والحد والرصيد بعد البيعة ← داخل POSB-1. **مستني تأكيد:** (أ) الحد الفاضي = بلا حد؛ (ب) الـ default للمستأجر الجديد = `warn` |
| 4 | half-up موحّد بدل الـ truncation؟ | **ANSWERED 2026-10-09** | half-up موحّد: scale 3 في الحساب الداخلي، والعرض بعدد خانات عملة المستأجر، ونفس القاعدة في PHP وJS. الضريبة مرة واحدة على صافي الفاتورة. بيسري على الفواتير الجديدة بس. تقريب النقدي (لـ 0.5/1) إعداد منفصل في Phase 2 ← SETG-13 |
| 5 | بوت المنصة ولا توكن بوت لكل مستأجر؟ | **ANSWERED 2026-10-09** | بوت Telegram **واحد للمنصة**. المحل بيربط بزر «ربط Telegram» (deep link بكود `/start`)، والمخزَّن chat ids بس لكل مستأجر/مستخدم مع اختيار الإشعارات لكل مستلم. مفيش توكن بوت لكل مستأجر. تنبيهات تشغيل المنصة لـ chat منفصل للـ CTO ← امتداد OPS-10 |
| 6 | `locale.business_day_cutoff` دلوقتي ولا P2؟ | **ANSWERED 2026-10-09** | يتضاف في Phase 1 مع SETG-2 (default `00:00`)، ويتطبق على كل التقارير والـ dashboard وحدود يوم الوردية. شرطه توحيد جانب الكتابة على `TenantClock` الأول |
| 7 | مصفوفة «مين يعدّل إيه»، و`settings.controls.manage` مستقلة ولا مدموجة؟ | **ANSWERED 2026-10-09** | المصفوفة معتمدة (§2.3): المالك كل حاجة؛ المدير نقطة البيع والمخزون والطباعة في فروعه بس؛ التفضيلات الشخصية للكل؛ الحساب والأمان والمالية للمالك بس. إعدادات الرقابة الثمانية محتاجة صلاحية **مستقلة** `settings.controls.manage` (تتدّي لمدير عام موثوق من غير صلاحيات مالية)، ومديرين الفروع ما يعدّلوهاش في الـ default |
| 8 | `branding.powered_by`؟ | **ANSWERED 2026-10-09** | حسب الباقة: إجباري في التجربة وBasic، والمحل يشيله في Pro، ومقفول افتراضيًا في Enterprise. النص من اسم المنصة في إعدادات المنصة، ومخفي لحد ما يتحدد اسم تجاري (مفيش اسم محايد على الإيصالات) ← BRND-12 |
| 9 | المالك يقدر يرفض دخول الدعم؟ | **ANSWERED 2026-10-09** (بيعدّل جزء من قرار ظهور الـ impersonation) | `account.support_access` = `allowed` (الافتراضي) أو `on_request` (الدعم يطلب، والمالك يوافق، والموافقة صالحة حوالي ساعة). مفيش وضع «ممنوع». **موظفو الدعم:** موافقة لازمة في `on_request`، وجلساتهم ظاهرة للمالك. **الـ super-admin:** دخول صامت (من غير موافقة ولا إشعار ولا أثر في سجل نشاط المستأجر)، لكن **كل جلسة بتتسجل** في audit داخلي للمنصة يشوفه الـ CTO بس، ومفيش جلسة من غير تسجيل ← IDEN-2.5/2.6/2.8/2.12 |
| 10 | نقل SETG-5 لـ 1a وإضافة SETG-7…13؟ | **ANSWERED 2026-10-09** | موافقة: SETG-5 (شاشة الإعدادات الكاملة) لـ 1a + SETG-8 (قائمة طرق دفع واحدة فيها فودافون كاش وإنستاباي)، SETG-9 (قواعد البيع)، SETG-10 (الوحدات)، SETG-11 (باركود الميزان لكل فرع)، SETG-12 (قوالب النشاط عند التسجيل)، SETG-13 (التقريب الموحّد)، كلها في 1a وموزعة على W2–W4 (حوالي +24 يوم بشري، 10–12 يوم بالوكلاء). SETG-7 اتنفذ في W1 |
| 11 | القوالب التلاتة كفاية؟ | **ANSWERED 2026-10-09** | في 1a: تجزئة، وجملة وتوزيع، وبيع بالوزن بس. الموبايلات والإلكترونيات (سيريال) والصيدلية والبقالة (صلاحية وتشغيلات) مع الميزات دي في Phase 3، والملابس (مقاسات وألوان) بعد كده |
| 12 | واتساب API وSMS: add-on ولا رصيد مدفوع مقدمًا؟ | **ANSWERED 2026-10-09** | الاتنين: add-on شهري فيه عدد رسائل + شحن رصيد مدفوع مقدمًا لما يخلص (Phase 3). schema الفوترة في Phase 1 لازم يدعم نوع add-on `credits` ورصيد ← ENTI-1.10 في خطة Phase 1. مشاركة الفاتورة يدويًا برابط واتساب مجانية في كل الباقات |

**التأكيدات المتبقية (مش مانعة للبدء):**
1. حد الائتمان: الحد الفاضي = بلا حد؟ (بند 3-أ، ونفس افتراض POSB-1 بند (ب) في خطة Phase 1)
2. حد الائتمان: default الوضع للمستأجر الجديد = `warn`؟ (بند 3-ب)
3. المحل الحالي بعد الترحيل: بيشتغل بورديات؟ (بند 1، بيحدد قيمة `shift.required_to_sell` عنده)
