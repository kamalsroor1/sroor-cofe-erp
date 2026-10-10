# 🏢 وثيقة المكون والصفحة: إدارة المستأجرين والشركات المركزية (`SuperAdminTenantsView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إدارة المستأجرين (Tenants Management)
* **المسار (Route):** `/super-admin/tenants`
* **اسم المسار (Route Name):** `super_admin.tenants`
* **الصلاحية المطلوبة (Permission):** `super_admin.access` (أو `super_admin.tenants.view` / `super_admin.tenants.manage` لمشغلي المنصة المركزية `CentralUser`).
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantsView.vue` (~88 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة وتجهيز حسابات المؤسسات والشركات المشتركة في نظام ERP متعدد المستأجرين.
  * استعراض دليل المستأجرين مع النطاق الفرعي (`slug`)، النطاقات المرتبطة (`domains`)، باقة الاشتراك، بيانات المسؤول الإداري، وتاريخ الإنشاء وحالة الاشتراك.
  * فلترة وبحث متعدد المعايير (بحث بالاسم أو النطاق أو البريد، فلترة بالحالة: نشط، تجريبي، موقوف، وفلترة بالباقة).
  * إنشاء وتجهيز المستأجر آلياً (`Auto-Provisioning`): إنشاء سجل المستأجر، ربط الدومين، إنشاء قاعدة البيانات الخاصة به، تشغيل التهجيرات وتعيين المستخدم الإداري الأول.
  * تعديل حالة المستأجر وتمديد فترة الاشتراك بعدد أيام محدد.
  * حذف المستأجر نهائياً وإلغاء ارتباطاته.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SuperAdminTenantsView.vue (~88 lines)
├── PageHeader.vue               <-- رأس الصفحة مع زر إنشاء مستأجر جديد وأزرار التنقل
├── TenantsFilterBar.vue         <-- شريط الفلاتر والبحث وتصفية الباقات والحالات
├── TenantsTable.vue             <-- جدول المستأجرين وبطاقات الهواتف مع الإجراءات
├── CreateTenantModal.vue        <-- نافذة تجهيز مستأجر جديد (بيانات المؤسسة، الباقة، وقاعدة البيانات)
└── EditTenantStatusModal.vue    <-- نافذة تعديل الحالة وتمديد فترة الاشتراك
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseSelect.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **القالب العام:** `SuperAdminLayout.vue` المفصول عن سياق المستأجرين.
* **الـ Composable:** `useSuperAdminTenants.js` لإدارة جلب المستأجرين والفلاتر وفتح النوافذ المنبثقة وتنفيذ عمليات الحفظ والتعديل.
* **المخازن المستخدمة:** `useAuthStore` للتحقق من صلاحيات إدارة المستأجرين المركزية.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/super-admin/tenants`:
  * **الوصف:** جلب قائمة المستأجرين مع الباقات وترقيم الصفحات.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@tenants`
  * **Action:** `App\Actions\Tenants\GetTenantsIndexDataAction`
* `POST /api/v1/super-admin/tenants`:
  * **الوصف:** تجهيز وإنشاء مستأجر جديد وقاعدة بياناته.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@storeTenant`
  * **Form Request:** `App\Http\Requests\StoreTenantRequest`
  * **Action:** `App\Actions\Tenants\ProvisionTenantAction`
  * **DTO:** `App\DTOs\CreateTenantDTO`
* `POST /api/v1/super-admin/tenants/{id}/toggle-status`:
  * **الوصف:** تعديل حالة المستأجر (تفعيل، إيقاف، تمديد الاشتراك).
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@toggleStatus`
  * **Form Request:** `App\Http\Requests\ToggleTenantStatusRequest`
  * **Action:** `App\Actions\Tenants\ToggleTenantStatusAction`
* `DELETE /api/v1/super-admin/tenants/{id}`:
  * **الوصف:** حذف المستأجر وإلغاء تهيئته.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@destroyTenant`

---

## 5. الحماية الأمنية وعزل البيانات (Control Plane Isolation):
* **حماية النطاق المركزي:** الوصول محمي بـ `EnsureCentralContext` و `can:super_admin.access`.
* **عزل الاتصال المركزي:** موديلات `Tenant` و `Domain` و `Plan` موجهة دائماً إلى قاعدة البيانات المركزية `central`.
* **سلامة التجهيز:** تنفيذ عملية التهيئة عبر `TenantProvisionerService` داخل Transaction آمنة تشمل إنشاء قاعدة البيانات وتشغيل Seeders الأولية.
