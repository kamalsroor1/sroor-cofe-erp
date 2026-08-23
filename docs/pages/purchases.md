# 🚛 وثيقة المكون والصفحة: سجل وإدارة فواتير المشتريات والتوريد (`PurchasesView.vue`)

> **المسار (Route):** `/purchases`  
> **الملف الرئيسي:** `resources/js/views/Purchases/PurchasesView.vue` (Thin Orchestrator: ~70 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **سجل فواتير المشتريات والتوريد (Purchases Management & Supplier Invoices)** البوابة المالية واللوجستية الرئيسية لاستقبال البضائع والخامات من الموردين وتتبع حركة التوريد:
1. **متابعة المؤشرات اللحظية (Purchases KPIs):**
   - إجمالي المشتريات المؤكدة (`total_purchases`).
   - مستحقات الموردين غير المسددة (`unpaid_total`).
   - عدد فواتير التوريد المعتمدة (`confirmed_count`).
2. **التصفية والبحث المتقدم:** البحث الفوري برقم الفاتورة أو اسم المورد، الفلترة حسب حالة الفاتورة (مؤكدة / ملغاة)، والفلترة بالنطاق الزمني.
3. **استعراض تفاصيل الفاتورة (Purchase Details Modal):** نافذة منبثقة تفاعلية تعرض بنود الفاتورة والكميات المستلمة والتكلفة المحملة (Landed Costs) والخصومات والفرع المستلم.
4. **إلغاء الفاتورة بأمان (Atomic Transaction):** إمكانية إلغاء فاتورة الشراء مع عكس الرصيد المخزني والمالي فوراً وبأمان عبر `POST /api/v1/purchases/{id}/cancel`.
5. **التكامل مع رادار إعادة الطلب الذكي:** زر مباشر ينقل المستخدم إلى `/purchases/smart-reorder` للتحليل التنبؤي لاستهلاك الخامات.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Purchases/
│   └── PurchasesView.vue                        <-- Thin Orchestrator (~70 lines)
├── Components/Purchases/
│   ├── PurchasesMetricsGrid.vue                 <-- بطاقات إجمالي المشتريات والمستحقات والفواتير
│   ├── PurchasesFilterBar.vue                   <-- شريط البحث وقائمة الحالة ونطاق التاريخ
│   ├── PurchasesTable.vue                       <-- جدول وتراص بطاقات فواتير المشتريات وأزرار الإجراءات
│   └── PurchaseDetailsModal.vue                 <-- نافذة استعراض تفاصيل الفاتورة والبنود والتحميل المالي
└── Composables/
    └── usePurchases.js                          <-- كبسولة المنطق الحسابي والاتصال بالـ APIs
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر إنشاء فاتورة جديدة وزر رادار إعادة الطلب.
* `BaseSearchInput.vue`: حقل البحث النصي مع Debounce تلقائي.
* `BaseSelect.vue`: القائمة المنسدلة لاختيار حالة الفاتورة.
* `StatCardSkeleton.vue` و `TableSkeleton.vue`: هياكل التحميل التفاعلية بالوميض (Shimmer).
* `EmptyState.vue`: حالة عدم وجود فواتير مشتريات مسجلة مع زر إضافة أول فاتورة.
* `AppModal.vue`: النافذة المنبثقة الموحدة لعرض تفاصيل الفاتورة.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Parameters/Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب فواتير المشتريات** | `GET /api/v1/purchases` | `search`, `status`, `from`, `to`, `page`, `per_page` | قائمة الفواتير، المؤشرات المالية، والترقيم |
| **إلغاء فاتورة شراء** | `POST /api/v1/purchases/{id}/cancel` | `reason` | إشعار نجاح وعكس المخزون وحساب المورد |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * بطاقات لمسية متراصة ومريحة للإبهام، ترتيب عمودي للبطاقات والحقول، وأزرار بإجراءات واضحة وبارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول بيانات عالي الكثافة مع محاذاة دقيقة للمبالغ المالية وشارات ملونة معتمدة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/purchases.php` و `lang/en/purchases.php`:
* `purchases.purchases_list`: سجل فواتير المشتريات والتوريد / Purchase Invoices Registry
* `purchases.total_purchases`: إجمالي المشتريات / Total Purchases
* `purchases.unpaid_total`: المستحقات غير المسددة / Unpaid Due
* `purchases.confirmed_count`: الفواتير المعتمدة / Confirmed Invoices
* `purchases.new_purchase`: فاتورة توريد جديدة / New Purchase Invoice
* `purchases.smart_reorder_radar`: رادار الطلب الذكي / Smart Reorder Radar

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/purchases-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 3.85 ثانية.
