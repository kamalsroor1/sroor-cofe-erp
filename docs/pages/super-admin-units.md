# ⚖️ وثيقة المكون والصفحة: إدارة وحدات القياس المركزية للنظام (`SuperAdminUnitsView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إدارة وحدات القياس للنظام (System Measurement Units)
* **المسار (Route):** `/super-admin/units`
* **اسم المسار (Route Name):** `super_admin.units`
* **الصلاحية المطلوبة (Permission):** `super_admin.access` (أو `super_admin.settings.view` / `super_admin.settings.manage` لمشغلي المنصة المركزية `CentralUser`).
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminUnitsView.vue` (~75 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة كتالوج وحدات القياس الافتراضية والقياسية المعتمدة على مستوى المنصة ككل (`System Units Catalog`).
  * تزويد المستأجرين الجدد بقائمة الوحدات القياسية عند تهيئة حساباتهم.
  * عرض شبكة الوحدات المفعلة مع تصنيفها التلقائي (وحدات عددية منفصلة Discrete Units أو وحدات أوزان وأحجام تقبل الكسور Fractional Units).
  * إضافة وحدات مخصصة جديدة أو اختيار وحدات من قائمة المقترحات الجاهزة الشائعة بنقرة واحدة (`Preset Suggestions`).
  * حفظ وتحديث قائمة الوحدات المركزية في إعدادات المنصة.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SuperAdminUnitsView.vue (~75 lines)
├── PageHeader.vue                 <-- رأس الصفحة مع أزرار العودة للوحة القيادة وحفظ التعديلات
├── ActiveUnitsGrid.vue            <-- شبكة شارات الوحدات المفعلة وتصنيفها وزر إزالة الوحدة
├── AddCustomUnitSection.vue       <-- قسم كتابة وإضافة وحدة مخصصة جديدة
└── UnitPresetSuggestions.vue     <-- قسم مقترحات الوحدات الشائعة للإضافة السريعة
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.
* **القالب العام:** `SuperAdminLayout.vue`.
* **الـ Composable:** `useSuperAdminUnits.js` لإدارة جلب الوحدات وإضافة وحدة جديدة وحذف وحدة وحفظ القائمة.
* **المخازن المستخدمة:** `useAuthStore` للتحقق من صلاحية السوبر أدمن.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/super-admin/units`:
  * **الوصف:** جلب قائمة وحدات القياس المركزية المعتمدة للمنصة.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@getUnits`
* `POST /api/v1/super-admin/units`:
  * **الوصف:** حفظ وتحديث قائمة وحدات القياس المركزية.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@updateUnits`
  * **Form Request:** `App\Http\Requests\UpdateSystemUnitsRequest`
  * **الحقول:** `units: string[]` (قائمة أسماء الوحدات).

---

## 5. الحماية الأمنية وعزل المنصة المركزية:
* **حماية النطاق المركزي:** الوصول محمي بـ `EnsureCentralContext` و `can:super_admin.access`.
* **التخزين المركزي:** تُخزن الوحدات في جدول الإعدادات المركزي أو كإعداد عام للمنصة دون المساس بإعدادات المستأجرين الفردية المستقلة.
