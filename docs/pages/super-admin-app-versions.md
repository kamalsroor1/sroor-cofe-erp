# 🚀 وثيقة المكون والصفحة: إدارة إصدارات التطبيق وحزم APK (`SuperAdminAppVersionsView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إدارة إصدارات التطبيق وحزم APK (App Versions & APK Releases)
* **المسار (Route):** `/super-admin/app-versions`
* **اسم المسار (Route Name):** `super_admin.app_versions`
* **الصلاحية المطلوبة (Permission):** `super_admin.access` (أو `super_admin.app_versions.view` / `super_admin.app_versions.manage` لمشغلي المنصة المركزية `CentralUser`).
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminAppVersionsView.vue` (~78 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة تحديثات التطبيقات المصاحبة عبر الهواء (OTA Updates) لتطبيقات أندرويد (Capacitor) وديسكتوب (Electron).
  * متابعة مؤشرات الإصدارات: الإصدار الفعال حالياً (`active_version`)، إجمالي التنزيلات (`total_downloads`)، وإجمالي الإصدارات المنشورة (`total_releases`).
  * جدول وسجل الحزم المنشورة: رقم الإصدار (`version_name`)، كود الإصدار (`version_code`)، المنصة (`platform`: android, windows, ios)، الحد الأدنى المطلوب (`min_version_code`)، نوع التحديث (إلزامي `is_force_update` أو اختياري)، ملاحظات الإصدار، وتاريخ النشر.
  * إمكانية تفعيل/تعطيل الحزم بنقرة واحدة، وتنزيل ملف APK أو حذفه.
  * نافذة رفع ونشر حزمة APK جديدة مع رفع الملف والتحقق من حجمه ونوعه.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SuperAdminAppVersionsView.vue (~78 lines)
├── PageHeader.vue                     <-- رأس الصفحة مع شارة OTA Updater وزر رفع حزمة جديدة
├── AppVersionsSummaryGrid.vue         <-- بطاقات المؤشرات الثلاثية للإصدارات والتحميلات
├── AppVersionsTable.vue               <-- جدول وسجل الحزم وبطاقات الهواتف مع الشارات والإجراءات
└── UploadApkModal.vue                 <-- نافذة رفع ونشر إصدار APK جديد وتحديد مواصفاته
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **القالب العام:** `SuperAdminLayout.vue`.
* **الـ Composable:** `useSuperAdminAppVersions.js` لإدارة جلب الإصدارات ورفع الحزم وتغيير حالات التفعيل والحذف.
* **المخازن المستخدمة:** `useAuthStore` للتحقق من صلاحية السوبر أدمن.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/super-admin/app-versions`:
  * **الوصف:** جلب قائمة الإصدارات المنشورة مع ملخص التنزيلات والإصدار النشط.
  * **الكنترولر:** `App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAppVersionController@index`
* `POST /api/v1/super-admin/app-versions`:
  * **الوصف:** رفع ونشر إصدار APK جديد.
  * **الكنترولر:** `App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAppVersionController@store`
  * **Form Request:** `App\Http\Requests\AppVersions\StoreAppVersionRequest`
  * **Action:** `App\Actions\AppVersions\CreateAppVersionAction`
  * **DTO:** `App\DTOs\AppVersions\StoreAppVersionDTO`
* `PATCH /api/v1/super-admin/app-versions/{appVersion}/toggle-active`:
  * **الوصف:** تبديل حالة تفعيل الإصدار بين نشط ومعطل.
  * **الكنترولر:** `App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAppVersionController@toggleActive`
* `DELETE /api/v1/super-admin/app-versions/{appVersion}`:
  * **الوصف:** حذف سجل الإصدار وحذف ملف الـ APK المرتبط به من وحدة التخزين.
  * **الكنترولر:** `App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAppVersionController@destroy`

---

## 5. الحماية الأمنية وعزل المنصة:
* **حماية النطاق المركزي:** المسارات محمية بـ `EnsureCentralContext` و `can:super_admin.access`.
* **الربط المركزي (`AppVersion`):** موديل `AppVersion` يرتبط حصراً بقاعدة البيانات المركزية `central` عبر `getConnectionName()`.
* **أمان رفع الملفات:** التحقق الصارم من امتداد الملف `.apk` ونوع الـ MIME وفصل مسار التخزين عن المستأجرين.
