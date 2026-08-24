# توثيق صفحة: تفاصيل ومعاينة الفاتورة والطباعة المزدوجة (Invoice Show & Dual Print)

> **المسار:** `/invoices/:id`  
> **ملف الـ View الرئيسي:** `resources/js/views/Invoices/InvoiceShowView.vue`  
> **النمط المعماري:** Thin Orchestrator (37 سطراً فقط)  
> **تاريخ التدقيق:** 2026-08-24  
> **الإصدار الحي:** `v1.0.84`  

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Purpose)
صفحة عرض الفاتورة والطباعة المزدوجة تقدم تجربة شاملة ومتكاملة لإدارة واستعراض الفاتورة الصادرة:
1. **المعاينة التفاعلية الحية (Interactive Dashboard View):**
   - مؤشرات المبالغ المالية الستة (الإجمالي قبل الخصم، الخصم، مصاريف الشحن/الإضافية، الصافي المطلوب، المدفوع، المتبقي/الآجل).
   - بطاقة العميل الذكية ورصيده الحالي والمتبقي مع رابط مباشر لكشف الحساب.
   - بيانات الفرع، الكاشير، الوردية، وقناة السداد.
   - جدول الأصناف التفصيلي (الكود، الصنف، الوحدة، الكمية، سعر الوحدة، الخصم، الإجمالي).
   - سجل دفعات التحصيل وتاريخ وملاحظات الفاتورة.
2. **الطباعة الحرارية المباشرة (80mm Thermal Receipt):**
   - تصميم مخصص لطابعات الكاشير الحرارية متوافق مع مقاس 80mm و 78mm و 58mm عبر نمط `@page { size: 80mm auto; margin: 0; }`.
   - يحتوي على الترويسة، أرقام الفاتورة، جدول الأصناف، المبالغ، والسياسات.
3. **الفاتورة الضريبية الرسمية A4 (Official A4 Tax Invoice):**
   - قالب فاخر للشركات والمؤسسات التجارية جاهز للطباعة وتصدير الـ PDF بمقاس `@page { size: A4 portrait; margin: 8mm 10mm; }`.
   - يحتوي على السجل التجاري، الرقم الضريبي، كود QR، جدول الأصناف المفصل، شروط الاستبدال والاسترجاع، وتوقيعات الاستلام والختم الرسمي.
4. **شريط الإجراءات الموحد (BaseButton Action Bar):**
   - طباعة حرارية فورية بضغطة زر (`variant="success"`).
   - طباعة رسمية A4 (`variant="primary"`).
   - مشاركة الفاتورة مباشرة عبر WhatsApp بنص منسق.
   - نسخ تفاصيل الفاتورة للحافظة.
   - نافذة إلغاء الفاتورة وعكس المخزون مع ذكر السبب الإجباري (`variant="danger"`).
   - زر الرجوع الذكي.

---

## 2. هيكلية وشجرة المكونات (Component Tree)
```text
resources/js/
├── views/
│   └── Invoices/
│       └── InvoiceShowView.vue                  <-- Thin Orchestrator (37 lines)
├── Composables/
│   └── useInvoiceShow.js                        <-- Complete show, print & cancel state management
└── Components/Invoices/Show/
    ├── InvoiceShowSkeleton.vue                  <-- Facebook-Style Shimmer Loader
    ├── InvoiceShowHeader.vue                    <-- Breadcrumb, Badges & BaseButton Tabs Switcher
    ├── InvoiceShowActionsBar.vue                <-- Quick Actions: BaseButtons with perfect theme variants
    ├── InvoiceShowInteractiveView.vue           <-- Interactive Layout Container
    │   ├── InvoiceShowKpiGrid.vue               <-- 6 Financial Metric Cards
    │   ├── InvoiceShowCustomerCard.vue          <-- Customer profile, balance & statement link
    │   ├── InvoiceShowStoreCard.vue             <-- Store branch, cashier, shift & payment info
    │   ├── InvoiceShowItemsTable.vue            <-- Rich items breakdown table
    │   └── InvoiceShowPaymentsTimeline.vue      <-- Payments log & notes
    ├── InvoiceShowThermalReceipt.vue            <-- Pixel-perfect 80mm POS Thermal Receipt
    ├── InvoiceShowA4Document.vue                <-- Luxury Enterprise A4 Tax Invoice Document
    └── CancelInvoiceModal.vue                   <-- Safe cancel modal with stock reversal prompt
```

---

## 3. الاعتماديات والـ APIs
- **Endpoint:** `GET /api/invoices/{id}`
- **Cancel Endpoint:** `POST /api/invoices/{id}/cancel` (مع سبب الإلغاء وعكس حركات المخزون وقيد الخزينة داخل DB Transaction).
- **Localization Gate:** كافة النصوص تمر عبر `invoices.php`, `customers.php`, `common.php`, `pos.php`.

---

## 4. فحص التجاوب واللمس والوضعين (Responsive & Dark Mode Audit)
| الجهاز والمقاس | السلوك | النتيجة |
|---|---|---|
| **Small Phone (360px)** | بطاقات KPI تترتب عمودياً، وشريط الإجراءات يلتف بسلاسة مع لمس فوري | ✅ ممتاز (100%) |
| **Large Phone (412px)** | توافق تام مع الشاشات اللمسية وتجاوب الأزرار بدون أي overflow | ✅ ممتاز (100%) |
| **Tablet Portrait (768px)** | شبكة البطاقات تتوزع 3 أعمدة وجدول الأصناف يدعم التمرير السلس | ✅ ممتاز (100%) |
| **Tablet Landscape (1024px)** | تخطيط متوازن مع تقسيم 6 أعمدة للـ KPIs وبطاقات جانبية للعميل والفرع | ✅ ممتاز (100%) |
| **Desktop (1280px)** | عرض فخم ومتكامل يماثل أعلى معايير لوحات التحكم العالمية | ✅ ممتاز (100%) |
| **Dark Mode vs Light Mode** | تباين نصوص كامل مع تدرجات Slate متناسقة ومتغيرات الـ Theme | ✅ ممتاز (100%) |

---

## 5. سجل الاختبارات والتحقق (Test Suite)
- ملف الاختبار الآلي: `e2e/flows/invoice-show-full-page-audit.spec.js`
- النتائج: **7/7 اختبارات ناجحة بنسبة 100%**.
