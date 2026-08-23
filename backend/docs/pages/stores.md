# 🏬 توثيق وتحليل صفحة إدارة الفروع والمخازن (Stores & Branches)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إدارة الفروع والمخازن ونقاط التوزيع (Stores & Branches Management)
* **المسار (Route):** `/stores`
* **الملف الرئيسي:** `resources/js/views/Stores/StoresView.vue` (منسق نحيف: ~70 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة الهيكل الجغرافي للمنشأة متعددة الفروع والمخازن ونقاط التوزيع (محل قطاعي، مستودع/مخزن رئيسي، سيارة/عربة توزيع).
  * تعيين الفرع الرئيسي للمنشأة وحمايته من التعطيل أو الحذف العشوائي.
  * تفعيل/تعطيل الفروع ومراقبة حالة نشاط كل فرع.
  * إدارة وتعيين الموظفين والكاشيرات لكل فرع بصلاحيات معزولة.
  * تتبع مؤشرات الأداء الحية لكل فرع (عدد الأصناف، عدد الفواتير، عدد أذون الشراء).
  * الوصول السريع لجرد وأرصدة البضاعة في الفرع (`/stores/stocks?store_id=...`).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
StoresView.vue (Thin Orchestrator ~70 lines)
├── PageHeader.vue              <-- رأس الصفحة، زر جرد الفروع، وزر إضافة فرع جديد
├── StoresMetricsGrid.vue       <-- 4 بطاقات KPI (إجمالي الفروع، النشطة، الفرع الرئيسي، وتشكيلة الـ SKUs)
├── StoresSearchFilterBar.vue   <-- شريط البحث السريع وفلاتر النوع والحالة مع زر الإعادة
├── StoresGrid.vue              <-- شبكة بطاقات الفروع مع CardSkeleton التفاعلي وقائمة ActionMenu
├── StoreFormModal.vue          <-- نافذة إضافة وتعديل بيانات الفرع باستخدام BaseInput و BaseSelect
└── StoreStaffModal.vue         <-- نافذة تعيين الموظفين والكاشيرات المصرح لهم على الفرع
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة:
* `PageHeader.vue`: رأس الصفحة الموحد.
* `MetricCard.vue` / `StatCardSkeleton.vue`: بطاقات المؤشرات وهياكل الوميض التفاعلية.
* `CardSkeleton.vue`: هيكل الوميض التفاعلي لشبكة الفروع.
* `BaseInput.vue`: حقول اسم الفرع، الكود، العنوان، ورقم الهاتف.
* `BaseSelect.vue`: القائمة المنسدلة لاختيار نوع الفرع.
* `ActionMenu.vue`: قائمة الإجراءات المنسدلة (تعديل، تعيين موظفين، تفعيل/تعطيل، حذف).
* `AppModal.vue` / `EmptyState.vue`.

---

## 4. الاعتماديات والـ APIs:
* **Endpoints:**
  * `GET /api/v1/stores` (جلب قائمة الفروع والموظفين المتاحين).
  * `POST /api/v1/stores` (إنشاء فرع / مخزن جديد).
  * `PUT /api/v1/stores/:id` (تعديل بيانات الفرع).
  * `PATCH /api/v1/stores/:id/toggle-active` (تبديل حالة التفعيل).
  * `POST /api/v1/stores/:id/assign-users` (تحديث تعيينات الموظفين للفرع).
  * `DELETE /api/v1/stores/:id` (حذف الفرع الآمن بعد التحقق من عدم وجود موانع حذف).
* **Actions:** `CreateStoreAction`, `UpdateStoreAction`, `ToggleStoreActiveAction`, `AssignStoreUsersAction`, `DeleteStoreAction`.
* **Form Requests:** `StoreStoreRequest`, `UpdateStoreRequest`, `AssignStoreUsersRequest`.
* **DTO:** `StoreDTO`.

---

## 5. فحص التجاوب واللمس والوضعين الداكن والفاتح:
* **الهواتف (360px - 430px):** بطاقات الفروع تترتب رأسياً `grid-cols-1` مع ارتفاعات لمسية مريحة $\ge 44\text{px}$ لكافة الأزرار وعناصر التبديل.
* **التابلت (768px - 1024px):** شبكة ثنائية الأعمدة `grid-cols-2` مع تنظيم متناسق للعدادات.
* **الديسكتوب (1280px+):** شبكة ثلاثية الأعمدة `grid-cols-3` مع إضاءة محيطية للفرع الرئيسي (Ambient Glow).
* **توافق الألوان والوضعين:** دعم الوضعين الفاتح والداكن عبر متغيرات الثيم الديناميكية `var(--color-primary)`.

---

## 6. سجل الاختبارات والتحقق:
* ✅ **Playwright E2E:** نجاح 7/7 اختبارات في `e2e/flows/stores-full-page-audit.spec.js` عبر كافة المقاسات الـ 5.
* ✅ **Vite Build:** خلو الكود 100% من أخطاء البناء والترجمة.
