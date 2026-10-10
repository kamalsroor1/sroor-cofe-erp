# 🏪 وثيقة المكون والصفحة: تفاصيل المستأجر والتحكم المركزي (`SuperAdminTenantShowView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** تفاصيل المستأجر والتحكم المركزي (Tenant Details & Control)
* **المسار (Route):** `/super-admin/tenants/:id`
* **اسم المسار (Route Name):** `super_admin.tenants.show`
* **الصلاحية المطلوبة (Permission):** `super_admin.access` (أو `super_admin.tenants.view` / `super_admin.tenants.manage` لمشغلي المنصة المركزية `CentralUser`).
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantShowView.vue` (~83 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * لوحة الإشراف المباشر والتحكم الدقيق في حساب المؤسسة الفردية للمستأجر.
  * استعراض بطاقة المؤشرات الحية للمستأجر: عدد المستخدمين، الفروع، الأصناف، الفواتير، وحجم المبيعات الإجمالي.
  * التحكم في مصفوفة ميزات الباقة وتطبيق التجاوزات الاستثنائية (`Feature Overrides`) للمستأجر المحدد.
  * إدارة وتخصيص وحدات القياس المعتمدة للمستأجر (`Allowed Inventory Units`).
  * تشغيل تهجيرات قاعدة البيانات المخصصة للمستأجر (`run-migrations`).
  * تعديل إعدادات اتصال قاعدة البيانات المخصصة (`update-db-config`).
  * تعديل حالة الاشتراك وتمديد المدة الزمنية أو إيقاف الحساب أو حذفه.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SuperAdminTenantShowView.vue (~83 lines)
├── TenantShowHeader.vue               <-- الرأس التنفيذي مع شارات الحالة والدومين وأزرار العمليات
├── TenantStatsGrid.vue                <-- شبكة المؤشرات التشغيلية الحية لحساب المستأجر
├── TenantFeaturesMatrixCard.vue       <-- بطاقة مصفوفة الميزات والتجاوزات الاستثنائية
├── TenantUnitsCard.vue                <-- بطاقة تخصيص وحدات القياس المسموح بها للمستأجر
└── TenantStatusModal.vue              <-- نافذة تعديل حالة الحساب وتمديد مدة الاشتراك
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `BaseButton.vue`, `BaseInput.vue`, `AppModal.vue`.
* **القالب العام:** `SuperAdminLayout.vue`.
* **الـ Composable:** `useSuperAdminTenantShow.js` لجلب بيانات المستأجر وإحصائياته وتحديث الميزات والوحدات والحالة.
* **المخازن المستخدمة:** `useAuthStore` للتحقق من صلاحية السوبر أدمن.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/super-admin/tenants/{id}`:
  * **الوصف:** جلب تفاصيل المستأجر، النطاقات، الإحصائيات، الميزات، والوحدات.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@showTenant`
  * **Action:** `App\Actions\Tenants\GetTenantDetailsAction`
* `POST /api/v1/super-admin/tenants/{id}/override-feature`:
  * **الوصف:** تفعيل أو تعطيل ميزة استثنائياً للمستأجر.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@overrideFeature`
  * **Form Request:** `App\Http\Requests\OverrideTenantFeatureRequest`
  * **Action:** `App\Actions\Tenants\OverrideTenantFeatureAction`
* `POST /api/v1/super-admin/tenants/{id}/update-units`:
  * **الوصف:** تحديث وحدات القياس المعتمدة للمستأجر.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@updateTenantUnits`
  * **Form Request:** `App\Http\Requests\UpdateTenantUnitsRequest`
* `POST /api/v1/super-admin/tenants/{id}/run-migrations`:
  * **الوصف:** تشغيل ملفات التهجير على قاعدة بيانات المستأجر.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@runTenantMigrations`
* `POST /api/v1/super-admin/tenants/{id}/update-db-config`:
  * **الوصف:** تحديث بيانات اتصال قاعدة البيانات المنفصلة للمستأجر.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@updateDatabaseConfig`
  * **Form Request:** `App\Http\Requests\UpdateTenantDatabaseConfigRequest`
  * **Action:** `App\Actions\Tenants\UpdateTenantDatabaseConfigAction`
* `POST /api/v1/super-admin/tenants/{id}/toggle-status`:
  * **الوصف:** تعديل حالة المستأجر وتمديد فترة الاشتراك.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@toggleStatus`
  * **Form Request:** `App\Http\Requests\ToggleTenantStatusRequest`
  * **Action:** `App\Actions\Tenants\ToggleTenantStatusAction`
* `DELETE /api/v1/super-admin/tenants/{id}`:
  * **الوصف:** حذف المستأجر وإلغاء ارتباطه بقواعد البيانات.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@destroyTenant`

---

## 5. الحماية الأمنية وعزل البيانات:
* **حماية النطاق المركزي:** التحقق عبر `EnsureCentralContext` و `can:super_admin.access`.
* **عزل التنفيذ (`$tenant->run()`):** عند تنفيذ عمليات على قاعدة بيانات المستأجر مثل الإحصائيات أو الميجريشن، يتم التبديل الآمن عبر Stancl Tenancy وإغلاق السياق بعد الانتهاء.
