# ☕ وثيقة المكون والصفحة: حاسبة وتوليفة خلطات البن والأصناف المركبة (`CoffeeBlenderView.vue`)

> **المسار (Route):** `/coffee-blender`  
> **الملف الرئيسي:** `resources/js/views/CoffeeBlender/CoffeeBlenderView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تعتبر صفحة **حاسبة خلطات وتكاليف البن (Coffee Blender & Product Formulation Studio)** الركيزة الأساسية لمحامص ومحلات ومصانع البن والمشروبات والمطاعم لإنتاج أصناف مركبة مخصصة حسب رغبة العميل (Custom Blend Formulation) في الوقت الفعلي:
1. **تحديد مواصفات التشغيلة:** إدخال اسم الخلطة أو المنتج المجمع، واختيار الوزن المستهدف من الأزرار السريعة (125جم، 250جم، 500جم، 1000جم) أو كتابة وزن حر مخصص.
2. **درجة التحميص ومستوى الطحن:** اختيار نوع التحميص (فاتح، وسط، غامق، محروق/دبل) ومستوى التجهيز (تركي ناعم، إسبريسو، فرينش بريس، حبوب خامة بدون طحن).
3. **توليفة المكونات ونسب الخامات:** إضافة أصناف البن الخام وتوزيع النسب المئوية (%) بسلايدر تفاعلي سلس، واحتساب الجرامات وسعر التكلفة وسعر البيع لكل صنف تلقائياً.
4. **الإضافات الخاصة (تحويجة الحبهان والمستكة):** تحديد كميات الحبهان والبهارات بالجرام واحتساب تكلفتها وقيمتها البيعية.
5. **التحليل المالي اللحظي:** احتساب إجمالي تكلفة الخامات، سعر البيع المقترح، وقيمة ونسبة هامش الربحية فورياً قبل حفظ الفاتورة.
6. **الربط المباشر بالفواتير والمخزون:** إصدار واعتماد فاتورة مبيعات مباشرة مع خصم كميات البن الخام بالجرام آلياً وبشكل ذري (Atomic Stock Deduction) من رصيد المخزن الفعلي دون حدوث أي تعارضات أو Race Conditions.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/
│   └── CoffeeBlender/
│       └── CoffeeBlenderView.vue                <-- Thin Orchestrator (~65 lines)
├── Components/
│   └── CoffeeBlender/
│       ├── CoffeeBlenderSpecsCard.vue           <-- بطاقة مواصفات الخلطة والوزن والأوزان السريعة
│       ├── CoffeeBlenderFormulationCard.vue     <-- بطاقة اختيار الأصناف الخام وسلايدر النسب والملاحظات
│       └── CoffeeBlenderCostSummary.vue         <-- لوحة التلخيص المالي واختيار العميل وزر الاعتماد
└── Composables/
    └── useCoffeeBlender.js                      <-- كبسولة المنطق الحسابي والاتصال بالـ APIs
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة لسجل الفواتير.
* `BaseButton.vue`: زر إضافة المكون الخام، أزرار الأوزان السريعة، وزر الاعتماد.
* `BaseInput.vue`: حقول إدخال اسم الخلطة والملاحظات.
* `BaseNumberInput.vue`: حقل الوزن المخصص، جرامات الحبهان، وحقول النسب.
* `BaseSelect.vue`: القوائم المنسدلة للتحميص، مستوى الطحن، واختيار العميل.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request / DTO) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب الأصناف والعملاء** | `GET /api/v1/items`, `GET /api/v1/customers` | Query Parameters | قائمة الأصناف الحية والعملاء |
| **حساب تكلفة الخلطة** | `POST /api/v1/coffee-blender/calculate` | Formulation Payload | التكلفة وسعر البيع وهوامش الربح |
| **إصدار فاتورة الخلطة** | `POST /api/v1/coffee-blender/invoice` | `CreateBlenderInvoiceDTO` | اعتماد الفاتورة وخصم المخزون |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي كامل (Single Column Stack) حيث تظهر مواصفات الخلطة تليها بطاقات المكونات اللمسية ثم بطاقة التكلفة والإنهاء.
  * أزرار الأوزان السريعة مريحة للإبهام بارتفاع $\ge 44	ext{px}$.
  * سلايدر النسب المئوية يعمل بسلاسة دون اعتراض التمرير الرأسي للشاشة.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * توزيع شبكي بنسبة 8 أعمدة لورك سبيس الخلطة و 4 أعمدة للوحة التكلفة والعميل في لوحة عائمة ملتصقة `sticky top-6`.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وسلايدر النسب وأزرار الإجراءات وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/inventory.php` و `lang/en/inventory.php`:
* `inventory.blender_title`: أداة وحاسبة تركيب وتجميع المنتجات / Product Formulation & Assembly Calculator
* `inventory.blend_specs_title`: مواصفات التركيبة والكمية المستهدفة / Assembly Specifications & Target Quantity
* `inventory.raw_beans_components`: المكونات والمواد الأولية / Raw Materials & Assembly Components
* `inventory.blend_cost_summary`: ملخص تكلفة وسعر التركيبة / Assembly Cost & Price Summary
* `inventory.blend_invoice_btn`: إصدار وتأكيد فاتورة التركيبة ⚡ / Issue & Confirm Assembly Invoice ⚡

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Feature API Test:** `php artisan test tests/Feature/Api/CoffeeBlenderApiTest.php` -> نجاح 2/2 اختبارات و 6 تأكيدات.
* ✅ **Playwright E2E Test:** `e2e/flows/coffee-blender-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.47 ثانية.
