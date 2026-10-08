# تحليل ترحيل المحل الحالي (`main`) إلى الـ SaaS (tenant)

> **الحالة:** تحليل وتخطيط فقط، ولم يتغيّر أي كود. التاريخ: 2026-10-08.
> **المرجع:** قرارات الـ CTO في [`product-overview.md`](../01-overview/product-overview.md)، وخاصة §15 (نقل إصلاحات `main`) و§16 (ترحيل المحل) و§18 (المراحل).
> **مصدر المقارنة:** قرأنا `main` بـ `git show` و`git ls-tree` فقط، من غير checkout.
> **تنبيه مهم:** الـ ref المحلي `main` (`6c43161f`) **قديم**. الفرع الذي يعمل عليه المحل فعلًا هو **`origin/main` (`930250b4`)**، وفيه `d08edc92` (إصلاح WAC، PR #2) و`a04be5a7` (إصلاح الدفع المكرر، PR #3)، وفيه أيضًا migration `2026_09_29_000001_add_cost_price_to_return_items_table`. لذلك يجب أن تقرأ كل أدوات الاستيراد والتحليل من `origin/main`.
> لا تحتوي هذه الوثيقة على أسرار أو عناوين خوادم أو بيانات عملاء.

---

## 1. الملخص وحجم الشغل

### الخلاصة
- **الـ schema متطابق تقريبًا.** الـ migrations الخاصة بـ `invoices` و`invoice_items` و`payments` و`returns` و`additional_expenses` و`items` و`stores` و`store_stocks` و`stock_*` و`purchases*` و`suppliers` و`cash_shifts` و`expenses` و`treasury_transfers` و`users` و`settings` و`activity_logs` و`audit_logs` وجداول الصلاحيات متطابقة byte-by-byte، باستثناء المسافات (whitespace). كل أعمدة الفلوس والكميات `DECIMAL(12,3)` في الجهتين، فلا نحتاج أي تحويل للدقة. أسماء الـ models واحدة كذلك، فقيم `source_type` و`document_type` و`model_type` الـ polymorphic تنتقل 1:1.
- **فروق الأعمدة قليلة:**
  - في الـ SaaS فقط: `customers.price_tier` و`invoices.client_uuid` و`invoices.change_amount` و`users.api_token` و`users.last_login_at`، و`items.min_selling_price` و`category_id` و`image` و`pos_*`، وجدول `categories`.
  - في `main` فقط: `return_items.cost_price` وجدولا `quotations` و`quotation_items`.
- **الخطر الحقيقي في السلوك، لا في الشكل.** البيانات الحية كتبها كود `main` بعد إصلاحه (WAC وPR #3 وتسوية FIFO)، أما الـ SaaS فما زال يشغّل الكود القديم قبل الإصلاح. لو استوردنا قبل نقل الإصلاحات، فأول تعديل لفاتورة مستوردة سيحصّل الفلوس مرتين، وأول إلغاء لمشتريات سيعيد إفساد الـ WAC.
- **الترحيل يكون بالتاريخ كاملًا لا بأرصدة افتتاحية.** الخزينة وأرصدة العملاء والموردين **مشتقة** من المستندات، ولا يوجد جدول أرصدة افتتاحية للخزينة أو للعملاء أو للموردين. لذلك يجب نقل كل التاريخ مع الاحتفاظ بالـ ids والـ timestamps كما هي.

### التقدير (أيام-مطور، بتقدير بشري)
| البند | أيام | ملاحظة |
|---|---|---|
| **شرط مسبق:** نقل إصلاحات `main` (`d08edc92` وPR #3 و`e73cbd1c` FIFO وتوزيع الخصم وguard الـ restore) مع اختبارات | (8–12) | **ضمن Phase 2** حسب §15، و**غير محسوب** في المجموع أدناه |
| tenant migrations محمية (guarded): `return_items.cost_price` و`customers.price_tier` (إصلاح) و`quotations*`، مع تعديلات الـ models (`SoftDeletes` على `ReturnItem`) | 2 | |
| تصميم مستند «رصيد افتتاحي» للعملاء والموردين (جدول أو نوع مستند) ودمجه في `CustomerBalanceService` و`SupplierBalanceService` والـ ledger | 3 | يحتاج قرار CTO (§8) |
| حد أدنى لعروض الأسعار في الـ SaaS (model + قراءة/أرشيف). الـ API الكامل خارج النطاق | 1–4 | حسب قرار §8 |
| أمر الاستيراد `tenant:import-main-dump` (staging DB + mapping + chunking + idempotency + تقارير) | 6–8 | |
| تقارير ما قبل الاستيراد (pre-flight audits) | 2 | |
| أمر المطابقة `tenant:reconcile-import` + اختبارات | 4–5 | |
| Feature/Unit tests للمستورد (fixture dump صغير) | 2–3 | |
| بروفة staging (مرتين على الأقل) وإصلاح ما يظهر | 3–4 | |
| الـ cutover الفعلي والمتابعة بعده | 1–2 | |
| **المجموع (بدون نقل الإصلاحات)** | **≈ 24–33 يومًا** | ≈ 5–7 أسابيع بشريًا، وأقل بالعمل عبر الوكلاء |

---

## 2. جدول الـ mapping الكامل

**مفتاح عمود mapping:**
- `same`: نسخ 1:1 مع الاحتفاظ بالـ ids.
- `transform`: يحتاج تحويلًا.
- `missing-in-saas`: الجدول موجود في `main` فقط.
- `new-only-in-saas`: الجدول موجود في الـ SaaS فقط.
- `drop`: لا يُنقل.

### 2.1 المبيعات والعملاء
| `main` | tenant | mapping | ملاحظات الأعمدة | الخطر |
|---|---|---|---|---|
| `customers` | `customers` | transform | `price_tier` = `'retail'` (العمود غير موجود في `main`). `current_balance` يُنقل **ولا يُعتمد** كرصيد (راجع §3.1) | **عالٍ**: الرصيد الافتتاحي يُمسح عند أول `updateBalance()` |
| `invoices` | `invoices` | transform | `client_uuid` = `NULL` و`change_amount` = `0.000`. تُنقل `paid_amount` و`remaining_amount` و`payment_status` **كما هي**. `store_id` mapping صارم، و`NULL` = خطأ | **عالٍ**: تسوية FIFO وPR #3، وفي الـ SaaS `updateInvoice` و`deleteInvoice` يحذفان الإيصالات |
| `invoice_items` | `invoice_items` | same | أعمدة متطابقة | متوسط إلى عالٍ: `cost_price = 0` في سطور ما قبل `d08edc92` |
| `payments` | `payments` | same | `payment_number` **حرفيًا** (البادئات `PAY-INV-` و`PAY-CUST-` و`PAY-SUPP-` و`PAY-PUR-` و`PAY-EXP-` لها معنى). بلا `store_id` | **عالٍ** |
| `returns` | `returns` | same | `return_type` بقيمتين: `sales_return` و`purchase_return`. `store_id` mapping صارم | متوسط |
| `return_items` | `return_items` | transform | `cost_price` غير موجود في الـ SaaS فيحتاج migration. back-fill للصفوف القديمة التي قيمتها `NULL` | **عالٍ** على التكلفة |
| `quotations` | (لا يوجد) | missing-in-saas | migration + model جديدان. `converted_invoice_id` يُربط بعد الفواتير. `status` نص حر يُتحقق من قيمه الخمس | متوسط |
| `quotation_items` | (لا يوجد) | missing-in-saas | `price_tier` بثلاث قيم: `wholesale` و`retail` و`custom` | متوسط |
| `additional_expenses` | `additional_expenses` | same | polymorphic. `payment_id` يُربط بعد `payments` | منخفض إلى متوسط |
| كشف حساب العميل (مشتق) | مشتق | same | يُرتّب حسب `created_at`، فيجب الاحتفاظ به | متوسط |

### 2.2 المخزون والمشتريات
| `main` | tenant | mapping | ملاحظات | الخطر |
|---|---|---|---|---|
| `items` | `items` | transform | `min_selling_price` بقرار CTO (§8)، و**لا** نترك `ItemDTO` يضع فيه `cost_price` افتراضيًا. `category_id` من `DISTINCT items.category`. `image` و`pos_*` تأخذ الـ defaults. `weighted_avg_cost` بعد قرار الـ recost | **عالٍ** (WAC) |
| (نص حر `items.category`) | `categories` | new-only-in-saas | يُنشأ deterministic مع normalize (trim + توحيد المسافات). الـ icon محايد وليس `☕` | منخفض إلى متوسط |
| (نص حر `items.unit`) | `settings.inventory_units` | transform | `DISTINCT items.unit` يُضاف إلى `inventory_units` / `allowed_units` | متوسط |
| `stores` | `stores` | same | تحقق من وجود `is_main = true` **واحد بالضبط** | متوسط: حدود الباقة |
| `store_stocks` | `store_stocks` | same | لا تُنقل الصفوف المحذوفة soft-deleted كصفوف نشطة | **عالٍ**: احتمال `SUM ≠ current_stock` |
| `store_user` | `store_user` | same | بعد `users` و`stores` | منخفض |
| `stock_movements` | `stock_movements` | same | تُنقل كما هي، و**لا** نعيد بناء المخزون منها | **عالٍ**: فيها فجوات (حذف فعلي قبل `d08edc92`) |
| `stock_deposits` | `stock_deposits` | same | لا يوجد `store_id`، والمخزن يُعرف من الحركة المقابلة | **عالٍ**: أرصدة افتتاحية بتكلفة `0` |
| `stock_transfers` | `stock_transfers` | same | `status` نص حر: `pending` أو `confirmed` أو `cancelled` | منخفض |
| `stock_transfer_items` | `stock_transfer_items` | same | — | منخفض |
| `suppliers` | `suppliers` | transform | `current_balance` يُعاد حسابه ويُقارن، و**لا** يُعتمد كرصيد افتتاحي | **عالٍ** |
| `purchases` | `purchases` | same | `paid_amount` كما هو (فيه تسوية FIFO للموردين) | **عالٍ** |
| `purchase_items` | `purchase_items` | same | `base_cost_price` قيمته `NULL` في الصفوف القديمة | متوسط |
| `returns` (`purchase_return`) / `return_items` | نفسهما | كما في 2.1 | — | متوسط إلى عالٍ |
| (CoffeeBlender بلا جدول) | (Actions/Blends بلا جدول) | same | الفواتير فقط | متوسط: الـ SaaS يضع `paid_amount` = `0` في `cash` |

### 2.3 الخزينة والتشغيل والمستخدمون
| `main` | tenant | mapping | ملاحظات | الخطر |
|---|---|---|---|---|
| `cash_shifts` | `cash_shifts` | same | snapshots، **لا يُعاد حسابها**. كل الورديات مقفولة قبل الـ cutover | متوسط |
| `payments` (دفتر الخزينة) | `payments` | same | — | **عالٍ** |
| `treasury_transfers` | `treasury_transfers` | same | — | منخفض |
| `expenses` | `expenses` | same | `payment_method` يُطبَّع بخريطة يعتمدها المالك | متوسط |
| (لا يوجد `expense_categories`) | (لا يوجد) | same | لا تُخلط مع `categories` الخاصة بالأصناف | منخفض |
| (اليومية مشتقة) | `GetDailyJournalAction` | same | لا بيانات | — |
| `users` | `users` (tenant) | transform | `api_token` و`last_login_at` = `NULL`. الـ ids والـ hashes كما هي. **مع** المحذوفين soft-deleted. `default_store_id` يُتحقق منه | متوسط |
| `permissions` و`roles` و`model_has_*` و`role_has_permissions` | نفسها | same | خريطة أسماء بسبب اختلاف أسماء الصلاحيات (§3.6) | متوسط إلى عالٍ |
| `settings` | `settings` | transform | UPSERT by key، بلا الصفوف المحذوفة. الـ logo ملف منفصل | متوسط |
| `activity_logs` | `activity_logs` (tenant) | same | chunked، و**ليس** إلى الجدول المركزي | منخفض |
| `audit_logs` | `audit_logs` | same | حرفيًا، لأنها دليل الـ recost | منخفض |
| (لا يوجد) | `personal_access_tokens` | new-only-in-saas | يبدأ فارغًا، وكل الأجهزة تسجّل دخول من جديد | — |
| `sessions` و`password_reset_tokens` و`cache*` و`jobs` و`job_batches` و`failed_jobs` | — | drop | فرّغ queue الـ `main` قبل الـ cutover | — |
| `pulse_*` و`telescope_*` | (مركزي) | drop | قد تحتوي PII أو tokens، فلا تُنسخ أبدًا | — |
| (لا يوجد) | مركزي: `tenants` و`domains` و`plans` و`subscriptions`… | new-only-in-saas | تُنشأ عند الـ onboarding | حدود الباقة |

---

## 3. التحويلات المطلوبة بالتفصيل

### 3.1 أرصدة العملاء الافتتاحية (`customers.current_balance`)
- الـ `CustomerBalanceService` متطابق في الجهتين. `updateBalance()` يعيد حساب الرصيد = فواتير مؤكدة `net_total` − كل `payments.amount` − مرتجعات بيع `total_amount`. لذلك أي رصيد افتتاحي مكتوب مباشرة في `current_balance` **يُمسح** عند أول حركة.
- **التحويل:** لكل عميل نحسب بـ bcmath:
  `delta = current_balance − (Σ confirmed invoices.net_total − Σ payments.amount − Σ sales returns.total_amount)`
  مع استبعاد كل الصفوف المحذوفة soft-deleted.
  - إذا كان `delta = 0`: ننقل الرصيد كما هو.
  - إذا كان `delta ≠ 0`: يظهر في تقرير المالك، ويتحول (بقرار CTO) إلى **مستند رصيد افتتاحي صريح** يدخل في `updateBalance()` وفي الـ ledger. هذا المستند غير موجود اليوم، ويحتاج تصميمًا قبل الاستيراد.
- **ممنوع** نسخ `current_balance` نسخًا أعمى.

### 3.2 أرصدة الموردين
- السلوك نفسه عبر `SupplierBalanceService::updateBalance()`. الـ SaaS `CreateSupplierAction` يكتب `opening_balance` في `current_balance` بلا مستند، فيُمسح بنفس الطريقة.
- **Bug في الجهتين:** مصروف إضافي على المشتريات بـ `paid_by = treasury_*` ينشئ `PAY-EXP-*` بـ `supplier_id`. هذا المبلغ لا يدخل في `net_total`، لكنه يُطرح من رصيد المورد، فالأرصدة الحية **أقل من الحقيقة**. نحتاج قرارًا: إما إعادة إنتاج الـ bug لتتطابق الأرقام، أو تصحيحه مع تقرير فروق.

### 3.3 الفواتير والمدفوعات
- `paid_amount` و`remaining_amount` و`payment_status` تُنقل **حرفيًا**، ولا يُعاد اشتقاقها من `payments`، لأن تسوية FIFO في `main` (`e73cbd1c`) تجعل إيصالًا واحدًا (`invoice_id` فارغ أو مربوط بفاتورة واحدة) يسدد فواتير كثيرة.
- يُنقل `payment_number` حرفيًا ولا يُعاد ترقيمه. البادئة `PAY-INV-` هي ما يعتمد عليه PR #3 و`receiptSettledAmount()`.
- لا نحاول ملء `invoice_id` في الإيصالات الفارغة (back-fill).
- `client_uuid` = `NULL` و`change_amount` = `0.000`.
- الصفوف الملغاة والمحذوفة soft-deleted تُنقل مع `deleted_at` كما هو، ولا تُنقل أبدًا كصفوف حية.
- **تقارير اكتشاف قبل الاستيراد** (لا تصحيح آلي):
  - فواتير فيها `Σ PAY-INV + receiptSettled > net_total`. هذه آثار الـ bug قبل PR #3.
  - فواتير فيها `Σ PAY-INV > paid_amount`.
  - إيصالات محذوفة soft-deleted وفاتورتها ما زالت `confirmed`. قد تكون فلوسًا حقيقية حُذفت بالخطأ.
  - قيم `payment_method` خارج الـ enum. هذه **خطأ استيراد**.

### 3.4 التكلفة (WAC) وتكلفة السطور
- **`invoice_items.cost_price = 0`:** السطور قبل `d08edc92` قد تكون بتكلفة صفر، لأن `'0.000' ?: x` يعيد `'0.000'`. نتحقق هل شُغّل `inventory:recost --recost-invoices` على الحي. وإلا نشغّله dry-run على **نسخة**، ونعلّم السطور التي فيها `cost_price = 0`، والفواتير التي فيها `total_cost ≠ Σ(qty × cost_price)`.
- **`items.weighted_avg_cost`:** قرار الـ recost (§8). الـ SaaS لا يملك أمر `inventory:recost`.
- **`return_items.cost_price`:** نضيف migration محمية (guarded) في الـ tenant، ونضيفه إلى fillable والـ cast.
  - back-fill للصفوف التي قيمتها `NULL` بهذا الترتيب: (1) `invoice_items.cost_price` بالمفتاح `(invoice_id, item_id)` لمرتجع البيع، أو `purchase_items.cost_price` لمرتجع الشراء. (2) وإلا `stock_movements.unit_cost` من نفس الـ source. (3) وإلا تبقى `NULL` مع flag في التقرير.
- **`stock_deposits` الافتتاحية بتكلفة صفر:** الشرط `deposit_type = 'opening_balance' AND cost_price = 0`. تُحل مع المالك قبل استيراد الـ WAC.
- **`purchase_items.base_cost_price` بقيمة `NULL`:** يُملأ بـ `cost_price` (back-fill)، أو يبقى `NULL` والتقارير تتحمله (§8).
- **ملحوظة:** معنى `purchase_items.cost_price` و`stock_movements.unit_cost` يتغير عند تاريخ نشر `d08edc92`. ننقلهما كما هما ولا نصححهما.

### 3.5 المخزون
- تُنقل `items.current_stock` و`store_stocks` و`stock_movements` كما هي. **لا** نعيد بناء المخزون من الحركات، لأن فيها فجوات بسبب الحذف الفعلي قبل الإصلاح.
- `store_stocks` المحذوفة soft-deleted لا تُنقل كصفوف نشطة. الـ unique `(store_id, item_id)` لا يشمل `deleted_at`، فنقرر هل ننقلها محذوفة أم نتجاهلها (الأفضل نتجاهلها، ونسجلها في التقرير).
- `categories`: من `DISTINCT TRIM(items.category)` مع normalize. ثم نضع `items.category_id` داخل المستورد نفسه، ولا نعتمد على الـ GET endpoints التي تكتب في الـ DB كأثر جانبي.
- `inventory_units`: نجمع `DISTINCT items.unit` ونضيفه إلى إعداد المستأجر وإلى `allowed_units` المركزي.
- الـ `stores` تُراجع على حدود الباقة (`main_warehouse` و`wholesale_van`)، وفيها `is_main` واحد فقط.

### 3.6 المستخدمون والصلاحيات
- تُنقل `users` بالـ ids والـ bcrypt hashes كما هي (لا تعتمد على `APP_KEY`)، مع المحذوفين soft-deleted لأن عليهم FKs بـ `restrict`/`cascade`.
- **خريطة أسماء الصلاحيات:** كود الـ SaaS يفحص أسماء لا يزرعها أي seeder، منها `items.manage` و`settings.manage` و`users.manage` و`pos.sell` و`expenses.view` و`purchases.manage` و`returns.view` و`returns.create` و`stores.view` و`system.manage`، ويتجاهل `daily_journal.close_shift`. المطلوب أحد اثنين قبل الـ cutover:
  - (أ) إصلاح الفحوص في الكود لتستخدم الأسماء المزروعة (الأفضل).
  - (ب) خريطة `main name → SaaS names` في المستورد.

  وفي الحالتين نضيف `daily_journal.close_shift` إلى `Open/CloseShiftRequest`.
- اسم الدور `admin` يبقى حرفيًا (عليه `hasRole('admin')` bypass).
- بعد الاستيراد: `permission:cache-reset`.
- **تعارض الهاتف مع الأدمن المركزي:** `ApiLoginAction` يرجع للمستخدم المركزي ثم ينفّذ `firstOrCreate` بالهاتف. يجب فحص تعارض أرقام هواتف الأدمن المركزي مع مستخدمي المحل، ومنهم المحذوفون.

### 3.7 الإعدادات والـ logo
- `settings` تُنقل بـ UPSERT by key، لأن الـ tenant migration تزرع defaults. لا تُنقل الصفوف المحذوفة.
- `telegram_bot_token` سر محفوظ في الـ DB: يُنقل بلا طباعة وبلا log، ولا يظهر في أي تقرير.
- الـ logo ملف في `main` (`public/logo.png`). الـ SaaS يقدّم الـ logo من `public/` مشترك غير معزول بالمستأجر، وهذا **bug عزل** يُصلح قبل المستأجر الثاني (Phase 1). يُنقل الملف إلى مسار tenant-scoped.
- تنبيهات Telegram: نتأكد أن نظامًا **واحدًا** فقط يرسلها بعد الـ cutover.

### 3.8 المصروفات والخزينة
- `SELECT DISTINCT payment_method` على `payments` و`expenses` و`invoices`. الخزينة تحسب `cash` و`instapay` و`e_wallet` فقط، وأي قيمة غيرها تسقط من الأرصدة بصمت. نطبّعها بخريطة يعتمدها المالك.
- **outflow مزدوج:** صفوف `Expense` التي أنشأها `002daf59` لمصروفات إضافية مدفوعة من الخزينة، مع `PAY-EXP` المقابل. الخزينة تطرح الاثنين، فتُطابق ويُقرَّر بشأنها.
- لا نعيد حساب `cash_shifts`، ونقفل كل الورديات المفتوحة على `main` قبل الـ cutover (الـ SaaS يضيف `opening_cash_balance` للوردية المفتوحة).
- `payment_date` و`created_at` و`expense_date` تُنقل حرفيًا بلا تحويل timezone (`Africa/Cairo` في الجهتين).

### 3.9 عروض الأسعار
- نضيف tenant migration بنفس أعمدة `main` (`2026_09_04_000001`)، وmodel `Quotation` و`QuotationItem`.
- تُنقل بعد `invoices`، ثم يُربط `converted_invoice_id`.
- `status` يُتحقق من قيمه الخمس: `draft` و`sent` و`converted` و`expired` و`cancelled`.
- مصدر سعر الجملة: نفحص `SHOW COLUMNS FROM items` على الحي. لو `wholesale_price` غير موجود، فكل أسعار «جملة» التاريخية هي `selling_price` فعلًا، ولا شيء ينقل إلى `min_selling_price`.

---

## 4. أداة النقل المقترحة

### 4.1 الشكل
```
php artisan tenant:import-main-dump {tenant} {--dump=path.sql} [--commit] [--only=customers,invoices,...] [--chunk=1000] [--report=path]
```
- **`--dry-run` هو الافتراضي.** بدون `--commit` يعمل كل شيء داخل transaction يُلغى في النهاية (rollback)، أو على staging DB، ويخرج التقارير فقط.
- **المصدر:** dump من `origin/main` الحي. يُحمَّل في **قاعدة staging مؤقتة منفصلة** (`import_src_<tenant>`)، ولا يُحمَّل أبدًا في الـ tenant DB مباشرة. القراءة من الـ staging DB عبر connection مؤقت read-only.
- **الهدف:** tenant DB عبر `tenancy()->initialize($tenant)`. يرفض العمل على tenant فيه بيانات تشغيلية (فواتير أو مدفوعات) إلا مع `--resume`.
- **مكانه:** `app/Console/Commands/ImportMainDumpCommand.php` (رفيع)، و`app/Actions/Migration/Import<Table>Action.php` لكل مجموعة، و`app/Services/Migration/MainImportReconciler.php`. التقارير تُكتب في `storage/app/tenants/<id>/import/` (ليست في الـ repo، وبلا أسرار).
- **حماية:** يرفض العمل في `production` إلا مع `--force` وتأكيد مكتوب، مثل guard أوامر الـ populate في Phase 0.

### 4.2 المبادئ
- **ترتيب الاستيراد (FK-safe):**
  1. `users` (مع المحذوفين)
  2. `stores`
  3. `store_user`
  4. `permissions` و`roles` والـ pivots
  5. `settings` (upsert)
  6. `categories` (مشتق)
  7. `items`
  8. `store_stocks`
  9. `customers`
  10. `suppliers`
  11. `purchases` و`purchase_items`
  12. `invoices` و`invoice_items`
  13. `payments`
  14. `additional_expenses` (remap `payment_id` و`document_id`)
  15. `returns` و`return_items`
  16. `quotations` و`quotation_items` (ثم `converted_invoice_id`)
  17. `stock_deposits`
  18. `stock_transfers` و`stock_transfer_items`
  19. `stock_movements`
  20. `cash_shifts`
  21. `expenses`
  22. `treasury_transfers`
  23. `activity_logs`
  24. `audit_logs`
- **الاحتفاظ بالـ primary keys** لأن الـ tenant جديد وفارغ، فلا يلزم جدول mapping للـ ids. هذا ضروري لأن `source_id` و`document_id` و`subject_id` الـ polymorphic بلا FK. بعد كل جدول نضبط `AUTO_INCREMENT` على `MAX(id)+1`.
- **Idempotent:**
  - جدول `import_runs` (tenant) يسجل المرحلة والـ checksum والعدد.
  - كل إدخال `INSERT ... ON DUPLICATE KEY UPDATE` بالمفتاح الأصلي (`id`)، أو تخطٍّ لو الصف موجود بنفس الـ hash.
  - التشغيل مرتين = نفس النتيجة.
- **Chunked:** `chunkById(1000)` على المصدر، وtransaction لكل chunk مع checkpoint في `import_runs`، ليمكن الاستئناف بـ `--resume`. الجداول الكبيرة (`activity_logs` و`stock_movements` و`audit_logs`) تُعالج بنفس الطريقة.
- **bcmath فقط:** كل القيم المالية والكميات تُقرأ كـ string (PDO بلا `ATTR_EMULATE`/casts إلى float)، وكل جمع أو مقارنة بـ `bcadd` و`bcsub` و`bccomp` على scale 3. لا `float` ولا `round()`.
- **بلا Observers ولا Services أثناء الإدخال:** الإدخال يكون بـ query builder (`DB::table()->insert`) حتى لا تُطلق observers أو `updateBalance()` أو `StockService` أو activity logs جديدة. الحسابات تتم بعد الاستيراد في المطابقة فقط.
- **الحفاظ على:** `created_at` و`updated_at` و`deleted_at` و`payment_number` و`invoice_number` وكل التواريخ حرفيًا.
- **Validation صارم (fail-fast):**
  - `store_id` = `NULL` على `invoices` أو `returns`.
  - `store_id` يتيم بلا FK على `expenses` أو `cash_shifts` أو `stock_movements`.
  - `payment_method` خارج الـ enum بعد تطبيق الخريطة المعتمدة.
  - `status` خارج القيم المعروفة.
  - ورديات مفتوحة.

  أي من هذه = **فشل** مع تقرير، وليس تصحيحًا صامتًا.
- **ممنوع تصحيح البيانات المالية آليًا.** كل الشذوذ يذهب إلى تقرير `anomalies.csv` لقرار المالك. القرارات المعتمدة تُطبَّق عبر ملف `decisions.json` صريح يُمرَّر للأمر.
- **Tests:** dump fixture صغير يغطي: FIFO receipt، وPAY-INV مكرر، وفاتورة محذوفة، ومرتجع بلا `cost_price`، ورصيد افتتاحي `delta ≠ 0`، وتشغيل مرتين (idempotency)، و`dry-run` لا يكتب شيئًا، وعزل tenant (الاستيراد لا يلمس tenant آخر ولا الـ central).

---

## 5. خطة المطابقة 100%

أمر مستقل: `php artisan tenant:reconcile-import {tenant} --source=import_src_<tenant>`. يقارن **المصدر** (staging من الـ dump) مع **الهدف** (tenant DB)، ويخرج `reconciliation.csv` + exit code غير صفري عند أي فرق غير معتمد. كل المقارنات بـ `bccomp` على scale 3، و**الفرق المسموح = 0.000** إلا ما يُعتمد صراحة في `decisions.json`.

| # | المحور | الفحص |
|---|---|---|
| R1 | **عدد الصفوف** | لكل جدول: `COUNT(*)` و`COUNT(deleted_at IS NOT NULL)` متطابقة |
| R2 | **أرصدة العملاء** | لكل عميل: `current_balance` المصدر = الهدف. وكذلك `recompute(target)` + رصيد افتتاحي معتمد = `current_balance` المصدر. وإقفال كشف الحساب (`getCustomerLedger`) متطابق في الجهتين |
| R3 | **أرصدة الموردين** | نفس R2 عبر `SupplierBalanceService`، مع المعالجة المعتمدة لـ `PAY-EXP` |
| R4 | **ذمم الفواتير** | لكل عميل: `Σ remaining_amount` (confirmed) مقابل `current_balance` + المرتجعات ± الـ delta الافتتاحي. ولكل فاتورة: `paid + remaining = net_total`، و`payment_status` متسق |
| R5 | **المخزون لكل صنف** | `items.current_stock` المصدر = الهدف |
| R6 | **المخزون لكل صنف ومخزن** | `store_stocks.quantity` لكل `(store_id, item_id)` المصدر = الهدف. ويُبلَّغ (ولا يفشل) عن `current_stock ≠ Σ store_stocks` و`last stock_after ≠ current_stock`، لأنهما فروق موروثة من المصدر ومعروفة |
| R7 | **التكلفة** | `weighted_avg_cost` و`cost_price` لكل صنف = القيم المعتمدة (الأصلية أو بعد الـ recost حسب القرار) |
| R8 | **الخزينة** | `TreasuryService::getBalances()` لكل طريقة دفع (`cash` و`instapay` و`e_wallet`) ولكل store = نفس الحساب على المصدر (نفس الكود متطابق، فنشغّل نسخة الحساب على الـ staging DB) |
| R9 | **الفواتير** | العدد والمجاميع لكل (شهر × `status` × `payment_type` × `store_id`): `Σ subtotal` و`discount_amount` و`net_total` و`paid_amount` و`remaining_amount` و`total_cost` و`shipping_cost` |
| R10 | **المشتريات والمرتجعات والمصروفات** | نفس R9 لـ `purchases` و`returns` (حسب النوع) و`expenses` (حسب `category` و`cost_center`) و`treasury_transfers` |
| R11 | **المدفوعات** | `Σ amount` لكل بادئة (`PAY-INV` و`PAY-CUST` و`PAY-SUPP` و`PAY-PUR` و`PAY-EXP`) × `payment_method` × شهر |
| R12 | **الأرباح** | تقرير الربح (`ProfitLossService`) لكل شهر و**لكل سنة كاملة** = المصدر. **شرط:** نقل `d08edc92` إلى الـ SaaS (إصلاح `?:`) قبله، وإلا فالفرق متوقع وليس فشلًا في الاستيراد |
| R13 | **الورديات** | الـ snapshots المخزنة متطابقة حرفيًا (لا إعادة حساب) |
| R14 | **الصلاحيات** | لكل مستخدم: مجموعة الصلاحيات الفعلية بعد الخريطة = المعتمدة. واختبار دخول عيّنة من كل دور |
| R15 | **Checksums** | hash لكل جدول على الأعمدة المنقولة حرفيًا (بالترتيب حسب `id`) |

**معيار القبول:** R1 إلى R15 كلها خضراء، أو كل فرق موثق ومعتمد بالاسم في `decisions.json` وموقّع من المالك/الـ CTO.

---

## 6. البروفة على staging، والـ cutover، والرجوع

### 6.1 المتطلبات المسبقة
1. نقل إصلاحات `main` (Phase 2، §15): `d08edc92` وPR #3 و`e73cbd1c` وتوزيع الخصم وguard الـ restore، مع اختبارات.
2. الـ migrations المحمية (§3) وتصميم الرصيد الافتتاحي وعروض الأسعار.
3. إصلاح خريطة الصلاحيات (§3.6) وعزل الـ logo (§3.7).
4. اختيار الباقة وإنشاء الـ tenant في الـ central (`tenants` و`domains` و`subscriptions`).

### 6.2 البروفة (مرتين على الأقل، على staging أو VPS البروفات)
1. نأخذ backup/dump من الحي. **المالك أو الـ CTO هو من ينفّذه أو يعتمده**، ولا يلمس أي وكيل الخادم الحي.
2. نحمّله في `import_src_*` على staging.
3. pre-flight audits، ثم تقرير الشذوذ إلى المالك، ثم `decisions.json`.
4. `import --dry-run`، ثم `--commit`، ثم `reconcile`.
5. اختبار يدوي بأدوار حقيقية:
   - كشف حساب 10 عملاء كبار.
   - تقرير الخزينة واليومية.
   - تعديل فاتورة مستوردة بالآجل ومسددة بإيصال (يجب **ألا** يتكرر الدفع).
   - إلغاء مشتريات (يجب أن ينعكس الـ WAC).
   - POS على Android وElectron.
6. نسجل المدة الفعلية للاستيراد، فهي تحدد نافذة التوقف.
7. نكرر البروفة على dump أحدث قبل الـ cutover بأيام.

### 6.3 الـ cutover (نافذة خارج ساعات العمل)
1. إعلان التوقف، ثم إقفال كل الورديات المفتوحة، ثم وضع `main` في maintenance mode (قراءة فقط).
2. تفريغ queue الـ `main`، وإيقاف scheduler و**Telegram** على `main`.
3. dump نهائي + backup مشفر (Google Drive) + checksum.
4. import `--commit`، ثم reconcile. **لا ننتقل للخطوة التالية إلا وكل الفحوص خضراء.**
5. نقل الـ logo، و`permission:cache-reset` ومسح كاش الإعدادات، وتفعيل الـ scheduler و Telegram على الـ SaaS.
6. تحويل الأجهزة: تسجيل دخول جديد على Android وElectron برمز المحل، لأن الـ tokens تبدأ فارغة.
7. أول يوم: مطابقة إقفال الوردية الأولى يدويًا مع المالك.

### 6.4 الرجوع (rollback)
- **نقطة اللاعودة:** أول فاتورة حقيقية على الـ SaaS. قبلها يكون الرجوع = إلغاء maintenance mode على `main`، لأن `main` لم يتغير ولم يُحذف منه شيء.
- **بعدها** (خلال نافذة المراقبة، مثل 72 ساعة): يبقى `main` و DB الخاصة به **read-only محفوظة كما هي**. الرجوع = تصدير المستندات الجديدة من الـ tenant (فواتير ومدفوعات ومصروفات بعد وقت الـ cutover) وإدخالها يدويًا أو بأمر عكسي محدود في `main`. هذا مكلف، فلازم نقلل نافذة المراقبة ونقرر الرجوع بسرعة.
- لا نحذف DB الـ `main` ولا نعيد استخدامها قبل اعتماد مكتوب من الـ CTO بعد فترة الاستقرار.

---

## 7. المخاطر

| # | الخطر | الأثر | التخفيف |
|---|---|---|---|
| 1 | الاستيراد قبل نقل PR #3: الـ SaaS `updateInvoice` و`deleteInvoice` يحذفان الإيصالات ويعيدان إنشاء `paid_amount` كاملًا | تحصيل مزدوج، ورصيد دائن وهمي، وخزينة منفوخة | شرط مسبق صارم، أو منع تعديل الفواتير المستوردة التي لها إيصالات منفصلة |
| 2 | غياب FIFO في الـ SaaS `PaymentService` | سلوك مختلف بعد الاستيراد، والأرصدة تتغير عند أول إيصال | نقل `e73cbd1c` |
| 3 | WAC: الـ fallback `?:` و`ReturnService` و`depositStock` بلا blending، وإلغاء المشتريات لا يعكس | أرباح وتقييم خاطئ، وانحراف يتراكم | نقل `d08edc92` + قرار الـ recost |
| 4 | الأرصدة الافتتاحية تُمسح | فرق صامت في الأرصدة | §3.1 و§3.2 + مستند افتتاحي |
| 5 | فجوات `stock_movements` | المخزون لا يُعاد بناؤه من الحركات | ننقل الأرصدة كما هي ونبلّغ عن الفروق |
| 6 | `store_id` بقيمة `NULL` أو يتيم | صفوف مخفية أو مسرّبة بسبب الـ store scoping | fail-fast |
| 7 | `payment_method` خارج `activeMethods` | فلوس تسقط من الخزينة | خريطة تطبيع معتمدة |
| 8 | outflow مزدوج (`Expense` + `PAY-EXP`) وbug `PAY-EXP` على المورد | خزينة وموردون غير دقيقين | تقرير + قرار |
| 9 | فروق أسماء الصلاحيات | أدوار تفقد صلاحيات أو تكسب صلاحيات (فتح/إقفال الوردية) | إصلاح الفحوص أو خريطة |
| 10 | `customers.price_tier` مُضاف داخل migration منشورة (`d52ffe14`) | tenants قديمة بلا العمود | migration محمية جديدة |
| 11 | الـ logo من `public/` مشترك | تسريب branding بين المستأجرين | عزل قبل المستأجر الثاني |
| 12 | تعارض هاتف أدمن مركزي مع مستخدم المحل، ودخول أدمن مركزي إلى المحل | فشل دخول أو وصول غير مقصود | فحص قبلي + قرار |
| 13 | حدود الباقة (فروع ومخازن وسيارات ومستخدمون وأصناف) | رفض عمليات بعد تفعيل الحدود في Phase 1 | اختيار باقة مناسبة أو override |
| 14 | الـ blender في الـ SaaS يضع `paid_amount` = `0` في `cash` | دين وهمي على عميل | إصلاح قبل الـ cutover |
| 15 | تنبيهات Telegram مكررة، أو jobs معلّقة على `main` | إزعاج أو كتابة على DB قديمة | إيقاف الـ scheduler والـ queue على `main` |
| 16 | `CreateItemAction` يولّد الكود من `count()+1` | تعارض unique مع الأكواد المستوردة | توليد الكود من `MAX` أو sequence |
| 17 | `ShiftService` يعدّ مبيعات العميل النقدية مرتين (في الجهتين) | أرقام ورديات جديدة مختلفة عن المتوقع | الـ snapshots القديمة كما هي + إصلاح لاحق |
| 18 | ترقيم الورديات والمصروفات من عدّ يومي بلا lock، و`TreasuryService::transfer` بلا `lockForUpdate` | تعارض أرقام أو سحب زائد عند التزامن | ديون تقنية لـ Phase 2 |
| 19 | dump الحي يحتوي PII وسر Telegram | تسريب | الـ staging DB تُحذف بعد البروفة، والتقارير بلا أسرار، وTelescope/Pulse لا تُنقل |

---

## 8. قرارات مطلوبة من الـ CTO

1. **الأرصدة الافتتاحية:** هل ننشئ مستندًا صريحًا (جدول `opening_balances` أو نوع مستند) للعملاء والموردين ونحوّل إليه كل `delta ≠ 0`؟ أم نعتمد الرصيد الحي ونقبل الفرق في كشف الحساب؟ (التوصية: مستند صريح.)
2. **الـ WAC التاريخي:** هل شُغّل `inventory:recost --recost-invoices` على الحي؟ ولو لم يُشغَّل: هل ننقل القيم الأصلية، أم القيم بعد recost على نسخة؟ وماذا عن سطور الفواتير التي `cost_price = 0` (تُصحَّح تاريخيًا أو تبقى)؟
3. **back-fill `return_items.cost_price`** للصفوف القديمة: بتكلفة البيع الأصلية، أم بالحركة، أم `NULL`؟
4. **bug `PAY-EXP` على رصيد المورد:** نعيد إنتاجه للتطابق، أم نصححه مع تقرير فروق للمالك؟
5. **outflow المزدوج** (`Expense` + `PAY-EXP`): أيهما يبقى؟
6. **خريطة `payment_method`** للقيم غير المعروفة (في `expenses` و`payments` و`invoices`).
7. **عروض الأسعار:** feature كاملة قبل الترحيل، أم جدول + model للقراءة والأرشيف فقط، أم تصدير أرشيفي (CSV/PDF) بلا جدول؟
8. **`items.min_selling_price`:** `0.000` أم `selling_price`؟ ومصدر سعر الجملة بعد فحص أعمدة `items` الحية.
9. **`customers.price_tier`:** الكل `retail`، أم يحدد المالك عملاء الجملة يدويًا (مع اقتراح من `quotations.pricing_tier`)؟
10. **الصلاحيات:** إصلاح الفحوص في الكود لتطابق الأسماء المزروعة (موصى به)، أم خريطة أسماء في المستورد؟ وإعادة ربط `daily_journal.close_shift`.
11. **الباقة** التي يدخل بها المحل، ومعاملة حدودها (override دائم أم Founder).
12. **مكان الاستضافة:** الـ VPS الجديد (Hetzner) أم الخادم الحالي؟ وتوقيت الـ cutover ونافذة المراقبة ومدة الاحتفاظ بـ `main` read-only.
13. **الأدمن المركزي:** هل مقبول أن يدخل أي أدمن مركزي إلى tenant المحل عبر fallback الـ `ApiLoginAction`؟ (يرتبط بقرار impersonation المدقق في Phase 1a.)
14. **صفوف `store_stocks` المحذوفة soft-deleted** و`purchase_items.base_cost_price` بقيمة `NULL`: نتجاهلها أم نملأها (back-fill)؟
15. **من ينفّذ الـ dump من الحي:** المالك أو الـ CTO يدويًا، فالوكلاء ممنوعون من لمس الخادم الحي.

---

## ملاحظات legacy لوحظت (لم تُعالج هنا)

- الـ SaaS `ReturnItem` بلا `SoftDeletes` رغم وجود العمود.
- نصوص عربية hardcoded في `CustomerBalanceService` و`PurchaseService` و`StockService` و`TreasuryService` والـ models.
- تحويل الفلوس إلى `float` في `ItemResource` و`GetPOSBootstrapDataAction` و`getSupplierLedger` و`GetDailyJournalAction`.
- `Auth::id() ?? 1` في عدة Services.
- endpoints الـ categories من نوع GET تكتب في الـ DB.
- `PopulateRealisticTenantDataCommand` يمرر `price_wholesale` غير الموجود في fillable.
- `ItemDTO` يضع `min_selling_price` افتراضيًا = `cost_price`.
- `payments` بلا `store_id`، فأرقام الخزينة والورديات لكل فرع تخلط الفروع.
