# 🏬 توثيق وتحليل صفحة إدارة الفروع والمخازن (Stores & Branches)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** دليل وإدارة الفروع والمستودعات (Stores & Warehouses Management)
* **المسار (Route):** `/stores`
* **اسم المسار (Route Name):** `stores.index`
* **الصلاحية المطلوبة (Permission):** `stores.manage`
* **الملف الرئيسي:** `resources/js/views/Stores/StoresView.vue` (~291 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المركز الإداري لتعريف وهيكلة الفروع والمخازن الرئيسية وعربات التوزيع المتنقلة (Vans).
  * تعيين الموظفين والكاشيرات المصرح لهم بالدخول والعمل على كل فرع لضبط حدود الصلاحيات ومنع تداخل الحسابات.
  * إدارة إعدادات نقاط البيع الخاصة بكل فرع (`Store POS Settings`):
    * إعدادات موازين الباركود الإلكترونية (بادئة الباركود، نوع الميزان، عدد خانات الوزن والسعر).
    * الحد الأقصى المسموح به للخصم (`max_discount_percent`) لمنع التجاوزات.
    * إدارة واستبدال مصفوفة الأزرار والمفاتيح السريعة لنقاط البيع (`POS Quick Keys`).
  * تفعيل وتعطيل الفروع وحذف الفروع الشاغرة (التي لا تحتوي على حركات مخزنية أو مالية).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
StoresView.vue (~291 lines)
├── StoresMetricsGrid.vue             <-- بطاقات المؤشرات: إجمالي الفروع، الفروع النشطة، العربات المتنقلة، طاقم العمل
├── StoresSearchFilterBar.vue         <-- شريط الفلاتر: البحث، نوع الفرع (ثابت / سيارة متنقلة / مستودع)، والحالة
├── StoresGrid.vue                    <-- شبكة كروت الفروع مع بيانات الاتصال، عدد الموظفين، وأزرار الإجراءات
├── StoreFormModal.vue                <-- نافذة إضافة وتعديل بيانات الفرع (الاسم، الكود، النوع، العنوان، الهاتف)
└── StoreStaffModal.vue               <-- نافذة تعيين وإدارة موظفي وكاشيرات الفرع المصرح لهم
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `StatusBadge.vue`, `AppModal.vue`.
* **المخازن المستخدمة:** `useAuthStore` (إدارة الفرع النشط وقائمة الفروع المصرح بها للمستخدم)، `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js`.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/stores`: استعراض قائمة الفروع والمخازن:
  * **الكنترولر:** `App\Http\Controllers\Api\StoreController@index`
  * **Resource:** `App\Http\Resources\StoreResource`
* `POST /api/v1/stores`: إضافة فرع أو مستودع جديد (`stores.manage`):
  * **Form Request:** `App\Http\Requests\StoreStoreRequest`
  * **Action:** `App\Actions\Stores\StoreStoreAction`
* `PUT /api/v1/stores/{id}`: تعديل بيانات الفرع (`stores.manage`):
  * **Form Request:** `App\Http\Requests\UpdateStoreRequest`
  * **Action:** `App\Actions\Stores\UpdateStoreAction`
* `DELETE /api/v1/stores/{id}`: حذف الفرع (`stores.manage`).
* `PATCH /api/v1/stores/{id}/toggle-active`: تفعيل/تعطيل الفرع (`stores.manage`).
* `POST /api/v1/stores/{id}/assign-users`: تعيين الموظفين على الفرع:
  * **Form Request:** `App\Http\Requests\AssignStoreUsersRequest`
  * **Action:** `App\Actions\Stores\AssignStoreUsersAction`
* `GET /api/v1/stores/{store}/pos-settings` و `PUT /api/v1/stores/{store}/pos-settings`: إعدادات ميزان الباركود والخصم الأقصى للفرع (`StorePosSettingsController`).
* `PUT /api/v1/stores/{store}/pos/quick-keys`: استبدال أزرار الاختصارات السريعة للفرع (`PosQuickKeyController@replace`).
* `POST /api/v1/stores/switch`: التبديل السريع للفرع النشط (`SwitchStoreRequest`).

---

## 5. مصفوفة الصلاحيات ونطاق الفروع (Store Scoping):
* تتطلب الصفحة صلاحية `stores.manage`.
* صلاحية `stores.view_all`: تمنح المنسق أو المدير إمكانية طلب ترويسة `X-Store-Id: all` لرؤية إجمالي الفروع معاً.
* إذا حاول مستخدم بدون هذه الصلاحية إرسال `all` أو إرسال معرف فرع غير معين عليه، يُرجع النظام خطأ **HTTP 403** مع كود `store_access_denied`.
