# 🏪 وثيقة المكون والصفحة: تفاصيل المستأجر والتحكم والمحاكاة (`SuperAdminTenantShowView.vue`)

> **المسار (Route):** `/super-admin/tenants/:id`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantShowView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ التحديث الشامل:** 2026-10-11  
> **الحالة المعمارية:** ✅ محدثة وموثقة 100% وفق بنية المنصة المركزية (Control Plane).

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **تفاصيل المستأجر والتحكم والمحاكاة (`/super-admin/tenants/:id`)** لوحة الإشراف المباشر والتحكم الدقيق في حساب المؤسسة الفردية:
1. **رأس الصفحة التنفيذي والتنقل السريع:** عرض اسم المؤسسة، الهوية، الدومين، الباقة، الحالة، وأزرار الإجراءات السريعة (دخول كأدمن Impersonate، تعديل الحالة والاشتراك، تشغيل الميجريشن المركزي لقواعد البيانات، وإجراء الأرشفة النهائية).
2. **شبكة المؤشرات التشغيلية الحية (Live Operational Stats Grid):** إجمالي المستخدمين، عدد الفروع والمخازن، عدد الأصناف، فواتير المبيعات، وإجمالي حجم العمليات المالية.
3. **تخصيص وحدات القياس المعتمدة للمستأجر (Allowed Units Management):** استعراض وإضافة وحذف وحفظ وحدات القياس الخاصة بحساب المستأجر عبر `PlatformUnitsController`، مع إمكانية الإضافة السريعة من القوالب العامة أو كتابة وحدة جديدة مخصصة.
4. **مصفوفة المميزات الاستثنائية والـ Overrides (Feature Flags Matrix):** تفعيل أو تعطيل مميزات الباقة بشكل استثنائي للمستأجر وحفظها فورياً.
5. **نافذة تعديل الحالة وتمديد الاشتراك (Status & Subscription Modal):** تعديل حالة الحساب وتمديد فترة الاشتراك بعدد أيام محدد.
6. **الحماية المشددة بالمصادقة الثنائية (2FA Step-Up):** كافة العمليات الحساسة (تعديل الحالة، تغيير المميزات، تشغيل الميجريشن، الأرشفة، وتعديل الوحدات) تتطلب توكن 2FA حديث (خلال آخر 15 دقيقة) عبر وسيط `RequireRecentTwoFactor`.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم بناء الصفحة بنمط **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminTenantShowView.vue           <-- Thin Orchestrator (~75 lines)
├── Components/SuperAdmin/
│   ├── TenantShowHeader.vue                   <-- الرأس التنفيذي مع شارات الحالة والمعرف والأزرار
│   ├── TenantStatsGrid.vue                    <-- شبكة المؤشرات التشغيلية الحية
│   ├── TenantUnitsCard.vue                    <-- بطاقة إدارة وتخصيص وحدات القياس المعتمدة
│   ├── TenantFeaturesMatrixCard.vue           <-- بطاقة مصفوفة الميزات والـ Feature Flags
│   └── TenantStatusModal.vue                  <-- نافذة AppModal لتعديل الحالة وتمديد الاشتراك
└── Composables/
    └── useSuperAdminTenantShow.js             <-- كبسولة المنطق وإدارة البيانات والعمليات عبر centralApi
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `TenantShowHeader.vue`: الرأس التنفيذي مع شارات الحالة، المعرف، الرابط الخارجي، وأزرار التحكم.
* `BaseButton.vue`: أزرار الحفظ والإلغاء والدخول والتحديث والميجريشن.
* `BaseInput.vue`: حقول إدخال الوحدة المخصصة وتمديد الأيام.
* `AppModal.vue`: الحاوية الموحدة للنوافذ المنبثقة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ والحذف.

---

## 4. الاعتماديات والـ APIs المرتبطة (Central Routes `backend/routes/central.php`)

كافة المسارات تعمل تحت وسيط المصادقة المركزية `AuthenticateCentral` وعزل الدومين المركزي `EnsureCentralContext`:

| العملية | الـ Endpoint | الكنترولر والوسائط | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- | :--- |
| **جلب تفاصيل المستأجر والإحصائيات** | `GET /api/v1/super-admin/tenants/{id}` | `SuperAdminApiController`<br>`can:super_admin.tenants.view` | - | بيانات المستأجر، المؤشرات، الميزات، والوحدات |
| **تعديل ميزة محددة استثنائياً** | `POST /api/v1/super-admin/tenants/{id}/override-feature` | `SuperAdminApiController`<br>`can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | `feature_key` | تحديث مصفوفة الميزات |
| **تحديث وحفظ وحدات القياس** | `POST /api/v1/super-admin/tenants/{id}/update-units` | `PlatformUnitsController`<br>`can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | `units` | حفظ قائمة الوحدات المعتمدة |
| **تشغيل الميجريشن للمستأجر** | `POST /api/v1/super-admin/tenants/{id}/run-migrations` | `SuperAdminApiController`<br>`can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | - | 200 تنفيذ الميجريشن |
| **تعديل الحالة وتمديد الاشتراك** | `POST /api/v1/super-admin/tenants/{id}/toggle-status` | `SuperAdminApiController`<br>`can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | `status`, `extend_days` | تحديث الحالة وتمديد الاشتراك |
| **الأرشفة الآمنة للمستأجر** | `DELETE /api/v1/super-admin/tenants/{id}` | `TenantArchiveController`<br>`can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | - | 202 أرشفة مع نسخة احتياطية نهائية |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي للرأس التنفيذي، شبكة المؤشرات موزعة بمرونة، بطاقات الميزات والوحدات ملائمة للمس مع أزرار بارتفاع $\ge 40\text{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * تقسيم عمودين 6/6 لوحدات القياس ومصفوفة الميزات، شبكة خماسية للمؤشرات.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال عبر Tailwind dark mode.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.tenant_details_title`: بيانات وإحصائيات المستأجر / Tenant Details & Statistics
* `super.impersonate_btn`: دخول كأدمن / Impersonate Admin
* `super.run_migrations_btn`: تحديث الميجريشن / Run Migrations
* `super.tenant_units_title`: وحدات القياس المعتمدة للمستأجر / Tenant Measurement Units
* `super.custom_features_title`: المميزات والخصائص المخصصة / Custom Features & Overrides
* `super.status_and_plan_modal_title`: تعديل حالة واشتراك المستأجر / Edit Tenant Status & Subscription

---

## 7. سجل الاختبارات والتحقق (Test Results & Audit Status)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-tenant-show-full-page-audit.spec.js` (تتطلب تشغيل خادم التحكم المركزي المحلي).
* ✅ **Feature Tests:**
  * `Tests\Feature\Platform\PlatformSuperAdminTest` -> نجاح اختبار الصلاحيات المركزية.
  * `Tests\Feature\Tenancy\ImpersonationLeaveRedirectTest` -> نجاح المحاكاة والمغادرة.
* ⚠️ **غير متحقق:** محاكاة الدخول كأدمن على بيئة إنتاجية حقيقية (محظور بموجب قواعد حماية السيرفر الحي).
