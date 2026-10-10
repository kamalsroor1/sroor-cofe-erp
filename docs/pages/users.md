# 👥 وثيقة المكون والصفحة: إدارة المستخدمين والموظفين (`UsersView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إدارة المستخدمين والموظفين (Users & Staff Management)
* **المسار (Route):** `/users`
* **اسم المسار (Route Name):** `users.index`
* **الصلاحية المطلوبة (Permission):** `roles.manage`
* **الملف الرئيسي:** `resources/js/views/Users/UsersView.vue` (~94 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة حسابات موظفي المؤسسة وكاشيراتها ومحاسبيها في بيئة المستأجر المعزولة.
  * تعيين الدور الوظيفي (`Role`) لكل موظف (مدير نظام، كاشير، أمين مخزن، محاسب).
  * تعيين الفرع الافتراضي وتحديد الفروع والمستودعات المصرح للموظف بالعمل عليها (`Assigned Stores`).
  * تفعيل وتعطيل حسابات الموظفين فورياً لمنع الدخول غير المصرح به عند انتهاء الخدمة.
  * تعديل كلمات المرور والبيانات الشخصية للموظفين بأمان.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
UsersView.vue (~94 lines)
├── PageHeader.vue                    <-- رأس الصفحة مع زر إضافة مستخدم جديد
├── UsersFilterBar.vue                <-- شريط الفلاتر: البحث بالاسم/البريد، الدور الوظيفي، الفرع، والحالة
├── UsersTable.vue                    <-- جدول المستخدمين مع الأدوار، الفروع المصرح بها، مفتاح التفعيل، وقائمة الإجراءات
└── UserFormModal.vue                 <-- نافذة إضافة وتعديل المستخدم (الاسم، البريد، كلمة المرور، الدور، الفروع المخصصة)
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `DataTable.vue`, `StatusBadge.vue`, `AppModal.vue`.
* **المخازن المستخدمة:** `useAuthStore` (التحقق من صلاحية `roles.manage`)، `useAppConfigStore`.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/users`: جلب قائمة المستخدمين مع الفروع والأدوار:
  * **الكنترولر:** `App\Http\Controllers\Api\UserController@index`
  * **Resource:** `App\Http\Resources\UserResource`
* `POST /api/v1/users`: إنشاء حساب مستخدم جديد:
  * **Form Request:** `App\Http\Requests\StoreUserRequest`
  * **Action:** `App\Actions\Users\StoreUserAction`
* `GET /api/v1/users/{id}`: جلب بيانات المستخدم التفصيلية.
* `PUT /api/v1/users/{id}`: تعديل بيانات المستخدم وصلاحيات الفروع:
  * **Form Request:** `App\Http\Requests\UpdateUserRequest`
  * **Action:** `App\Actions\Users\UpdateUserAction`
* `DELETE /api/v1/users/{id}`: حذف حساب المستخدم (`roles.manage`).
* `PATCH /api/v1/users/{id}/toggle-active`: تفعيل/تعطيل الحساب فورياً.

---

## 5. الحماية الأمنية وعزل البيانات:
* **حماية التوكن السريع (`DenyQuickLoginToken`):** مسارات إدارة المستخدمين محمية بـ Middleware يمنع استخدام توكنات تسجيل الدخول السريع الخاصة بالاختبار، لضمان عدم التلاعب بحسابات الموظفين.
* **عزل المستأجر:** كافة المستخدمين ينتمون حصراً لجدول `users` الخاص بقاعدة بيانات المستأجر المعزولة.
