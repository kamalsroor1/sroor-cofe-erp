# ⚖️ وثيقة المكون والصفحة: إدارة وحدات القياس المركزية للنظام (`SuperAdminUnitsView.vue`)

> **المسار (Route):** `/super-admin/units`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminUnitsView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% وفق المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة وحدات القياس المركزية للنظام (`/super-admin/units`)** كتالوج وحدات القياس القياسي لمنظومة الـ Multi-Tenant SaaS:
1. **رأس الصفحة وإجراءات الحفظ السريع:** عنوان الصفحة، وزر العودة للوحة القيادة، وزر حفظ التعديلات للنظام.
2. **قائمة وشبكة الوحدات المفعلة بالنظام (Active System Units Grid):** استعراض كافة وحدات القياس المعتمدة مع تصنيف تلقائي (عددية منفصلة ممنوع الكسور / وزن وحجم تقبل الكسور)، وزر إزالة الوحدة.
3. **قسم إضافة وحدة مخصصة جديدة للنظام:** حقل إدخال وكتابة اسم الوحدة المخصصة وزر الإضافة الفورية.
4. **قسم المقترحات الشائعة (Preset Suggestions):** اقتراحات جاهزة للوحدات الشائعة للضغط والإضافة بنقرة واحدة.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 212 سطراً إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminUnitsView.vue                <-- Thin Orchestrator (~65 lines)
├── Components/SuperAdmin/
│   ├── ActiveUnitsGrid.vue                    <-- شبكة شارات الوحدات المفعلة وتصنيفها وزر الحذف
│   ├── AddCustomUnitSection.vue               <-- قسم إضافة وحدة مخصصة جديدة
│   └── UnitPresetSuggestions.vue              <-- قسم الوحدات المقترحة الشائعة للإضافة الفورية
└── Composables/
    └── useSuperAdminUnits.js                  <-- كبسولة المنطق وإدارة البيانات والعمليات
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة للوحة القيادة وزر حفظ التعديلات.
* `BaseButton.vue`: أزرار الحفظ والإضافة.
* `BaseInput.vue`: حقل إدخال الوحدة المخصصة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ والتنبيه.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب قائمة وحدات القياس المركزية** | `GET /api/v1/super-admin/units` | - | مصفوفة الوحدات المركزية المعتمدة |
| **حفظ وتحديث وحدات القياس** | `POST /api/v1/super-admin/units` | `{ units: [...] }` | تأكيد الحفظ والتحديث |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي، شارات قابلة للنقر والإزالة بسهولة، أزرار بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * تنسيق بطاقات وشارات متراصة بمرونة تامة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.units_page_title`: إدارة وحدات القياس للنظام / System Units of Measurement
* `super.units_page_subtitle`: تحديد وتخصيص وحدات القياس المتاحة لكافة المستأجرين في المنظومة / Define and manage standard measurement units
* `super.active_units_count`: الوحدات المفعلة حالياً بالنظام (:count) / Currently Active System Units (:count)
* `super.discrete_unit_badge`: عددية (ممنوع الكسور) / Discrete (Whole items)
* `super.continuous_unit_badge`: وزن/حجم (تقبل الكسور) / Continuous (Weight/Volume)

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-units-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 5.33 ثانية.
