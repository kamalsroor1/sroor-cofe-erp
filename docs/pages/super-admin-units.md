# ⚖️ وثيقة المكون والصفحة: إدارة وحدات القياس المركزية للنظام (`SuperAdminUnitsView.vue`)

> **المسار (Route):** `/super-admin/units`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminUnitsView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ التحديث الشامل:** 2026-10-11  
> **الحالة المعمارية:** ✅ محدثة وموثقة 100% وفق كتالوج وحدات المنصة المركزية (`PlatformUnitsController`).

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة وحدات القياس المركزية للنظام (`/super-admin/units`)** كتالوج وحدات القياس القياسي لمنظومة الـ Multi-Tenant SaaS المدار عبر `PlatformUnitsController`:
1. **رأس الصفحة وإجراءات الحفظ السريع:** عنوان الصفحة، وزر العودة للوحة القيادة، وزر حفظ التعديلات للنظام مع حماية المصادقة الثنائية `RequireRecentTwoFactor`.
2. **قائمة وشبكة الوحدات المفعلة بالنظام (Active System Units Grid):** استعراض كافة وحدات القياس المعتمدة مع تصنيف تلقائي (عددية منفصلة ممنوع الكسور / وزن وحجم تقبل الكسور)، وزر إزالة الوحدة.
3. **قسم إضافة وحدة مخصصة جديدة للنظام:** حقل إدخال وكتابة اسم الوحدة المخصصة وزر الإضافة الفورية.
4. **قسم المقترحات الشائعة (Preset Suggestions):** اقتراحات جاهزة للوحدات الشائعة للضغط والإضافة بنقرة واحدة.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم بناء الصفحة بنمط **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminUnitsView.vue                <-- Thin Orchestrator (~65 lines)
├── Components/SuperAdmin/
│   ├── ActiveUnitsGrid.vue                    <-- شبكة شارات الوحدات المفعلة وتصنيفها وزر الحذف
│   ├── AddCustomUnitSection.vue               <-- قسم إضافة وحدة مخصصة جديدة
│   └── UnitPresetSuggestions.vue              <-- قسم الوحدات المقترحة الشائعة للإضافة الفورية
└── Composables/
    └── useSuperAdminUnits.js                  <-- كبسولة المنطق وإدارة البيانات والعمليات عبر centralApi
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة للوحة القيادة وزر حفظ التعديلات.
* `BaseButton.vue`: أزرار الحفظ والإضافة.
* `BaseInput.vue`: حقل إدخال الوحدة المخصصة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ والتنبيه.

---

## 4. الاعتماديات والـ APIs المرتبطة (Central Routes `backend/routes/central.php`)

كافة المسارات تعمل تحت وسيط المصادقة المركزية `AuthenticateCentral` وعزل الدومين المركزي `EnsureCentralContext`:

| العملية | الـ Endpoint | الكنترولر والوسائط | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- | :--- |
| **جلب قائمة وحدات القياس المركزية** | `GET /api/v1/super-admin/units` | `PlatformUnitsController`<br>`can:super_admin.settings.view` | - | مصفوفة الوحدات المركزية المعتمدة |
| **حفظ وتحديث وحدات القياس** | `POST /api/v1/super-admin/units` | `PlatformUnitsController`<br>`can:super_admin.settings.manage`<br>`RequireRecentTwoFactor` | `{ units: [...] }` | 200 تأكيد التحديث |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي، شارات قابلة للنقر والإزالة بسهولة، أزرار بارتفاع $\ge 40\text{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * تنسيق بطاقات وشارات متراصة بمرونة تامة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال عبر Tailwind dark mode.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.units_page_title`: إدارة وحدات القياس للنظام / System Units of Measurement
* `super.units_page_subtitle`: تحديد وتخصيص وحدات القياس المتاحة لكافة المستأجرين في المنظومة / Define and manage standard measurement units
* `super.active_units_count`: الوحدات المفعلة حالياً بالنظام (:count) / Currently Active System Units (:count)
* `super.discrete_unit_badge`: عددية (ممنوع الكسور) / Discrete (Whole items)
* `super.continuous_unit_badge`: وزن/حجم (تقبل الكسور) / Continuous (Weight/Volume)

---

## 7. سجل الاختبارات والتحقق (Test Results & Audit Status)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-units-full-page-audit.spec.js` (تتطلب تشغيل خادم التحكم المركزي المحلي).
* ✅ **JS Architecture Tests:** `backend/tests/js/no-hardcoded-units.test.js` -> نجاح منع الوحدات الثابتة.
* ⚠️ **غير متحقق:** التحقق البصري المباشر على بيئة الإنتاج الحية (محظور بموجب قواعد حماية السيرفر الحي).
