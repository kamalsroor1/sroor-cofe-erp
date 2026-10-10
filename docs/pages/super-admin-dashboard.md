# 👑 وثيقة المكون والصفحة: لوحة تحكم السوبر أدمن المركزية (`SuperAdminDashboardView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** لوحة تحكم السوبر أدمن (Super Admin Dashboard)
* **المسار (Route):** `/super-admin/dashboard`
* **اسم المسار (Route Name):** `super_admin.dashboard`
* **الصلاحية المطلوبة (Permission):** `super_admin.access` (أو `super_admin.dashboard.view` لمستخدمي مشغلي المنصة `CentralUser`).
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminDashboardView.vue` (~83 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * مركز القيادة والتحكم لمنظومة المنصة متعددة المستأجرين (SaaS Control Plane).
  * مراقبة المؤشرات التشغيلية الحية: إجمالي المستأجرين، النشطين، الحسابات التجريبية، والموقوفة، وتقديرات الإيراد الشهري المتكرر (MRR).
  * استعراض توزيع الباقات والاشتراكات وعدد المشتركين في كل باقة.
  * متابعة أحدث المستأجرين المسجلين في المنصة وحالاتهم ونطاقاتهم.
  * إدارة إعدادات الهوية والمنصة المركزية (Platform White-labeling & Support Contacts).
  * استعراض مواصفات وبيئة الخادم المركزي (PHP, Laravel, MySQL, Environment).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SuperAdminDashboardView.vue (~83 lines)
├── PageHeader.vue                         <-- رأس الصفحة مع أزرار الإجراءات السريعة
├── SuperAdminMetricsGrid.vue              <-- شبكة بطاقات مؤشرات المنصة الخمسة
├── SuperAdminPlansDistribution.vue        <-- توزيع الباقات والمشتركين والإيرادات
├── SuperAdminRecentTenants.vue            <-- قائمة أحدث المستأجرين المسجلين
├── SuperAdminPlatformSettingsCard.vue     <-- نموذج إعدادات المنصة المركزية وبيانات الدعم
└── SuperAdminServerSpecsCard.vue          <-- بطاقة مواصفات السيرفر المركزي والبيئة
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `StatCardSkeleton.vue`.
* **القالب العام:** `SuperAdminLayout.vue` المخصص لإدارة المنصة المركزية والمفصول تماماً عن قوالب المستأجرين.
* **الـ Composable:** `useSuperAdminDashboard.js` لجلب مؤشرات لوحة السوبر أدمن وتحديث إعدادات المنصة.
* **المخازن المستخدمة:** `useAuthStore` للتحقق من صلاحيات السوبر أدمن وتوجيه مسارات الإدارة المركزية.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/super-admin/dashboard`:
  * **الوصف:** جلب مؤشرات المنصة، توزيع الباقات، أحدث المستأجرين، ومواصفات السيرفر.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@dashboard`
  * **الخدمة:** `App\Contracts\SuperAdminDashboardAnalyticsInterface`
* `GET /api/v1/super-admin/settings`:
  * **الوصف:** جلب إعدادات المنصة المركزية وهوية النظام.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@getPlatformSettings`
* `POST /api/v1/super-admin/settings`:
  * **الوصف:** تحديث إعدادات المنصة المركزية (اسم المنصة، الوصف، بريد وهاتف الدعم الفني).
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@updatePlatformSettings`
  * **Form Request:** `App\Http\Requests\UpdatePlatformSettingsRequest`

---

## 5. الحماية الأمنية وعزل المنصة المركزية (Central Context Isolation):
* **عزل النطاق المركزي (`EnsureCentralContext`):** كافة مسارات السوبر أدمن محمية بـ Middleware يمنع طلبها من نطاقات المستأجرين (إذا كان الطلب من نطاق مستأجر يعيد `404 Not Found` بدلاً من `401/403` لمنع استكشاف المسارات).
* **معمارية المصادقة المركزية والـ 2FA:** تدعم المنصة مصادقة مشغلي المنصة المركزية `CentralUser` عبر `routes/central.php` مع التحقق الثنائي الإلزامي (Mandatory 2FA) والرموز البديلة (`recovery-codes`) وإمكانية طلب التوثيق الإضافي (`step-up`).
* **الاتصال المركزي:** النماذج المركزية تستخدم الاتصال المركزي `central` لضمان عدم خلط البيانات المركزية مع قواعد بيانات المستأجرين.
