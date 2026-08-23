# ⚡ وثيقة المكون والصفحة: رادار إعادة الطلب الذكي ومساعد المشتريات (`SmartReorderView.vue`)

> **المسار (Route):** `/purchases/smart-reorder` (مع مسار بديل `/smart-reorder`)  
> **الملف الرئيسي:** `resources/js/views/Purchases/SmartReorderView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل صفحة **رادار إعادة الطلب الذكي ومساعد المشتريات (Smart Reorder Radar & AI Purchasing Engine)** مركز التنبؤ بالمخزون وإدارة الإمداد والتوريد للمؤسسة:
1. **تحليل معدل الاستهلاك الفعلي (Burn-Rate Analytics):** فحص حركات البيع والصرف اليومية للأصناف والخامات عبر فترات قابلة للتخصيص (آخر 7، 14، أو 30 يوماً).
2. **التنبؤ بأفق نفاد الرصيد (Stockout Horizon):** حساب دقيق لعدد الأيام المتبقية لصمود الرصيد الحالي بالمخازن (`days_remaining`).
3. **اقتراح كميات التوريد المثالية:** احتساب الكمية المقترح شراؤها لتغطية فترة الاحتياج المستهدفة (7 أيام، 15 يوماً، أو شهر كامل) بناءً على معدل السحب الفعلي وحد الطلب الأدنى.
4. **تصنيف وتلوين مستويات الخطر اللحظي:**
   - 🚨 **حرج (Critical):** المخزون يكفي أقل من 3 أيام أو رصيده منتهٍ تماماً (0).
   - ⚠️ **تنبيه وشيك (Warning):** المخزون يغطي فترة أقل من المدة المستهدفة وبحاجة لتنسيق التوريد.
   - 🛡️ **آمن (Safe):** المخزون يغطي كامل الفترة المستهدفة.
5. **التقدير المالي المباشر:** احتساب التكلفة التقديرية لإعادة التغذية لكل صنف وإجمالي الميزانية المطلوبة.
6. **التحويل الفوري لأمر شراء مجمع:** إمكانية تحديد كافة النواقص أو بنود محددة وتصديرها بنقرة واحدة كمسودة لفاتورة شراء جديدة (`/purchases/create?prefill=...`).

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Purchases/
│   └── SmartReorderView.vue                     <-- Thin Orchestrator (~65 lines)
├── Components/SmartReorder/
│   ├── SmartReorderMetricsGrid.vue              <-- بطاقات مؤشرات مستويات الخطر والتكلفة مع Skeletons
│   ├── SmartReorderFilterBar.vue                <-- شريط البحث وفلاتر أيام التحليل والتغطية ومستوى الخطر
│   └── SmartReorderTable.vue                    <-- جدول وتراص بطاقات النواقص اللمسية والتحديد المجمع
└── Composables/
    └── useSmartReorder.js                       <-- كبسولة المنطق الحسابي والاتصال بالـ APIs
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: رأس الصفحة مع شارة AI وشريط الإجراءات.
* `BaseButton.vue`: زر إنشاء أمر الشراء المجمع.
* `BaseSearchInput.vue`: حقل البحث المباشر عن الأصناف والخامات.
* `BaseSelect.vue`: القوائم المنسدلة لفترات التحليل والتغطية ومستويات الخطر.
* `StatCardSkeleton.vue` و `TableSkeleton.vue`: هياكل التحميل التفاعلية بالوميض (Shimmer).
* `EmptyState.vue`: حالة سلامة المخزون وعدم وجود نواقص.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Parameters) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب اقتراحات إعادة الطلب** | `GET /api/v1/purchases/smart-reorder` | `analysis_days`, `target_cover_days`, `urgency`, `search` | مؤشرات النواقص وجدول التوصيات والكميات والتكلفة |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * عرض بطاقات لمسية متراصة (Tactile Cards Stack) واضحة ومريحة مع مربعات اختيار لمسية واسعة $\ge 44	ext{px}$.
  * فلاتر التغطية والبحث تتراص عمودياً بانسيابية.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول بيانات متقدم عالي الكثافة مع محاذاة الأرقام المالية وتلوين شارات الخطر بالوسط.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وخلفيات التحذير (Rose, Amber, Emerald, Purple) وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/purchases.php` و `lang/en/purchases.php`:
* `purchases.reorder_radar_title`: رادار إعادة الطلب الذكي / Smart Reorder Radar
* `purchases.create_batch_po_btn`: إنشاء أمر شراء مجمع (:count) / Create Batch PO (:count)
* `purchases.critical_shortage_range`: نواقص حرجة (0 - 3 أيام) / Critical Shortage (0 - 3 Days)
* `purchases.warning_supply_range`: تنبيه توريد (4 - 7 أيام) / Supply Alert (4 - 7 Days)
* `purchases.safe_stock_range`: رصيد آمن (+8 أيام) / Safe Stock (+8 Days)
* `purchases.estimated_reorder_cost`: التكلفة التقديرية لإعادة التغذية / Estimated Restock Cost

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/smart-reorder-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.29 ثانية.
