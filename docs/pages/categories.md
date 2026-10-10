# 🗂️ توثيق وتحليل صفحة فئات وتصنيفات المنتجات (Categories)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** فئات وتصنيفات المنتجات (Item Categories)
* **المسار (Route):** `/categories`
* **اسم المسار (Route Name):** `categories.index`
* **الصلاحية المطلوبة (Permission):** `items.view`
* **الملف الرئيسي:** `resources/js/views/Items/CategoriesView.vue` (~180 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * الهيكل التنظيمي والشجري لكافة تصنيفات المنتجات (مثل: مشروبات ساخنة، بن مطحون، عصائر، مستلزمات، حلويات).
  * التحكم في ترتيب الظهور (`Sort Order`) والأيقونات والإيموجي التعبيرية المعروضة على شاشات كاشير نقاط البيع (POS).
  * ربط الأصناف بمجموعاتها الرئيسية والفرعية لتسهيل الفلترة وإعداد التقارير المالية والتحليلية.
  * إحصاء عدد الأصناف التابعة لكل فئة مع إمكانية التعديل السريع أو الحذف الآمن (بشرط عدم وجود أصناف مرتبطة).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
CategoriesView.vue (~180 lines)
├── CategoriesHeader.vue              <-- رأس الصفحة مع زر إضافة فئة جديدة
├── CategoriesGrid.vue                <-- شبكة كروت التصنيفات البصرية مع الإيموجي وعدد الأصناف والترتيب
└── CategoryFormModal.vue             <-- نافذة إضافة وتعديل بيانات الفئة (الاسم بالعربية والإنجليزية، الأيقونة، الترتيب)
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `AppModal.vue`, `BaseInput.vue`.
* **المخازن المستخدمة:** `useAuthStore`, `useAppConfigStore`.
* **الـ Composables:** `useTrans` لإدارة الترجمة الفورية للحقول.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/categories`: جلب شجرة وقائمة الفئات:
  * **الكنترولر:** `App\Http\Controllers\Api\CategoryApiController@index`
* `POST /api/v1/categories`: إضافة فئة جديدة:
  * **Form Request:** `App\Http\Requests\StoreCategoryRequest`
  * **Action:** `App\Actions\Categories\StoreCategoryAction`
* `PUT /api/v1/categories/{id}`: تعديل بيانات الفئة:
  * **Form Request:** `App\Http\Requests\UpdateCategoryRequest`
  * **Action:** `App\Actions\Categories\UpdateCategoryAction`
* `DELETE /api/v1/categories/{id}`: حذف الفئة:
  * **Action:** `App\Actions\Categories\DeleteCategoryAction`

---

## 5. مصفوفة الصلاحيات:
* تصفح الفئات متاح لحاملي صلاحية `items.view`.
* إضافة وتعديل وحذف الفئات يتطلب صلاحيات إدارة الأصناف (`items.create` / `items.edit` / `items.delete` أو دور `admin`).

---

## 6. القواعد والمعايير المعمارية:
1. **الترتيب البصري للكاشير:** حقل الترتيب `sort_order` يحدد أولوية ظهور تبويبات الأصناف في شريط `POSCategoryBar.vue`.
2. **سلامة العلاقات المرجعية:** يمنع النظام حذف أي فئة تحتوي على أصناف نشطة؛ ويجب نقل الأصناف أو تفريغها أولاً.
