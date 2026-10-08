# توصية نموذج التسعير — Sroor ERP (أكتوبر 2026)

> **النطاق:** منصة SaaS سحابية متعددة المستأجرين (POS + مخزون + فواتير + خزينة + ورديات + تقارير) للمحلات والفروع في مصر: تجزئة، جملة وتوزيع بسيارات، وبيع بالوزن. Arabic-first، مع تطبيق Android (Capacitor) وWindows (Electron).
> **الأساس:** الباقات مبنية على الكود الحالي (`backend/database/seeders/PlansAndFeaturesSeeder.php`، 4 باقات و24 feature key) **+ نظام Add-ons** جديد.
> **قرارات CTO المعتمدة سلفًا:** (1) الباقات من الكود + Add-ons. (2) `blender.access` يتحول لـ Add-on عام غير مرتبط بالبن. (3) الدفع يدوي أولًا (Manual activation)، وPaymob وFawry لاحقًا.
> **تاريخ البحث:** 2026-10-08.

> **الحالة: معتمد من الـ CTO في 2026-10-08.**
> - **ما اعتُمد:** سلّم الباقات والأسعار والحدود (§4) وfounder pricing؛ توزيع الـ features (§5)؛ Mix Builder `mixes.manage` (§6)؛ الـ Add-ons والخدمات (§7)؛ سياسة التجربة والفوترة والسماح (§8)؛ الدفع اليدوي ثم Paymob ثم Fawry عبر `ActivateSubscriptionAction` واحد (§10).
> - **إضافة 1:** Offline POS = Add-on بـ **149 ج.م/شهر لـ Basic**، ومُضمَّن في Pro وEnterprise.
> - **إضافة 2:** الأسعار المعلنة **لا تشمل ضريبة القيمة المضافة 14%** (لم تعد بندًا يحتاج تأكيدًا).
> - علامات **[افتراض]** أدناه على الأرقام المعتمدة أصبحت قرارات. المرجع الموحّد للقرارات: [`product-overview.md`](../01-overview/product-overview.md).

### مفتاح التوثيق في هذه الوثيقة
- **[مصدر]** = رقم منقول من صفحة المنافس الرسمية أو مصدر منشور (الرابط في الجدول/المراجع).
- **[مصدر غير رسمي]** = رقم من دليل/مدونة/طرف ثالث، للاسترشاد فقط.
- **[افتراض]** = تقدير أو قرار تصميمي مقترح منّا يحتاج تأكيدًا قبل الاعتماد.

---

## 1. ملخص تنفيذي

1. **3 باقات مدفوعة + تجربة 14 يومًا** مع الإبقاء على الـ slugs الحالية: `basic` (البداية) / `pro` (النمو ⭐) / `enterprise` (الأعمال)، و`free` يتحول لتجربة مؤقتة وليس باقة مجانية دائمة.
2. **الأسعار المقترحة [افتراض]:** 449 / 899 / 1,799 ج.م شهريًا. السنوي = سعر 10 شهور (4,490 / 8,990 / 17,990 ج.م). هذا أعلى من أسعار الـ seeder الحالية (299/599/999)، لكنه أقل بوضوح من Daftra مصر (733 / 1,225 / 2,448 ج.م شهريًا [مصدر]) وWafeq مصر (966 / 1,386 / 2,490 ج.م [مصدر]).
3. **التسعير بالجنيه الثابت** وبالباقة + Add-ons للفروع والمستخدمين والسيارات. **لا تسعير per-device الآن**، لأن الكود ليس فيه سجل أجهزة (device registry).
4. **إعادة تسمية `blender.access` ← `mixes.manage`** بالاسم التجاري «الخلطات والتركيبات (Mix Builder)». مدفوع كـ Add-on بسعر 199 ج.م/شهر في Basic وPro، ومُضمَّن في Enterprise.
5. **تطبيقات Android وWindows مُتاحة في كل الباقات.** معنى `api.access` يُعاد تعريفه ليصبح «Public API / تكاملات خارجية» فقط.
6. **فجوات حرجة في الكود:**
   - حدود الباقة **غير مُطبَّقة فعليًا**: `Tenant::checkLimit()` يقرأ `limits.*` من JSON الـ features، وهو غير موجود، ولا يستدعيه أي كود.
   - لا يوجد جدول Add-ons ولا فواتير اشتراك SaaS.
   - `Plan` و`Subscription` بدون central connection pin.

---

## 2. مقارنة المنافسين (أسعار 2025–2026)

| المنافس | السوق/العملة | نموذج التسعير | الأسعار | Add-ons | سنوي/تجربة | المصدر |
|---|---|---|---|---|---|---|
| **Daftra (دفترة)** | مصر — ج.م | باقة + حدود (فواتير/مخازن/خزائن) + add-ons للمستخدم والفرع والجهاز | Basic **733**/ش (489.5/ش سنوي = 5,874/سنة)، Advanced **1,225** (11,731/سنة)، Premium **2,448** (23,520/سنة) | مستخدم إضافي 392 (294 سنوي)، **فرع 1,293.6** (980)، مخزن 637 (490)، **جهاز POS/Offline 637** (490)، تخزين 24.5/GB | خصم سنوي حتى ~30%؛ تجربة 14 يوم بدون كارت؛ بلا رسوم تأسيس | [مصدر] daftra.com/en/plans |
| **Wafeq (وافق)** | مصر — ج.م | باقة + فروع إضافية | Starter **966**/ش (9,660/سنة)، Plus **1,386** (13,860)، Premium **2,490** (23,900)، Enterprise بعرض سعر | فرع إضافي **89**/ش (890/سنة) | **12 شهر بسعر 10**؛ تجربة 14 يوم | [مصدر] wafeq.com/ar-eg/pricing |
| **Qoyod (قيود)** | السعودية — ر.س | باقة بعدد مستخدمين/مواقع + add-ons | Basic 138 ر.س/ش (1 مستخدم/1 موقع)، Pro 207 (3/3)، Advanced 379.5 (5/5) — شامل الضريبة | مستخدم 20 ر.س/ش، **موقع 40**، **مستخدم POS 50**، رواتب 10/موظف | السنوي = 10 شهور؛ عروض 2–3 سنوات (20–33%) | [مصدر] qoyod.com knowledge-base (أسعار 2025) |
| **Rewaa (رواء)** | السعودية — ر.س | **per-branch** + صناديق كاشير + مستخدمين؛ دفع سنوي فقط | الانطلاقة 275 ر.س/ش (سنوي)، النمو 367، التميز 708؛ خطط سنتين أرخص ~10% | فرع إضافي 899–3,198، صندوق كاشير 149–249، مستخدم 79–149 | سنوي/سنتين فقط | [مصدر] rewaa.com/pricing |
| **Foodics (فودكس)** | مصر — ج.م (مطاعم) | Bundles + عرض سعر للأجهزة | Basic ~**2,849**/ش، Advanced ~**3,734**/ش (فوترة سنوية/ربع سنوية) | add-ons بدون أسعار معلنة؛ الأجهزة بعرض سعر | لا تجربة معلنة | [مصدر] foodics.com/foodics-pricing-egypt |
| **Odoo** | عالمي — US$ | **per-user** | Standard $8.95/مستخدم/ش (خصم أول 12 شهر، القائمة $11.20)، Custom $16.40 ($20.90)، One App Free | Light user $2.90 | خصم سنوي | [مصدر] odoo.com/pricing |
| Odoo (عبر شريك مصري) | مصر — ج.م | per-user + تنفيذ | ~1,200–1,500 ج.م/مستخدم/ش؛ أول سنة لـ 1–2 terminals ≈ 40–70 ألف ج.م شاملة التنفيذ | — | خصم سنوي 15–20% | [مصدر غير رسمي] 2b-cs.com (مارس 2026) |
| **Zoho Inventory** | عالمي — US$ (سنوي) | باقة بحدود طلبات/مستخدمين/مواقع | Free، Standard $29، Professional $79، Premium $129، Enterprise $249 | مستخدم $7.5، **موقع $10**، +500 طلب $7.5 | تجربة 14 يوم | [مصدر] zoho.com/inventory/pricing |
| **Loyverse** | عالمي — US$ | **POS مجاني** + add-ons per-store/per-employee | مجاني | Employee mgmt $5/موظف/ش، Advanced inventory **$25/متجر/ش**، Sales history $5/متجر | **سنوي = 10 شهور**؛ تجربة 14 يوم لكل add-on | [مصدر] loyverse.com/pricing |
| **Edara ERP** | MENA — US$ | Base fee + per-user | Basic $29 + $5/مستخدم، Pro $49 + $9، Ultimate $79 + $12 | تخصيص بتكلفة إضافية | لا تجربة | [مصدر غير رسمي] GetApp/Capterra |
| **CloudPastry** (محلي، مخابز) | مصر — ج.م | باقة + عمولة marketplace | Starter **299**/ش (249 سنوي)، Growth **999** (833)، Pro **2,499** (2,083) | جهاز Smart POS بالتقسيط: 3,900 مقدم + 600×15 = 12,900 | خصم سنوي ~17%؛ خطة مجانية؛ الدفع عبر Paymob | [مصدر] cloudpastry.com |
| **Zemam** (محلي، ERP) | مصر — ج.م | باقة أساسية + users/branches إضافية؛ POS add-on | من ~**1,500**/ش (3 مستخدمين، فرع، مخزن) | POS كموديول إضافي | بلا رسوم تنفيذ إجبارية | [مصدر] getzemam.com/en/erp-cost-egypt |
| متوسط السوق المصري (مطاعم) | مصر — ج.م | — | أساسي 1,000–2,000/ش، متوسط 2,000–3,000، متقدم 3,000–5,000+؛ تركيب 500–2,000 | أجهزة 5,000–15,000 | — | [مصدر غير رسمي] talabxy.com |

### الاستنتاجات من السوق
- **النموذج السائد في مصر/الخليج:** باقة أساسية تشمل عددًا من المستخدمين والفروع + Add-ons للفرع والمستخدم وأحيانًا جهاز الكاشير. التسعير per-user الصِرف (Odoo/Edara) يصبح مكلفًا للمحلات.
- **فرق الفرع الإضافي ضخم بين المنافسين:** Wafeq 89 ج.م مقابل Daftra 1,294 ج.م. هنا فرصة لـ Sroor بسعر فرع متوسط (249 ج.م) يُشجّع التوسع.
- **«12 شهر بسعر 10» شبه معيار** (Wafeq، Qoyod، Loyverse، والـ seeder الحالي نفسه: 2990 = 10×299).
- **تجربة 14 يومًا بدون كارت هي المعيار** (Daftra، Wafeq، Zoho، Loyverse).
- **الأجهزة دائمًا خارج الاشتراك** (Foodics، CloudPastry، Daftra تبيع «جهاز POS» كـ add-on ترخيص).
- **لم نجد أسعارًا رسمية منشورة بالجنيه** لـ Edara وDexef ومعظم البائعين المصريين المحليين، فهم يبيعون بعرض سعر. [لا مصدر]

---

## 3. ما يستطيع الكود التعبير عنه اليوم

| العنصر | الحالة الحالية | الملف |
|---|---|---|
| `plans` | `price_monthly/yearly decimal(10,2)`، `max_users`، `max_stores`، `max_items`، `max_invoices_per_month`، `max_storage_mb`، `features` JSON {key: bool}، `is_popular`، `sort_order` | `database/migrations/2019_09_15_000005_create_plans_and_features_tables.php` |
| `plan_features` | registry: key، module، `type enum(boolean, limit, quota)`. كل الـ 24 حاليًا `boolean` | نفس الملف |
| `subscriptions` | tenant_id، plan_id، `billing_cycle(monthly/yearly)`، `status(active/past_due/cancelled/trialing)`، amount، starts/ends، `payment_method` نصي، `payment_details` JSON | `2019_09_15_000015_create_subscriptions_table.php` |
| `tenants` | plan_id، `status(active/trial/suspended/cancelled)`، `trial_ends_at`، `subscription_ends_at`، `enabled_features` JSON (قائمة keys، Override يدوي من السوبر أدمن) | `2019_09_15_000010_create_tenants_table.php` |
| حل الفيتشرز | `TenantFeatureManager::isFeatureEnabled()` = override يدوي ∪ features الباقة | `app/Services/TenantFeatureManager.php` |
| الفروع | `stores.type` = `retail_shop` / `wholesale_van` / `main_warehouse`، وكلها تُعَد ضمن `max_stores` | `database/migrations/tenant/2026_08_11_180000_create_stores_and_stocks_tables.php` |

### الفجوات (Gap analysis)
1. **لا يوجد مفهوم Add-on إطلاقًا.** `enabled_features` مجرد قائمة keys بلا سعر ولا كمية ولا تاريخ انتهاء ولا ربط باشتراك. يصلح فقط للـ Add-ons البوليانية «مجانًا يدويًا».
2. **لا توجد Add-ons كمّية** (فرع/مستخدم/سيارة إضافية). الحدود مثبّتة على `plans` فقط.
3. **الحدود غير مُطبَّقة:** `Tenant::checkLimit()` يقرأ `getFeatureLimit('limits.users')` من `plan->features` JSON، وهذه المفاتيح غير موجودة في الـ seeder، فالنتيجة دائمًا 0. والدالة نفسها لا تُستدعى من أي Action. كما أنها تستعلم `User::where('tenant_id', …)` رغم أن المستخدمين في tenant DB (يحتاج تحقق). **أي تسعير بالحدود يتطلب إصلاح هذا أولًا.**
4. **`max_stores` يخلط الفرع والمخزن وسيارة التوزيع.** لا يمكن تسعير «سيارة إضافية» منفصلة عن «فرع إضافي».
5. **لا يوجد سجل أجهزة** (devices/terminals)، فالتسعير per-device غير قابل للتطبيق حاليًا.
6. **لا توجد فواتير اشتراك SaaS أو مدفوعات** (`billing_invoices` / `billing_payments`)، ولا مكان لإثبات تحويل InstaPay/فودافون كاش ولا لمرجع Fawry/Paymob.
7. `subscriptions.status` ينقصه `pending_payment` و`expired`. والمال `decimal(10,2)` يخالف قاعدة المشروع `DECIMAL(12,3)`.
8. `Plan` و`PlanFeature` و`Subscription` بدون `getConnectionName()` للـ central connection، وهذا مخالف لـ `multi-tenancy.md`. سيصبح خطرًا بمجرد قراءة الـ entitlements من سياق tenant.
9. **الوصف الحالي لـ `api.access`:** «تطبيق الموبايل وواجهة API (NativePHP / Flutter)»، وهو قديم (NativePHP أُزيل). إن رُبط به تطبيق Android/Windows فهذا يقتل القيمة الأساسية للمنتج.
10. **نصوص الباقات والفيتشرز في الـ seeder عربية hardcoded** وتذكر «المطاحن/البن». يلزم تعميمها (وتحويل الأسماء لـ translation keys، أو أعمدة `name_ar`/`name_en`).

---

## 4. سلّم الباقات المقترح (Plan ladder)

> كل الأرقام في هذا القسم **[افتراض]** مبني على المقارنة أعلاه. التموضع المقصود: **أرخص من Daftra/Wafeq مصر بـ 30–40%، مع POS + مخزون + خزينة + ورديات في كل الباقات المدفوعة**.

| | `free` ← **التجربة** | `basic` ← **البداية** | `pro` ← **النمو ⭐** | `enterprise` ← **الأعمال** |
|---|---|---|---|---|
| الفئة | تجربة 14 يوم | محل واحد (تجزئة/وزن) | محلات بفروع قليلة أو تاجر جملة صغير | سلاسل وشركات توزيع |
| `price_monthly` | 0 | **449** | **899** | **1,799** |
| `price_yearly` (=10 شهور) | 0 | **4,490** | **8,990** | **17,990** |
| `max_stores` (فروع بيع `retail_shop`) | 1 | 1 | 3 | 10 |
| مخازن `main_warehouse` (حد جديد مقترح) | 0 | 1 | 2 | 5 |
| سيارات `wholesale_van` (حد جديد مقترح) | 0 | 0 | 1 | 5 |
| `max_users` | 3 | 3 | 8 | 25 |
| `max_items` | 200 | 3,000 | 20,000 | غير محدود (`null`) |
| `max_invoices_per_month` | 300 | 6,000 (~200/يوم) | 30,000 | غير محدود (`null`) |
| `max_storage_mb` | 500 | 2,048 | 10,240 | 51,200 |
| الدعم | واتساب | واتساب (أيام العمل) | واتساب + أولوية | مسؤول حساب + أولوية قصوى |
| ما فوق الحدود | — | Add-ons | Add-ons | Add-ons أو عرض سعر Custom |

**ملاحظات على الحدود:**
- **`max_invoices_per_month` الحالي (1,000 في basic) منخفض جدًا لـ POS.** محل تجزئة يصدر 100–300 إيصال يوميًا. الحد يجب أن يكون **fair-use** لا أداة upsell. [افتراض]
- اقتراح اعتماد `null` = غير محدود بدل قيم سحرية مثل `999`/`99999`.
- السعر للمستخدم الفعلي: Basic ≈ 150 ج.م/مستخدم، Pro ≈ 112، Enterprise ≈ 72. أقل كثيرًا من مستخدم Daftra الإضافي (294–392 ج.م).

**مقارنة سريعة بالموقع السعري:**
- Basic 449 أقل من Daftra Basic 733 وWafeq Starter 966، وأعلى من CloudPastry Starter 299 (الذي بلا ERP).
- Pro 899 (3 فروع) مقابل Daftra Advanced 1,225 + فرعين إضافيين (2×1,294).
- Enterprise 1,799 أقل من Daftra Premium 2,448 وWafeq Premium 2,490.

**عرض الإطلاق [افتراض]:** أول 50 عميلًا مدفوعًا يحتفظون بالأسعار الحالية في الـ seeder (299/599/999) لمدة 12 شهرًا (founder pricing)، لتسريع أول مرجعيات.

---

## 5. توزيع الـ 24 feature على الباقات

| # | Key | التجربة | البداية | النمو | الأعمال | ملاحظة |
|---|---|:-:|:-:|:-:|:-:|---|
| 1 | `pos.access` | ✓ | ✓ | ✓ | ✓ | |
| 2 | `invoices.create` | ✓ | ✓ | ✓ | ✓ | |
| 3 | `invoices.edit` | ✓ | ✓ | ✓ | ✓ | يبقى محميًا بالصلاحية (admin)، لا بالباقة |
| 4 | `whatsapp.share` | ✓ | ✓ | ✓ | ✓ | مشاركة رابط، بلا تكلفة API |
| 5 | `items.manage` | ✓ | ✓ | ✓ | ✓ | |
| 6 | `items.movements` | ✓ | ✓ | ✓ | ✓ | |
| 7 | `transfers.manage` | ✓ | ✗ | ✓ | ✓ | يعمل فقط مع >1 مخزن/فرع؛ يُتاح تلقائيًا لو اشترى Basic فرعًا أو مخزنًا إضافيًا [افتراض] |
| 8 | `blender.access` ← **`mixes.manage`** | ✓ | Add-on | Add-on | ✓ | راجع القسم 6 |
| 9 | `purchases.manage` | ✓ | ✓ | ✓ | ✓ | المخزون بلا مشتريات بلا قيمة |
| 10 | `purchases.reorder` | ✓ | ✗ | ✓ | ✓ | upsell ذكي |
| 11 | `expenses.manage` | ✓ | ✓ | ✓ | ✓ | |
| 12 | `payments.manage` | ✓ | ✓ | ✓ | ✓ | |
| 13 | `shifts.manage` | ✓ | ✓ | ✓ | ✓ | قيمة «منع العجز» الأساسية |
| 14 | `treasury.view` | ✓ | ✓ | ✓ | ✓ | |
| 15 | `returns.manage` | ✓ | ✓ | ✓ | ✓ | |
| 16 | `reports.basic` | ✓ | ✓ | ✓ | ✓ | |
| 17 | `reports.advanced` | ✓ | ✗ | ✓ | ✓ | **المحرك الرئيسي للترقية** (أرباح/COGS) |
| 18 | `reports.export` | ✓ | ✓ | ✓ | ✓ | |
| 19 | `audit.logs` | ✓ | ✗ | ✓ | ✓ | |
| 20 | `printing.thermal` | ✓ | ✓ | ✓ | ✓ | |
| 21 | `printing.a4` | ✓ | ✓ | ✓ | ✓ | |
| 22 | `telegram.notifications` | ✓ | ✓ | ✓ | ✓ | تكلفة شبه صفرية وأداة retention قوية |
| 23 | `api.access` ← **Public API** | ✗ | ✗ | Add-on | ✓ | **إعادة تعريف:** تطبيقات Android/Windows ليست مربوطة بهذا المفتاح |
| 24 | `custom.domain` | ✗ | ✗ | Add-on | ✓ | |

**مبدأ التوزيع:** كل باقة مدفوعة «تشغّل محلًا كاملًا» (بيع + مخزون + مشتريات + خزينة + ورديات + مرتجعات). الترقية تأتي من **الحجم** (فروع/مستخدمين) و**الذكاء** (أرباح، إعادة طلب، رقابة)، لا من قصّ الأساسيات. Daftra وWafeq يفعلان المثل: الأساسيات في كل الباقات.

**التجربة تحصل على مزايا «النمو» كاملة** (ما عدا API/domain) لكن بحدود صغيرة، ليجرّب العميل أقوى ما في المنتج.

---

## 6. Add-on «الخلطات والتركيبات» (بديل `blender.access`)

**ماذا تفعل الميزة اليوم:** `CoffeeBlenderController` (`/coffee-blender/calculate` و`/coffee-blender/invoice`) يحسب خلطة من عدة أصناف بنسب، ويحسب تكلفتها، ويصدر بها فاتورة. هذه حالة عامة تتكرر في: العطارة والتوابل، المكسرات والياميش، العطور (تركيب)، الشاي، الحلويات بالوزن، الأعلاف، الدهانات، بالإضافة للبن.

| البديل | التقييم |
|---|---|
| `bom.manage` (Bill of Materials) | دلالة تصنيع ثقيلة وغير مفهومة للتاجر |
| `recipes.manage` | مرتبط ذهنيًا بالمطاعم |
| `compound_items.manage` | يوحي بصنف ثابت (kit/bundle)، والميزة ديناميكية لحظة البيع |
| **`mixes.manage` ✅** | عام، قصير، يغطي «خلطة عند الطلب» و«تركيبة محفوظة» |

- **الـ key:** `mixes.manage` (على نمط `<module>.<verb>` مثل `items.manage`)، module = `inventory`.
- **الاسم التجاري:** «الخلطات والتركيبات» / *Mix Builder*. الوصف: «تكوين خلطة من عدة أصناف بالنسب أو الوزن، حساب تكلفتها وهالكها، وبيعها مباشرة».
- **السعر [افتراض]:** 199 ج.م/شهر (1,990/سنة) لـ Basic وPro، ومُضمَّن في Enterprise والتجربة.
- **الترحيل (مقترح، لا يُنفَّذ الآن):**
  - migration بيانات تعيد تسمية الـ key في `plan_features.key` وفي مفاتيح `plans.features` وفي `tenants.enabled_features`.
  - تحديث `config/modules.json` و`useNavigation`/`router` و`FeatureGate`.
  - إعادة تسمية المسارات `coffee-blender` ← `mixes` مع إبقاء alias لإصدار واحد للتطبيقات القديمة (Android/Windows).
  - تحديث مفاتيح الترجمة في `lang/{ar,en}`.
  - Alias map مؤقت في `TenantFeatureManager` يربط `blender.access` بـ `mixes.manage` لحين تحديث كل العملاء.

---

## 7. قائمة الـ Add-ons والأسعار

> كل الأسعار **[افتراض]**. الأسعار شهرية بالجنيه، والسنوي = ×10.

### 7.1 Add-ons كمّية (تزيد حدًا)
| Add-on | key مقترح | يضيف | السعر/شهر | متاح لـ | ملاحظة |
|---|---|---|---|---|---|
| فرع بيع إضافي | `addon.store` | +1 `retail_shop` **+1 مستخدم** | **249** | كل الباقات | خصم كمية: 3–5 وحدات −15% (≈212)، 6+ −25% (≈187) |
| مخزن إضافي | `addon.warehouse` | +1 `main_warehouse` | **149** | كل الباقات | بلا POS |
| سيارة توزيع إضافية | `addon.van` | +1 `wholesale_van` **+1 مستخدم (مندوب)** | **249** | كل الباقات | نفس خصم الكمية |
| مستخدم إضافي | `addon.user` | +1 مستخدم | **79** | كل الباقات | 6+ مستخدمين: 59 |
| مساحة تخزين | `addon.storage_10gb` | +10 GB | **49** | كل الباقات | |
| أصناف إضافية | `addon.items_5k` | +5,000 صنف | **99** | Basic فقط | Pro/Enterprise حدودهما كافية |

### 7.2 Add-ons وظيفية (تفتح feature)
| Add-on | feature key | السعر/شهر | متاح لـ |
|---|---|---|---|
| **الخلطات والتركيبات** | `mixes.manage` | **199** | Basic، Pro (مُضمَّن في Enterprise) |
| تقارير الأرباح وCOGS | `reports.advanced` | **149** | Basic فقط (بديل الترقية لمن يريد الأرباح فقط) |
| التحويلات + إعادة الطلب الذكي | `transfers.manage` + `purchases.reorder` | **99** | Basic |
| سجل الرقابة | `audit.logs` | **99** | Basic |
| Public API / تكاملات | `api.access` | **299** | Pro |
| نطاق مخصص | `custom.domain` | **149** | Pro |

> **قاعدة حماية الترقية:** إذا تجاوز مجموع Add-ons عميل Basic سعر Pro، تقترح الواجهة الترقية تلقائيًا (Upgrade nudge).

### 7.3 خدمات (لمرة واحدة أو شهرية)
| الخدمة | السعر | النوع |
|---|---|---|
| تهيئة وترحيل بيانات (Excel أصناف/عملاء/أرصدة) + تدريب أونلاين ساعتين | 1,500 | مرة واحدة، اختياري |
| تركيب وتدريب في الموقع (القاهرة/الجيزة) | 2,500 + انتقالات | مرة واحدة |
| دعم مميز (رد خلال ساعة + مسؤول حساب) | 299/شهر | شهري، لـ Basic/Pro |
| حزم أجهزة (طابعة 80mm + قارئ باركود + درج + ميزان باركود) | حسب البرشور الحالي (4,900 / 6,800) | بيع أجهزة، خارج الاشتراك |

### 7.4 مؤجَّل (غير موجود في الكود، لا يُباع الآن)
- **الفاتورة/الإيصال الإلكتروني ETA:** الطلب عليها عالٍ (Daftra وCloudPastry يسوّقانها)، لكنها غير منفّذة في الكود. تُسعَّر لاحقًا كـ add-on بـ ~200–300 ج.م/شهر. [افتراض]
- **جهاز/Terminal إضافي (per-device):** يتطلب device registry وربط Sanctum token بالجهاز أولًا. مرجع السوق: Daftra 490–637 ج.م وQoyod 50 ر.س.

---

## 8. سياسة التجربة والخصم السنوي والفوترة

| البند | التوصية | الأساس |
|---|---|---|
| مدة التجربة | **14 يومًا**، بلا كارت، بمزايا «النمو» وحدود صغيرة | معيار السوق [مصدر] |
| تمديد التجربة | +7 أيام مرة واحدة بقرار المبيعات (super-admin) | [افتراض] |
| بعد انتهاء التجربة بلا دفع | **Read-only 30 يومًا** (عرض وتصدير فقط)، ثم `suspended`، والاحتفاظ بالبيانات 90 يومًا ثم أرشفة | [افتراض]: يقلل الإحباط ويرفع التحويل |
| الخصم السنوي | **12 شهرًا بسعر 10 (≈16.7%)** على الباقة وكل الـ Add-ons | Wafeq/Qoyod/Loyverse [مصدر] + الـ seeder الحالي |
| خصم سنتين | 25% مدفوعة مقدمًا، اختياري، Enterprise فقط | Rewaa/Qoyod يقدمان عروض متعددة السنوات [مصدر] |
| تثبيت السعر | السعر ثابت بالجنيه طوال المدة المدفوعة، ومراجعة الأسعار مرة سنويًا مع إشعار 30 يومًا | [افتراض]: ميزة «جنيه ثابت» من الدراسة السابقة |
| رسوم تأسيس | **لا توجد** للتسجيل الذاتي؛ التهيئة خدمة اختيارية | Daftra «no setup fees» [مصدر] |
| فترة سماح التأخير | `past_due` 7 أيام بإشعارات، ثم Read-only | [افتراض] |
| ترقية/تخفيض منتصف الدورة | ترقية فورية بفرق نسبي (proration) يدوي حاليًا؛ التخفيض من الدورة التالية | [افتراض] |
| الضريبة | الأسعار المعلنة **قبل** ضريبة القيمة المضافة 14%، مع توضيح ذلك في صفحة الأسعار | يحتاج تأكيدًا محاسبيًا/قانونيًا |

---

## 9. نمذجة الـ Add-ons في قاعدة البيانات (Central DB)

> **مقترح تصميمي — لا تعديل على الكود في هذه الوثيقة.** يُنفَّذ عبر `backend-architect` مع اختبارات من `qa-tester` ومراجعة `security-auditor`.

### 9.1 تعديلات على الجداول الحالية
- **`plans`:**
  - إضافة `max_warehouses` و`max_vans`، ويصبح معنى `max_stores` = فروع البيع `retail_shop` فقط.
  - إضافة `trial_days`.
  - جعل حدود «غير محدود» `nullable`.
  - ترحيل الأسعار إلى `decimal(12,3)` حسب القاعدة الذهبية رقم 1.
- **`subscriptions`:**
  - إضافة `pending_payment` و`expired` للـ status.
  - إضافة `grace_ends_at`.
  - إضافة `currency` (افتراضي `EGP`).
- **Models** `Plan` / `PlanFeature` / `Subscription` + الجديدة: إضافة `getConnectionName()` للـ central connection.

### 9.2 جداول جديدة
```
addons
  id, key (unique: addon.store, addon.user, mixes.manage …)
  type enum('feature','limit','service')
  feature_key nullable        → plan_features.key   (للنوع feature)
  limit_field nullable        → users|stores|warehouses|vans|items|storage_mb
  limit_increment int nullable
  bundled_limits json nullable  (مثال: addon.store يضيف {"stores":1,"users":1})
  price_monthly, price_yearly decimal(12,3)
  price_tiers json nullable   [{ "from":3, "unit_price":"212.000" }, { "from":6, "unit_price":"187.000" }]
  is_active, sort_order, timestamps

plan_addon            (أي add-on متاح لأي باقة)
  plan_id, addon_id, is_available, included_quantity, price_override nullable

subscription_addons   (ما اشتراه المستأجر فعلًا)
  id, subscription_id, tenant_id, addon_id, quantity,
  unit_price decimal(12,3), billing_cycle, starts_at, ends_at,
  status enum('active','pending_payment','cancelled','expired'), timestamps

billing_invoices      (فواتير SaaS — منفصلة تمامًا عن invoices في tenant DB)
  id, number (unique), tenant_id, subscription_id nullable,
  lines json (plan + addons snapshot), subtotal, tax, total decimal(12,3),
  currency, status enum('draft','pending','paid','void','refunded'),
  due_at, paid_at, timestamps

billing_payments
  id, billing_invoice_id, tenant_id, amount decimal(12,3),
  method enum('instapay','vodafone_cash','bank_transfer','cash','paymob_card','paymob_wallet','fawry_reference'),
  gateway enum('manual','paymob','fawry'), gateway_reference (unique nullable),
  proof_path nullable (tenant-scoped disk, server-generated filename),
  status enum('pending','verified','failed','refunded'),
  verified_by (central user id) nullable, verified_at, raw_payload json nullable, timestamps
```

### 9.3 محرك الاستحقاق (Entitlements)
- **`TenantEntitlementService`** (يُوسِّع أو يحل محل `TenantFeatureManager`، ويبقى خلف `TenantFeatureManagerInterface`):
  - `features = plan.features ∪ active subscription_addons(type=feature) ∪ enabled_features (override يدوي)`
  - `limits[x] = plan.max_x + Σ(qty × increment | bundled_limits) ; null = unlimited`
  - cache per-tenant بمفتاح يحتوي tenant id، ويُمسح عند أي تغيير اشتراك.
- **التطبيق (enforcement):**
  - Action موحّد `EnsureWithinPlanLimitAction` (أو Policy/Rule) يُستدعى من `CreateStoreAction` و`CreateUserAction` و`CreateItemAction`.
  - إصدار الفواتير: فحص soft للحد الشهري.
  - يرد بـ 403 أو 422 مع مفتاح ترجمة `subscription.limit_reached`.
  - **حذف أو إصلاح `Tenant::checkLimit()`/`getFeatureLimit()` المعطّلين.**
  - عدّ الفروع بحسب `stores.type` داخل tenant DB عبر `$tenant->run()`.
- **الواجهة:** `GetSystemContextAction` يُرجع `features` و`limits` و`usage` و`addons` لعرض «استخدمت 2 من 3 فروع — أضف فرعًا».

---

## 10. تجريد بوابات الدفع (Manual الآن ← Paymob/Fawry لاحقًا)

```
interface PaymentGateway {
    public function key(): string;                               // manual | paymob | fawry
    public function initiate(BillingInvoice $invoice): PaymentInitiation; // URL / reference / تعليمات تحويل
    public function verifyCallback(Request $request): GatewayResult;     // تحقق توقيع + idempotency
}
```

### المرحلة 1 — Manual (الآن)
- يصدر النظام `billing_invoice` بحالة `pending`، ويعرض للعميل تعليمات InstaPay / فودافون كاش / تحويل بنكي.
- العميل يرفع إيصال التحويل (`proof_path`: mime/size validation، اسم ملف server-side، disk مركزي خاص غير عام).
- السوبر أدمن يراجع، ثم `VerifyManualPaymentAction` الذي يستدعي `ActivateSubscriptionAction`. كل ذلك داخل `DB::transaction()` + `lockForUpdate()` على الاشتراك، ويُحدّث `tenants.status/subscription_ends_at` و`subscription_addons`.
- ⚠️ **لا تُكتب** أرقام المحافظ أو الحسابات البنكية في الكود. توضع في `config/billing.php` عبر `env()`.

### المرحلة 2 — Paymob
- **الرسوم المعلنة:** 2.75% + 3 ج.م لكل معاملة، بلا رسوم شهرية ولا تأسيس [مصدر: paymob.com/en/pricing].
- يدعم **Subscriptions/tokenization** للفوترة الشهرية المتكررة [مصدر: paymob.com/en/subscriptions].
- webhook بتحقق HMAC، ومسار central عام عليه rate limiting، بلا tokens في الـ URL.

### المرحلة 3 — Fawry
- رقم مرجعي يدفعه العميل نقدًا في منافذ فوري. مهم لشريحة المحلات التي لا تملك كروتًا.
- server notification مع signature verification.
- رسوم التاجر لم نجد لها مصدرًا رسميًا 2026: **[غير مؤكد]**، وتحتاج عرض سعر مباشر.

### قواعد مشتركة
- `gateway_reference` unique للـ idempotency.
- كل webhook يكتب `raw_payload`.
- التفعيل يمر دائمًا بنفس `ActivateSubscriptionAction`، فبوابة الدفع لا تغيّر منطق الاشتراك.
- اختيار البوابة من `config('billing.gateways')`، وتسجيل الـ drivers في Service Provider (Strategy pattern).
- أي webhook جديد يحتاج مراجعة `security-auditor`.

---

## 11. خطة تنفيذ مقترحة (بالترتيب)
1. **إصلاح الأساس:**
   - central connection pin للـ models.
   - entitlement service + enforcement حقيقي للحدود.
   - فصل `max_warehouses`/`max_vans`.
   - اختبارات 403/422 عند تجاوز الحد.
2. تحديث `PlansAndFeaturesSeeder`: أسعار وحدود جديدة، نصوص عامة غير مرتبطة بالبن، وإعادة تعريف `api.access`.
3. إعادة تسمية `blender.access` ← `mixes.manage` مع alias.
4. جداول `addons` / `plan_addon` / `subscription_addons` + شاشة السوبر أدمن لإسناد Add-on يدويًا.
5. `billing_invoices` / `billing_payments` + Manual gateway + رفع إثبات الدفع.
6. صفحة أسعار عامة + شاشة «الاشتراك والاستخدام» داخل التطبيق.
7. Paymob، ثم Fawry.

---

## 12. المراجع
- Daftra — https://www.daftra.com/en/plans (أسعار ج.م، add-ons، تجربة 14 يوم)
- Daftra (أسعار السعودية، مقال 21-10-2025) — https://www.daftra.com/en/hub/accounting-software-prices
- Wafeq مصر — https://www.wafeq.com/ar-eg/pricing
- Qoyod — https://www.qoyod.com/en/knowledge-base/qoyod-plans-and-pricing-subscription-tiers-features-add-ons/
- Rewaa — https://rewaa.com/pricing
- Foodics مصر — https://www.foodics.com/foodics-pricing-egypt/
- Foodics (عام) — https://www.foodics.com/eg/pricing/
- Odoo — https://www.odoo.com/pricing
- Odoo POS مصر (شريك، مارس 2026) — https://www.2b-cs.com/blog/2b-1/odoo-arabic-pos-egypt-55
- Zoho Inventory — https://www.zoho.com/inventory/pricing/
- Loyverse — https://loyverse.com/pricing
- Edara ERP (أدلة طرف ثالث) — https://www.getapp.com/communication-software/a/edara-erp/ ، https://www.capterra.com/p/227642/Edara-ERP/
- CloudPastry — https://cloudpastry.com/
- Zemam — https://getzemam.com/en/erp-cost-egypt
- متوسطات السوق المصري — https://talabxy.com/en/blog/best-pos-system-for-restaurants-egypt-2026
- Paymob pricing — https://paymob.com/en/pricing ؛ Paymob subscriptions — https://paymob.com/en/subscriptions
- داخلي:
  - `backend/database/seeders/PlansAndFeaturesSeeder.php`
  - `docs/03-architecture/pricing-restructuring-and-packages-study.md`
  - `docs/sroor-erp-pricing-and-features-brochure.html`
  - `docs/03-architecture/saas-transformation-architecture-and-plan.md`

> **تحفظات:**
> - أسعار المنافسين تتغير كثيرًا، وبعضها لم يحدد الصفحة بوضوح أي رقم شهري وأيها سنوي (Foodics، Daftra). يجب التحقق قبل النشر التسويقي.
> - لم نجد مصادر رسمية بالجنيه لـ Edara وDexef ورسوم Fawry.
> - كل أرقام Sroor في هذه الوثيقة افتراضات قابلة للمعايرة بعد قياس تكلفة الدعم الفعلية والتحويل من التجربة خلال أول 3–6 أشهر.
