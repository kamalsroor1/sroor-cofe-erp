# تحليل ترحيل المحل الحالي (`main`) إلى الـ SaaS (tenant)

> **الحالة:** تحليل وتخطيط فقط، ولم يتغيّر أي كود. التاريخ: 2026-10-08.
> **المرجع:** قرارات الـ CTO في [`product-overview.md`](../01-overview/product-overview.md)، وخاصة §15 (نقل إصلاحات `main`) و§16 (ترحيل المحل) و§18 (المراحل).
> **مصدر المقارنة:** قرأنا `main` بـ `git show` و`git ls-tree` فقط، من غير checkout.
> **تنبيه مهم:** الـ ref المحلي `main` (`6c43161f`) **قديم**. الفرع الذي يعمل عليه المحل فعلًا هو **`origin/main` (`930250b4`)**، وفيه `d08edc92` (إصلاح WAC، PR #2) و`a04be5a7` (إصلاح الدفع المكرر، PR #3)، وفيه أيضًا migration `2026_09_29_000001_add_cost_price_to_return_items_table`. لذلك يجب أن تقرأ كل أدوات الاستيراد والتحليل من `origin/main`.
> لا تحتوي هذه الوثيقة على أسرار أو عناوين خوادم أو بيانات عملاء.
> **[CTO-2026-10-09] تحديث:** الـ CTO جاوب قرارات §8 كلها (القرار 15 جزئيًا). الأقسام المتأثرة اتعدلت ومتعلّمة بنفس الوسم. باقي 3 نقاط مفتوحة: مين ياخد الـ dump، وإتاحة عروض الأسعار حسب الباقة، وهل المحل بيستخدم الورديات (تفاصيلها في §8).

---

## 1. الملخص وحجم الشغل

### الخلاصة
- **الـ schema متطابق تقريبًا.** الـ migrations الخاصة بـ `invoices` و`invoice_items` و`payments` و`returns` و`additional_expenses` و`items` و`stores` و`store_stocks` و`stock_*` و`purchases*` و`suppliers` و`cash_shifts` و`expenses` و`treasury_transfers` و`users` و`settings` و`activity_logs` و`audit_logs` وجداول الصلاحيات متطابقة byte-by-byte، باستثناء المسافات (whitespace). كل أعمدة الفلوس والكميات `DECIMAL(12,3)` في الجهتين، فلا نحتاج أي تحويل للدقة. أسماء الـ models واحدة كذلك، فقيم `source_type` و`document_type` و`model_type` الـ polymorphic تنتقل 1:1.
- **فروق الأعمدة قليلة:**
  - في الـ SaaS فقط: `customers.price_tier` و`invoices.client_uuid` و`invoices.change_amount` و`users.api_token` و`users.last_login_at`، و`items.min_selling_price` و`category_id` و`image` و`pos_*`، وجدول `categories`.
  - في `main` فقط: `return_items.cost_price` وجدولا `quotations` و`quotation_items`.
- **الخطر الحقيقي في السلوك، لا في الشكل.** البيانات الحية كتبها كود `main` بعد إصلاحه (WAC وPR #3 وتسوية FIFO)، أما الـ SaaS فما زال يشغّل الكود القديم قبل الإصلاح. لو استوردنا قبل نقل الإصلاحات، فأول تعديل لفاتورة مستوردة سيحصّل الفلوس مرتين، وأول إلغاء لمشتريات سيعيد إفساد الـ WAC.
- **الترحيل يكون بالتاريخ كاملًا لا بأرصدة افتتاحية.** الخزينة وأرصدة العملاء والموردين **مشتقة** من المستندات، ولا يوجد جدول أرصدة افتتاحية للخزينة أو للعملاء أو للموردين. لذلك يجب نقل كل التاريخ مع الاحتفاظ بالـ ids والـ timestamps كما هي. **[CTO-2026-10-09]** الفرق الوحيد اللي هيتحول لمستند: أي `delta ≠ 0` في رصيد عميل أو مورد يبقى **مستند رصيد افتتاحي** صريح (§3.1).

### التقدير (أيام-مطور، بتقدير بشري)
| البند | أيام | ملاحظة |
|---|---|---|
| **شرط مسبق:** نقل إصلاحات `main` (`d08edc92` وPR #3 و`e73cbd1c` FIFO وتوزيع الخصم وguard الـ restore) مع اختبارات | (8–12) | **ضمن Phase 2** حسب §15، و**غير محسوب** في المجموع أدناه |
| **[CTO-2026-10-09] شرط مسبق:** feature عروض الأسعار كاملة في الـ SaaS (Q7: إنشاء/تعديل/طباعة، تحويل لفاتورة، انتهاء صلاحية، 5 حالات) | (≈ 2–3 أيام وكلاء) | **Phase 2 قبل الترحيل** (أو آخر 1a)، **غير محسوب** في المجموع. التقدير البشري ماتعملش |
| **[CTO-2026-10-09] شرط مسبق:** إصلاح bug `PAY-EXP` على رصيد المورد في كود الـ SaaS (Q4) | (ضمن Phase 2) | غير محسوب |
| tenant migrations محمية (guarded): `return_items.cost_price` و`customers.price_tier` (إصلاح)، مع تعديلات الـ models (`SoftDeletes` على `ReturnItem`). `quotations*` بقت جزء من الـ feature (شرط مسبق) | 2 | |
| مستند «رصيد افتتاحي» للعملاء والموردين ودمجه في `CustomerBalanceService` و`SupplierBalanceService` والـ ledger | 3 | **[CTO-2026-10-09]** Q1: مستند صريح، يتسلّم في Phase 2 لكل المستأجرين، والمستورد بيستخدمه |
| ~~حد أدنى لعروض الأسعار في الـ SaaS (model + قراءة/أرشيف)~~ | ~~1–4~~ | **[CTO-2026-10-09]** اتشال: Q7 اختار feature كاملة (فوق) |
| أمر الاستيراد `tenant:import-main-dump` (staging DB + mapping + chunking + idempotency + تقارير) | 6–8 | |
| تقارير ما قبل الاستيراد (pre-flight audits) | 2 | |
| **[CTO-2026-10-09]** تقارير الفروق اللي المالك بيراجعها (الأرصدة الافتتاحية، `PAY-EXP` لكل مورد، الخزينة، خريطة `payment_method`، المستخدمين اللي هيفقدوا صلاحيات) + التصحيح التاريخي لسطور التكلفة الصفرية من حركات الشراء | 2–3 | تقدير من التخطيط، ماتقاسش |
| أمر المطابقة `tenant:reconcile-import` + اختبارات | 4–5 | |
| Feature/Unit tests للمستورد (fixture dump صغير) | 2–3 | |
| بروفة staging (مرتين على الأقل) وإصلاح ما يظهر | 3–4 | |
| الـ cutover الفعلي والمتابعة بعده | 1–2 | **[CTO-2026-10-09]** الرقم ده للشغل الفعلي بس. المراقبة أسبوعين بعد الـ cutover (§6.4) |
| **المجموع (من غير الشروط المسبقة)** | ~~24–33~~ **≈ 25–32 يومًا** [CTO-2026-10-09] | ≈ 5–7 أسابيع بشريًا، وأقل بالعمل عبر الوكلاء. الحساب: 24–33 − عروض الأسعار (1–4) + تقارير الفروق (2–3) |

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
| `customers` | `customers` | transform | `price_tier` = `'retail'` للكل (**[CTO-2026-10-09]** Q9: المالك بيحدد عملاء الجملة بإيده بعد الترحيل، من غير اقتراح آلي). `current_balance` يُنقل **ولا يُعتمد** كرصيد، والفرق يبقى مستند رصيد افتتاحي (§3.1) | **عالٍ**: الرصيد الافتتاحي يُمسح عند أول `updateBalance()` |
| `invoices` | `invoices` | transform | `client_uuid` = `NULL` و`change_amount` = `0.000`. تُنقل `paid_amount` و`remaining_amount` و`payment_status` **كما هي**. `store_id` mapping صارم، و`NULL` = خطأ | **عالٍ**: تسوية FIFO وPR #3، وفي الـ SaaS `updateInvoice` و`deleteInvoice` يحذفان الإيصالات |
| `invoice_items` | `invoice_items` | same | أعمدة متطابقة | متوسط إلى عالٍ: `cost_price = 0` في سطور ما قبل `d08edc92` |
| `payments` | `payments` | same | `payment_number` **حرفيًا** (البادئات `PAY-INV-` و`PAY-CUST-` و`PAY-SUPP-` و`PAY-PUR-` و`PAY-EXP-` لها معنى). بلا `store_id` | **عالٍ** |
| `returns` | `returns` | same | `return_type` بقيمتين: `sales_return` و`purchase_return`. `store_id` mapping صارم | متوسط |
| `return_items` | `return_items` | transform | `cost_price` غير موجود في الـ SaaS فيحتاج migration. back-fill للصفوف القديمة التي قيمتها `NULL` بالترتيب المعتمد في §3.4 (**[CTO-2026-10-09]** Q3) | **عالٍ** على التكلفة |
| `quotations` | `quotations` (**[CTO-2026-10-09]** feature كاملة في Phase 2 قبل الترحيل، Q7) | missing-in-saas | `converted_invoice_id` يُربط بعد الفواتير. `status` نص حر يُتحقق من قيمه الخمس | متوسط |
| `quotation_items` | (لا يوجد) | missing-in-saas | `price_tier` بثلاث قيم: `wholesale` و`retail` و`custom` | متوسط |
| `additional_expenses` | `additional_expenses` | same | polymorphic. `payment_id` يُربط بعد `payments` | منخفض إلى متوسط |
| كشف حساب العميل (مشتق) | مشتق | same | يُرتّب حسب `created_at`، فيجب الاحتفاظ به | متوسط |

### 2.2 المخزون والمشتريات
| `main` | tenant | mapping | ملاحظات | الخطر |
|---|---|---|---|---|
| `items` | `items` | transform | `min_selling_price` = `NULL` (بلا أرضية، **[CTO-2026-10-09]** Q8)، والحماية من البيع تحت التكلفة عن طريق `pos.price.floor_policy` (SETG-9). **لا** نترك `ItemDTO` يضع فيه `cost_price` افتراضيًا. `category_id` من `DISTINCT items.category`. `image` و`pos_*` تأخذ الـ defaults. `weighted_avg_cost` يُنقل كما هو (Q2) | **عالٍ** (WAC) |
| (نص حر `items.category`) | `categories` | new-only-in-saas | يُنشأ deterministic مع normalize (trim + توحيد المسافات). الـ icon محايد وليس `☕` | منخفض إلى متوسط |
| (نص حر `items.unit`) | `settings.inventory_units` | transform | `DISTINCT items.unit` يُضاف إلى `inventory_units` / `allowed_units` | متوسط |
| `stores` | `stores` | same | تحقق من وجود `is_main = true` **واحد بالضبط** | متوسط: حدود الباقة |
| `store_stocks` | `store_stocks` | same | الصفوف المحذوفة soft-deleted تُنقل **كما هي محذوفة** (**[CTO-2026-10-09]** Q14)، ولا تُنقل أبدًا كصفوف نشطة. اللي رصيدها ≠ 0 تظهر في تقرير المطابقة | **عالٍ**: احتمال `SUM ≠ current_stock` |
| `store_user` | `store_user` | same | بعد `users` و`stores` | منخفض |
| `stock_movements` | `stock_movements` | same | تُنقل كما هي، و**لا** نعيد بناء المخزون منها | **عالٍ**: فيها فجوات (حذف فعلي قبل `d08edc92`) |
| `stock_deposits` | `stock_deposits` | same | لا يوجد `store_id`، والمخزن يُعرف من الحركة المقابلة | **عالٍ**: أرصدة افتتاحية بتكلفة `0` |
| `stock_transfers` | `stock_transfers` | same | `status` نص حر: `pending` أو `confirmed` أو `cancelled` | منخفض |
| `stock_transfer_items` | `stock_transfer_items` | same | — | منخفض |
| `suppliers` | `suppliers` | transform | `current_balance` يُعاد حسابه ويُقارن، و**لا** يُعتمد كرصيد افتتاحي | **عالٍ** |
| `purchases` | `purchases` | same | `paid_amount` كما هو (فيه تسوية FIFO للموردين) | **عالٍ** |
| `purchase_items` | `purchase_items` | transform | `base_cost_price` بقيمة `NULL` في الصفوف القديمة يتملا بـ `cost_price` بتاع نفس السطر (**[CTO-2026-10-09]** Q14) | متوسط |
| `returns` (`purchase_return`) / `return_items` | نفسهما | كما في 2.1 | — | متوسط إلى عالٍ |
| (CoffeeBlender بلا جدول) | (Actions/Blends بلا جدول) | same | الفواتير فقط | متوسط: الـ SaaS يضع `paid_amount` = `0` في `cash` |

### 2.3 الخزينة والتشغيل والمستخدمون
| `main` | tenant | mapping | ملاحظات | الخطر |
|---|---|---|---|---|
| `cash_shifts` | `cash_shifts` | same | snapshots، **لا يُعاد حسابها**. كل الورديات مقفولة قبل الـ cutover | متوسط |
| `payments` (دفتر الخزينة) | `payments` | same | — | **عالٍ** |
| `treasury_transfers` | `treasury_transfers` | same | — | منخفض |
| `expenses` | `expenses` | same | `payment_method` يُطبَّع بخريطة يعتمدها المالك (§3.8). **[CTO-2026-10-09]** صفوف `Expense` الخاصة بمصروفات المشتريات الإضافية تفضل، و`PAY-EXP` المكرر يتشال (Q5) | متوسط |
| (لا يوجد `expense_categories`) | (لا يوجد) | same | لا تُخلط مع `categories` الخاصة بالأصناف | منخفض |
| (اليومية مشتقة) | `GetDailyJournalAction` | same | لا بيانات | — |
| `users` | `users` (tenant) | transform | `api_token` و`last_login_at` = `NULL`. الـ ids والـ hashes كما هي. **مع** المحذوفين soft-deleted. `default_store_id` يُتحقق منه | متوسط |
| `permissions` و`roles` و`model_has_*` و`role_has_permissions` | نفسها | same | تُنقل كما هي. **[CTO-2026-10-09]** Q10: الكود يتصلح ليستخدم الأسماء المزروعة، فمفيش خريطة أسماء في المستورد (§3.6) | متوسط إلى عالٍ |
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
  - إذا كان `delta ≠ 0`: يتحول إلى **مستند رصيد افتتاحي صريح** يدخل في `updateBalance()` وفي الـ ledger.
- **[CTO-2026-10-09] قرار Q1:** مستند رصيد افتتاحي صريح للعملاء والموردين (قيد في الـ ledger). الرصيد = الافتتاحي + الحركات، و`updateBalance()` مايمسحهوش أبدًا. المستند ده يتصلح لكل المستأجرين في **Phase 2** (مش للترحيل بس). وقت الترحيل: كل `delta ≠ 0` يبقى مستند افتتاحي، والقائمة كلها تتراجع مع صاحب المحل قبل الـ cutover.
- **ممنوع** نسخ `current_balance` نسخًا أعمى.

### 3.2 أرصدة الموردين
- السلوك نفسه عبر `SupplierBalanceService::updateBalance()`. الـ SaaS `CreateSupplierAction` يكتب `opening_balance` في `current_balance` بلا مستند، فيُمسح بنفس الطريقة. **[CTO-2026-10-09]** نفس حل §3.1 (مستند رصيد افتتاحي).
- **Bug في الجهتين:** مصروف إضافي على المشتريات بـ `paid_by = treasury_*` ينشئ `PAY-EXP-*` بـ `supplier_id`. هذا المبلغ لا يدخل في `net_total`، لكنه يُطرح من رصيد المورد، فالأرصدة الحية **أقل من الحقيقة**.
- **[CTO-2026-10-09] قرار Q4: الـ bug يتصلح، مايتعادش إنتاجه.** المصروفات الإضافية المدفوعة من الخزينة تتنقل كمصروفات خزينة **من غير ربط بالمورد**. تقرير فروق لكل مورد (القديم / الصحيح / الفرق) يتراجع مع المالك قبل الـ cutover. والـ bug نفسه يتصلح في كود الـ SaaS في Phase 2. فحص R3 في §5 بيقارن بالرصيد **الصحيح** المعتمد في `decisions.json`.

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
- **`invoice_items.cost_price = 0`:** السطور قبل `d08edc92` قد تكون بتكلفة صفر، لأن `'0.000' ?: x` يعيد `'0.000'`. نعلّم السطور التي فيها `cost_price = 0`، والفواتير التي فيها `total_cost ≠ Σ(qty × cost_price)`.
- **[CTO-2026-10-09] قرار Q2:** قيم الـ WAC والتكلفة الحية تتنقل **كما هي**. دليل الـ backups بيقول إن الـ recost اتطبق على الحي يوم 2026-09-29، **بس الـ CTO ماأكدش ده صراحةً**. المستورد بيتحقق من كل سطر ويطلّع تقرير بالسطور اللي تكلفتها صفر. السطور الصفرية اللي فاضلة تتصحح تاريخيًا من حركات الشراء، مع تقرير فروق يتراجع مع المالك.
- **`items.weighted_avg_cost`:** يُنقل كما هو (Q2). الـ SaaS لا يملك أمر `inventory:recost`.
- **`return_items.cost_price`:** نضيف migration محمية (guarded) في الـ tenant، ونضيفه إلى fillable والـ cast.
  - **[CTO-2026-10-09] قرار Q3:** back-fill للصفوف التي قيمتها `NULL` بهذا الترتيب: (1) تكلفة سطر الفاتورة الأصلية (`invoice_items.cost_price` بالمفتاح `(invoice_id, item_id)` لمرتجع البيع، أو `purchase_items.cost_price` لمرتجع الشراء). (2) وإلا `stock_movements.unit_cost` من نفس الـ source. (3) وإلا WAC الصنف في التاريخ ده. كل صف اتملا بـ fallback (2) أو (3) يظهر في التقرير.
- **`stock_deposits` الافتتاحية بتكلفة صفر:** الشرط `deposit_type = 'opening_balance' AND cost_price = 0`. تُحل مع المالك قبل استيراد الـ WAC.
- **`purchase_items.base_cost_price` بقيمة `NULL`:** **[CTO-2026-10-09] قرار Q14:** يتملا بـ `cost_price` بتاع نفس السطر.
- **ملحوظة:** معنى `purchase_items.cost_price` و`stock_movements.unit_cost` يتغير عند تاريخ نشر `d08edc92`. ننقلهما كما هما ولا نصححهما.

### 3.5 المخزون
- تُنقل `items.current_stock` و`store_stocks` و`stock_movements` كما هي. **لا** نعيد بناء المخزون من الحركات، لأن فيها فجوات بسبب الحذف الفعلي قبل الإصلاح.
- `store_stocks` المحذوفة soft-deleted لا تُنقل كصفوف نشطة. **[CTO-2026-10-09] قرار Q14:** تُنقل كما هي (محذوفة)، واللي رصيدها ≠ 0 تتعلّم في تقرير المطابقة. الـ unique `(store_id, item_id)` لا يشمل `deleted_at`، لكن نفس الـ unique موجود في `main`، فبيانات المصدر نفسها مفيهاش تعارض.
- `categories`: من `DISTINCT TRIM(items.category)` مع normalize. ثم نضع `items.category_id` داخل المستورد نفسه، ولا نعتمد على الـ GET endpoints التي تكتب في الـ DB كأثر جانبي.
- `inventory_units`: نجمع `DISTINCT items.unit` ونضيفه إلى إعداد المستأجر وإلى `allowed_units` المركزي.
- الـ `stores` تُراجع على حدود الباقة (`main_warehouse` و`wholesale_van`)، وفيها `is_main` واحد فقط.

### 3.6 المستخدمون والصلاحيات
- تُنقل `users` بالـ ids والـ bcrypt hashes كما هي (لا تعتمد على `APP_KEY`)، مع المحذوفين soft-deleted لأن عليهم FKs بـ `restrict`/`cascade`.
- **خريطة أسماء الصلاحيات:** كود الـ SaaS يفحص أسماء لا يزرعها أي seeder، منها `items.manage` و`settings.manage` و`users.manage` و`pos.sell` و`expenses.view` و`purchases.manage` و`returns.view` و`returns.create` و`stores.view` و`system.manage`، ويتجاهل `daily_journal.close_shift`. كان المطلوب أحد اثنين قبل الـ cutover:
  - (أ) إصلاح الفحوص في الكود لتستخدم الأسماء المزروعة (الأفضل).
  - (ب) خريطة `main name → SaaS names` في المستورد.
- **[CTO-2026-10-09] قرار Q10: (أ).** الكود يتصلح ليستخدم الأسماء المزروعة فعلًا، وأي اسم ناقص يتزرع (QA-2 في W2، لكل المستأجرين). `daily_journal.close_shift` يتطبق على فتح وقفل الوردية. صلاحيات المحل تتنقل كما هي. قبل الـ cutover: تقرير بالمستخدمين اللي هيفقدوا صلاحية، عشان المالك يدّيها لهم.
- اسم الدور `admin` يبقى حرفيًا (عليه `hasRole('admin')` bypass).
- بعد الاستيراد: `permission:cache-reset`.
- **تعارض الهاتف مع الأدمن المركزي:** `ApiLoginAction` يرجع للمستخدم المركزي ثم ينفّذ `firstOrCreate` بالهاتف. يجب فحص تعارض أرقام هواتف الأدمن المركزي مع مستخدمي المحل، ومنهم المحذوفون. **[CTO-2026-10-09]** قرار Q13: الـ fallback المركزي ده بيتشال في Phase 1a (IDEN-2.1)، والدعم بيدخل عن طريق الـ impersonation المدققة (IDEN-2.5…2.12). الفحص يفضل مطلوب لو الترحيل حصل قبل دمج IDEN-2.1.

### 3.7 الإعدادات والـ logo
- `settings` تُنقل بـ UPSERT by key، لأن الـ tenant migration تزرع defaults. لا تُنقل الصفوف المحذوفة.
- `telegram_bot_token` سر محفوظ في الـ DB: يُنقل بلا طباعة وبلا log، ولا يظهر في أي تقرير.
- الـ logo ملف في `main` (`public/logo.png`). الـ SaaS يقدّم الـ logo من `public/` مشترك غير معزول بالمستأجر، وهذا **bug عزل** يُصلح قبل المستأجر الثاني (Phase 1). يُنقل الملف إلى مسار tenant-scoped.
- تنبيهات Telegram: نتأكد أن نظامًا **واحدًا** فقط يرسلها بعد الـ cutover.

### 3.8 المصروفات والخزينة
- `SELECT DISTINCT payment_method` على `payments` و`expenses` و`invoices`. الخزينة تحسب `cash` و`instapay` و`e_wallet` فقط، وأي قيمة غيرها تسقط من الأرصدة بصمت. نطبّعها بخريطة يعتمدها المالك.
- **[CTO-2026-10-09] قرار Q6:** Claude بيجهز خريطة مقترحة من القيم الـ `DISTINCT` (بالعدد والإجمالي لكل قيمة)، والمالك يعتمدها أو يعدلها قبل الترحيل. القيم المش واضحة تروح `other` وتظهر في تقرير. كل طريقة دفع تترتبط بخزينة (درج كاش، بنك، محفظة)، فالمبالغ اللي كانت بتسقط قبل كده تظهر. ومعاها تقرير فروق للخزينة. الخريطة بتطابق قائمة طرق الدفع الواحدة في SETG-8.
- **outflow مزدوج:** صفوف `Expense` التي أنشأها `002daf59` لمصروفات إضافية مدفوعة من الخزينة، مع `PAY-EXP` المقابل. الخزينة تطرح الاثنين.
- **[CTO-2026-10-09] قرار Q5:** سجل الـ `Expense` يفضل (تصنيف «مصاريف نقل/إضافية للمشتريات»)، وسند `PAY-EXP` المكرر **مايتنقلش**. تقرير مطابقة للخزينة (القديم / الجديد / الفرق) يتراجع مع المالك.
- لا نعيد حساب `cash_shifts`، ونقفل كل الورديات المفتوحة على `main` قبل الـ cutover (الـ SaaS يضيف `opening_cash_balance` للوردية المفتوحة). **سؤال مفتوح:** هل المحل بيستخدم الورديات فعلًا؟ الإجابة بتحدد قيمة `shift.required_to_sell` للمحل بعد الترحيل (المحل مالوش قالب نشاط، SETG-12).
- `payment_date` و`created_at` و`expense_date` تُنقل حرفيًا بلا تحويل timezone (`Africa/Cairo` في الجهتين).

### 3.9 عروض الأسعار
- **[CTO-2026-10-09] قرار Q7:** feature عروض أسعار **كاملة** في الـ SaaS قبل الترحيل: إنشاء وتعديل وطباعة، وتحويل لفاتورة، وانتهاء صلاحية، و5 حالات. التقدير ≈ 2–3 أيام وكلاء، في Phase 2 (أو آخر 1a). الـ schema بنفس أعمدة `main` (`2026_09_04_000001`). **مفتوح:** هل الـ feature متاحة لكل الباقات ولا Pro وأعلى بس.
- تُنقل بعد `invoices`، ثم يُربط `converted_invoice_id`.
- `status` يُتحقق من قيمه الخمس: `draft` و`sent` و`converted` و`expired` و`cancelled`.
- مصدر سعر الجملة: نفحص أعمدة `items` من الـ dump أثناء البروفة. لو `wholesale_price` غير موجود، فكل أسعار «جملة» التاريخية هي `selling_price` فعلًا. **[CTO-2026-10-09] قرار Q8:** في الحالتين `min_selling_price` = `NULL` للأصناف المنقولة.
- **[CTO-2026-10-09] إعداد جديد طلبه الـ CTO مع Q8:** `items.min_price.default_mode` = `none` | `cost` | `cost_plus_percent` (+ نسبة). بيتطبق وقت إنشاء صنف جديد (ومعاه تطبيق جماعي اختياري)، وبيحل محل الافتراض الضمني في `ItemDTO` (min = التكلفة). مضاف لـ SETG-9 في [`phase-1-plan.md`](phase-1-plan.md).

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
| R3 | **أرصدة الموردين** | نفس R2 عبر `SupplierBalanceService`. **[CTO-2026-10-09]** المرجع هو الرصيد **الصحيح** بعد شيل أثر `PAY-EXP` (Q4)، والفرق عن الرصيد الحي لازم يطابق تقرير الفروق المعتمد |
| R4 | **ذمم الفواتير** | لكل عميل: `Σ remaining_amount` (confirmed) مقابل `current_balance` + المرتجعات ± الـ delta الافتتاحي. ولكل فاتورة: `paid + remaining = net_total`، و`payment_status` متسق |
| R5 | **المخزون لكل صنف** | `items.current_stock` المصدر = الهدف |
| R6 | **المخزون لكل صنف ومخزن** | `store_stocks.quantity` لكل `(store_id, item_id)` المصدر = الهدف. ويُبلَّغ (ولا يفشل) عن `current_stock ≠ Σ store_stocks` و`last stock_after ≠ current_stock`، لأنهما فروق موروثة من المصدر ومعروفة |
| R7 | **التكلفة** | `weighted_avg_cost` و`cost_price` لكل صنف = القيم الحية (**[CTO-2026-10-09]** Q2). سطور التكلفة الصفرية اللي اتصححت تاريخيًا لازم تطابق تقرير الفروق المعتمد |
| R8 | **الخزينة** | `TreasuryService::getBalances()` لكل طريقة دفع ولكل store = نفس الحساب على المصدر (نفس الكود متطابق، فنشغّل نسخة الحساب على الـ staging DB). **[CTO-2026-10-09]** الفروق المتوقعة بس: المبالغ اللي ظهرت بخريطة طرق الدفع (Q6)، وشيل `PAY-EXP` المكرر (Q5)، وكلها لازم تطابق تقرير مطابقة الخزينة المعتمد |
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
2. الـ migrations المحمية (§3)، و**[CTO-2026-10-09]** مستند الرصيد الافتتاحي (Q1) وإصلاح `PAY-EXP` (Q4) وfeature عروض الأسعار الكاملة (Q7)، وكلهم في Phase 2.
3. إصلاح أسماء الصلاحيات في الكود + `daily_journal.close_shift` (§3.6، Q10) وعزل الـ logo (§3.7).
4. إنشاء الـ tenant في الـ central (`tenants` و`domains` و`subscriptions`). **[CTO-2026-10-09] قرار Q11:** المحل بيدخل كـ **عميل مؤسس على Enterprise**، مجانًا أو بسعر رمزي (الـ CTO هيحدد الرقم)، ومحسوب من الـ 50 خانة بتوع الـ founders. الـ super-admin يقدر يعمل override للحدود.
5. **[CTO-2026-10-09] قرار Q12 (الاستضافة):** المحل بيتنقل على الـ VPS الجديد على Hetzner (نفس قرار Q-O1 في خطة Phase 1).

### 6.2 البروفة (مرتين على الأقل، على staging أو VPS البروفات)
1. نأخذ backup/dump من الحي. **المالك أو الـ CTO هو من ينفّذه أو يعتمده**، ولا يلمس أي وكيل الخادم الحي. **مفتوح (Q15):** المقترح إن الـ CTO أو المالك ياخده بـ export من hPanel، ويتخزن برّه الريبو، وClaude يجهز الخطوات خطوة بخطوة. لسه مستني تأكيد.
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
**[CTO-2026-10-09] قرار Q12:** ميعاد الـ cutover بيتحدد بالاتفاق مع صاحب المحل، من غير قاعدة ثابتة.

1. إعلان التوقف، ثم إقفال كل الورديات المفتوحة، ثم وضع `main` في maintenance mode (قراءة فقط).
2. تفريغ queue الـ `main`، وإيقاف scheduler و**Telegram** على `main`.
3. dump نهائي + backup مشفر (Google Drive) + checksum.
4. import `--commit`، ثم reconcile. **لا ننتقل للخطوة التالية إلا وكل الفحوص خضراء.**
5. نقل الـ logo، و`permission:cache-reset` ومسح كاش الإعدادات، وتفعيل الـ scheduler و Telegram على الـ SaaS.
6. تحويل الأجهزة: تسجيل دخول جديد على Android وElectron برمز المحل، لأن الـ tokens تبدأ فارغة.
7. أول يوم: مطابقة إقفال الوردية الأولى يدويًا مع المالك.

### 6.4 الرجوع (rollback)
- **نقطة اللاعودة:** أول فاتورة حقيقية على الـ SaaS. قبلها يكون الرجوع = إلغاء maintenance mode على `main`، لأن `main` لم يتغير ولم يُحذف منه شيء.
- **بعدها** (خلال نافذة المراقبة): يبقى `main` و DB الخاصة به **read-only محفوظة كما هي**. الرجوع = تصدير المستندات الجديدة من الـ tenant (فواتير ومدفوعات ومصروفات بعد وقت الـ cutover) وإدخالها يدويًا أو بأمر عكسي محدود في `main`. هذا مكلف، فلازم نقرر الرجوع بسرعة.
- **[CTO-2026-10-09] قرار Q12 (المراقبة):** أسبوعين مراقبة بعد الـ cutover: مراجعة يومية لأخطاء Sentry وللـ backups. أول 3 أيام مراقبة مكثفة (**افتراض**، ماتأكدش صراحةً).
- **[CTO-2026-10-09] قرار Q12 (مصير `main`):** `main` يبقى read-only بعد أول فاتورة على الـ SaaS، ويتقفل نهائيًا بعد 3 شهور من الـ cutover، بعد backup نهائي على Google Drive. الاستضافة القديمة تفضل staging.
- لا نحذف DB الـ `main` ولا نعيد استخدامها قبل الـ 3 شهور والـ backup النهائي واعتماد مكتوب من الـ CTO.

---

## 7. المخاطر

| # | الخطر | الأثر | التخفيف |
|---|---|---|---|
| 1 | الاستيراد قبل نقل PR #3: الـ SaaS `updateInvoice` و`deleteInvoice` يحذفان الإيصالات ويعيدان إنشاء `paid_amount` كاملًا | تحصيل مزدوج، ورصيد دائن وهمي، وخزينة منفوخة | شرط مسبق صارم، أو منع تعديل الفواتير المستوردة التي لها إيصالات منفصلة |
| 2 | غياب FIFO في الـ SaaS `PaymentService` | سلوك مختلف بعد الاستيراد، والأرصدة تتغير عند أول إيصال | نقل `e73cbd1c` |
| 3 | WAC: الـ fallback `?:` و`ReturnService` و`depositStock` بلا blending، وإلغاء المشتريات لا يعكس | أرباح وتقييم خاطئ، وانحراف يتراكم | نقل `d08edc92` + قرار الـ recost |
| 4 | الأرصدة الافتتاحية تُمسح | فرق صامت في الأرصدة | §3.1 و§3.2 + مستند افتتاحي (**[CTO-2026-10-09]** معتمد، Q1) |
| 5 | فجوات `stock_movements` | المخزون لا يُعاد بناؤه من الحركات | ننقل الأرصدة كما هي ونبلّغ عن الفروق |
| 6 | `store_id` بقيمة `NULL` أو يتيم | صفوف مخفية أو مسرّبة بسبب الـ store scoping | fail-fast |
| 7 | `payment_method` خارج `activeMethods` | فلوس تسقط من الخزينة | خريطة تطبيع معتمدة |
| 8 | outflow مزدوج (`Expense` + `PAY-EXP`) وbug `PAY-EXP` على المورد | خزينة وموردون غير دقيقين | **[CTO-2026-10-09]** `Expense` يفضل و`PAY-EXP` المكرر يتشال (Q5)، والـ bug يتصلح (Q4)، مع تقارير فروق يعتمدها المالك |
| 9 | فروق أسماء الصلاحيات | أدوار تفقد صلاحيات أو تكسب صلاحيات (فتح/إقفال الوردية) | **[CTO-2026-10-09]** إصلاح الفحوص في الكود (Q10) + تقرير المستخدمين اللي هيفقدوا صلاحية |
| 10 | `customers.price_tier` مُضاف داخل migration منشورة (`d52ffe14`) | tenants قديمة بلا العمود | migration محمية جديدة |
| 11 | الـ logo من `public/` مشترك | تسريب branding بين المستأجرين | عزل قبل المستأجر الثاني |
| 12 | تعارض هاتف أدمن مركزي مع مستخدم المحل، ودخول أدمن مركزي إلى المحل | فشل دخول أو وصول غير مقصود | فحص قبلي. **[CTO-2026-10-09]** الـ fallback يتشال، والدعم بالـ impersonation المدققة (Q13) |
| 13 | حدود الباقة (فروع ومخازن وسيارات ومستخدمون وأصناف) | رفض عمليات بعد تفعيل الحدود في Phase 1 | **[CTO-2026-10-09]** Enterprise كعميل مؤسس + override من الـ super-admin عند الحاجة (Q11) |
| 14 | الـ blender في الـ SaaS يضع `paid_amount` = `0` في `cash` | دين وهمي على عميل | إصلاح قبل الـ cutover |
| 15 | تنبيهات Telegram مكررة، أو jobs معلّقة على `main` | إزعاج أو كتابة على DB قديمة | إيقاف الـ scheduler والـ queue على `main` |
| 16 | `CreateItemAction` يولّد الكود من `count()+1` | تعارض unique مع الأكواد المستوردة | توليد الكود من `MAX` أو sequence |
| 17 | `ShiftService` يعدّ مبيعات العميل النقدية مرتين (في الجهتين) | أرقام ورديات جديدة مختلفة عن المتوقع | الـ snapshots القديمة كما هي + إصلاح لاحق |
| 18 | ترقيم الورديات والمصروفات من عدّ يومي بلا lock، و`TreasuryService::transfer` بلا `lockForUpdate` | تعارض أرقام أو سحب زائد عند التزامن | ديون تقنية لـ Phase 2 |
| 19 | dump الحي يحتوي PII وسر Telegram | تسريب | الـ staging DB تُحذف بعد البروفة، والتقارير بلا أسرار، وTelescope/Pulse لا تُنقل |

---

## 8. قرارات الـ CTO (مُجابة [CTO-2026-10-09])

> الترقيم هنا هو ترقيم الأسئلة الأصلي في الوثيقة دي. في ملاحظات الـ CTO اتجمّع السؤال 12 تحت «Q12a/b/c» (التوقيت والمراقبة ومصير `main`)، والسؤال 14 اتسجّل باسم «Q13». السؤالين 12 (الاستضافة) و13 (الأدمن المركزي) كانوا اتحسموا قبل كده.

| # | السؤال | الحالة | القرار | الأقسام اللي اتعدلت |
|---|---|---|---|---|
| 1 | الأرصدة الافتتاحية | **ANSWERED** | مستند رصيد افتتاحي صريح للعملاء والموردين (قيد في الـ ledger، والرصيد = الافتتاحي + الحركات، و`updateBalance()` مايمسحهوش). يتصلح لكل المستأجرين في Phase 2. كل `delta ≠ 0` يبقى مستند يتراجع مع المالك قبل الـ cutover | §1، §3.1، §3.2، §6.1 |
| 2 | الـ WAC التاريخي | **ANSWERED** | القيم الحية تتنقل كما هي. الـ recost اتطبق على الحي يوم 2026-09-29 حسب دليل الـ backups، **وده ماتأكدش صراحةً من الـ CTO**. المستورد بيتحقق من كل سطر، والسطور الصفرية الفاضلة تتصحح تاريخيًا من حركات الشراء بتقرير فروق | §3.4، R7 |
| 3 | back-fill `return_items.cost_price` | **ANSWERED** | تكلفة سطر الفاتورة الأصلية ← تكلفة حركة المخزون ← WAC الصنف في التاريخ ده. كل fallback يظهر في التقرير | §2.1، §3.4 |
| 4 | bug `PAY-EXP` على رصيد المورد | **ANSWERED** | يتصلح، مايتعادش إنتاجه. تتنقل كمصروفات خزينة من غير ربط بالمورد، مع تقرير فروق لكل مورد. والـ bug يتصلح في كود الـ SaaS في Phase 2 | §3.2، R3، §7 |
| 5 | الـ outflow المزدوج | **ANSWERED** | سجل `Expense` يفضل (تصنيف «مصاريف نقل/إضافية للمشتريات»)، و`PAY-EXP` المكرر يتشال. تقرير مطابقة خزينة | §2.3، §3.8، R8 |
| 6 | خريطة `payment_method` | **ANSWERED** | Claude بيجهز خريطة مقترحة (بالعدد والإجمالي)، والمالك يعتمدها. المش واضح يروح `other` + تقرير. كل طريقة مرتبطة بخزينة. تقرير فروق خزينة | §3.8، R8 |
| 7 | عروض الأسعار | **ANSWERED** (جزء مفتوح) | feature كاملة قبل الترحيل (≈ 2–3 أيام وكلاء)، في Phase 2 أو آخر 1a. العروض القديمة تتنقل بروابط `converted_invoice_id`. **مفتوح:** الإتاحة حسب الباقة (كل الباقات ولا Pro) | §1، §2.1، §3.9 |
| 8 | `items.min_selling_price` | **ANSWERED** | `NULL` (بلا أرضية)، والحماية بـ `pos.price.floor_policy` (SETG-9). إعداد جديد `items.min_price.default_mode` (`none`/`cost`/`cost_plus_percent`) للأصناف الجديدة بدل افتراض `ItemDTO`. وجود عمود سعر الجملة يتفحص من الـ dump في البروفة | §2.2، §3.9 |
| 9 | `customers.price_tier` | **ANSWERED** | الكل `retail`. المالك يحدد عملاء الجملة بإيده بعد الترحيل من غير اقتراح آلي، والكاشير يقدر يغيّر الفئة في كل فاتورة | §2.1 |
| 10 | الصلاحيات | **ANSWERED** | إصلاح الكود ليستخدم الأسماء المزروعة (وزرع الناقص، QA-2 في W2). `daily_journal.close_shift` على فتح وقفل الوردية. صلاحيات المحل تتنقل كما هي، مع تقرير قبل الـ cutover بالمستخدمين اللي هيفقدوا صلاحية | §2.3، §3.6، §6.1، §7 |
| 11 | الباقة | **ANSWERED** | عميل مؤسس على Enterprise، مجانًا أو بسعر رمزي (الـ CTO يحدد الرقم)، ومن ضمن الـ 50 founder. override الحدود متاح من الـ super-admin | §6.1، §7 |
| 12 | الاستضافة والتوقيت والمراقبة و`main` | **ANSWERED** | الاستضافة: Hetzner (اتحسم قبل كده). التوقيت: بالاتفاق مع المالك. المراقبة: أسبوعين (مراجعة يومية لـ Sentry والـ backups)، وأول 3 أيام مكثفة (افتراض). `main` read-only بعد أول فاتورة SaaS، ويتقفل بعد 3 شهور بـ backup نهائي على Google Drive، والاستضافة القديمة staging | §6.1، §6.3، §6.4 |
| 13 | الأدمن المركزي | **ANSWERED** | اتحسم قبل كده: الـ fallback يتشال، والبديل الـ impersonation المدققة في 1a | §3.6، §7 |
| 14 | `store_stocks` المحذوفة و`base_cost_price` الفاضي | **ANSWERED** | `store_stocks` المحذوفة تتنقل كما هي (محذوفة)، واللي رصيدها ≠ 0 تتعلّم في المطابقة. `base_cost_price` الفاضي يتملا بـ `cost_price` بتاع السطر | §2.2، §3.4، §3.5 |
| 15 | مين ياخد الـ dump | **مفتوح** | المقترح: الـ CTO أو المالك بـ export من hPanel، ويتخزن برّه الريبو، وClaude يجهز الخطوات. مستني تأكيد | §6.2 |

**لسه مفتوح:**
- مين ياخد الـ dump من الحي (السؤال 15).
- إتاحة عروض الأسعار حسب الباقة (السؤال 7).
- هل المحل بيستخدم الورديات حاليًا؟ ده بيحدد `shift.required_to_sell` للمحل بعد الترحيل (§3.8، وقرار الإعدادات Q1).
- تأكيد صريح إن الـ recost اتطبق على الحي يوم 2026-09-29 (السؤال 2)، وإن أول 3 أيام مراقبة مكثفة (السؤال 12).
- السعر الفعلي للمحل كعميل مؤسس (السؤال 11).

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
