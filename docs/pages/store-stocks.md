# 📦 وثيقة المكون والصفحة: جرد وأرصدة الفروع والمخازن (`StoreStocksView.vue`)

> **المسار (Route):** `/stores/stocks` (مع دعم بارامتر الاستعلام `?store_id=...`)  
> **الملف الرئيسي:** `resources/js/views/Stores/StoreStocksView.vue` (Thin Orchestrator: ~50 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل صفحة **جرد وأرصدة الفروع والمخازن (Branch Stock Valuation & Inventory Audit)** شاشة الجرد الدوري والرقابة المباشرة على البضائع في كل مستودع وفرع بيع على حدة:
1. **الاستعراض متعدد الفروع (Multi-Store / Branch View):** التبديل الفوري بين المخازن والفروع ونقاط التوزيع مع تمييز المخزن الرئيسي.
2. **الرقابة على مستويات الأمان وحد الطلب:**
   - 🚨 **بضاعة منتهية (Out of Stock):** رصيد صفر بالمخزن يستوجب التوريد العاجل.
   - ⚠️ **بضاعة قاربت على النفاد (Low Stock):** الرصيد وصل أو قل عن حد الأمان الأدنى (`min_stock_level`).
   - ✅ **رصيد متوفر وآمن (Available):** يغطي حركة البيع العادية.
3. **التقييم المالي الإجمالي للبضاعة:** استعراض سعر التكلفة/الشراء واحتساب القيمة الإجمالية للرصيد المتاح (`total_valuation = quantity * cost_price`).
4. **البحث المباشر والترقيم الذكي:** فلترة فورية بالاسم أو كود الصنف أو الباركود وترقيم متقدم للصفحات.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Stores/
│   └── StoreStocksView.vue                      <-- Thin Orchestrator (~50 lines)
├── Components/StoreStocks/
│   ├── StoreStocksFilterBar.vue                 <-- شريط اختيار الفرع، البحث، وأقراص تصفية الحالة
│   └── StoreStocksTable.vue                     <-- جدول وتراص بطاقات الأرصدة اللمسية والترقيم
└── Composables/
    └── useStoreStocks.js                        <-- كبسولة المنطق الحسابي والاتصال بالـ APIs
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة لإدارة المخازن والفروع.
* `BaseSelect.vue`: القائمة المنسدلة لاختيار الفرع / المخزن.
* `BaseSearchInput.vue`: حقل البحث المباشر مع خاصية الـ Debounce.
* `TableSkeleton.vue`: هيكل التحميل التفاعلي بالوميض (Shimmer).
* `EmptyState.vue`: حالة عدم وجود أصناف تطابق شروط التصفية.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Parameters) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب قائمة الفروع** | `GET /api/v1/stores` | — | قائمة المخازن والفروع |
| **جلب أرصدة المخزن وجردها** | `GET /api/v1/stores/stocks` | `store_id`, `search`, `stock_status`, `page`, `per_page` | جدول الأصناف والأرصدة والتقييم المالي |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * بطاقات لمسية متراصة (Tactile Cards Stack) مريحة وواضحة توضح اسم الصنف، الرصيد المتاح، التكلفة، والتقييم وشارة الحالة، مع أزرار بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول بيانات عالي الكثافة مع محاذاة الأرقام المالية جهة اليمين/اليسار وشارات الحالة في المنتصف.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وشارات الحالة (Rose, Amber, Emerald) وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/inventory.php` و `lang/en/inventory.php`:
* `inventory.branch_stocks_balance`: أرصدة وجرد الفروع / Branch Stock & Inventory
* `inventory.branch_stocks_subtitle`: متابعة أرصدة الأصناف، وحدود الطلب، وتقييم البضاعة في كل فرع / Monitor item stock, reorder levels, and valuation across branches
* `inventory.back_to_stores`: العودة للمخازن / Back to Stores
* `inventory.out_of_stock_badge`: بضاعة منتهية / Out of Stock
* `inventory.low_stock_badge`: قارب على النفاد / Low Stock
* `inventory.available_badge`: متوفر / Available

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/store-stocks-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.18 ثانية.
