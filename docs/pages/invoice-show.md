# توثيق صفحة: تفاصيل ومعاينة الفاتورة والطباعة المزدوجة (Invoice Show & Dual Print)

> **المسار:** `/invoices/:id`  
> **ملف الـ View الرئيسي:** `resources/js/views/Invoices/InvoiceShowView.vue`  
> **النمط المعماري:** Thin Orchestrator (37 سطراً فقط)  
> **تاريخ التدقيق:** 2026-08-24  

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
   - تصميم مخصص لطابعات الكاشير الحرارية متوافق مع مقاس 80mm و 78mm و 58mm.
   - يحتوي على الترويسة، أرقام الفاتورة، جدول الأصناف، المبالغ، والسياسات.
3. **الفاتورة الضريبية الرسمية A4 (Official A4 Tax Invoice):**
   - قالب فاخر للشركات والمؤسسات التجارية جاهز للطباعة وتصدير الـ PDF.
   - يحتوي على السجل التجاري، الرقم الضريبي، كود QR، جدول الأصناف المفصل، شروط الاستبدال والاسترجاع، وتوقيعات الاستلام والختم الرسمي.
4. **شريط الإجراءات السريعة (Action Bar):**
   - طباعة حرارية فورية بضغطة زر.
   - طباعة رسمية A4.
   - مشاركة الفاتورة مباشرة عبر WhatsApp بنص منسق.
   - نسخ تفاصيل الفاتورة للحافظة.
   - نافذة إلغاء الفاتورة وعكس المخزون مع ذكر السبب الإجباري.
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
    ├── InvoiceShowHeader.vue                    <-- Breadcrumb, Badges & Interactive Tabs Switcher
    ├── InvoiceShowActionsBar.vue                <-- Quick Actions: Print, WhatsApp, Copy, Cancel, Back
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
- **Resource:** `InvoiceResource` مع تحميل علاقات `items`, `payments`, `customer`, `store`, `user`.

---

## 4. فحص التجاوب واللمس والوضعين
- اختبار كامل عبر المقاسات الخمسة:
  - Small Mobile (360px) ✅
  - Large Mobile (412px) ✅
  - Tablet Portrait (768px) ✅
  - Tablet Landscape (1024px) ✅
  - Desktop (1280px) ✅
- أزرار تفاعلية بأبعاد لمس قياسية `>= 44px`.
- دعم كامل للوضع الداكن والفاتح ولغة RTL العربية.

---

## 5. نتائج الاختبارات الآلية (Playwright E2E)
- ملف الاختبار: `e2e/flows/invoice-show-full-page-audit.spec.js`
- النتيجة: **7/7 اختبارات ناجحة بنسبة 100%**.
