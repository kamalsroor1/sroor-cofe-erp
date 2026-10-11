# 🏢 وثيقة المكون والصفحة: إدارة الشركات والمستأجرين المركزية (`SuperAdminTenantsView.vue`)

> **المسار (Route):** `/super-admin/tenants`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantsView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ التحديث الشامل:** 2026-10-11  
> **الحالة المعمارية:** ✅ محدثة وموثقة 100% وفق بنية المنصة المركزية (Control Plane).

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة الشركات والمستأجرين المركزية (`/super-admin/tenants`)** مركز إدارة وتجهيز حسابات المؤسسات المشتركة بالمنظومة المركزية:
1. **سجل المستأجرين والشركات (Tenants Directory):** استعراض المؤسسات مع الدومين والنطاق الفرعي والباقة وبيانات المدير والمسؤول والحالة التشغيلية (`active`, `trial`, `suspended`, `archived`) وحالة التهيئة (`pending`, `running`, `ready`, `failed`).
2. **الفلاتر والبحث المتقدم:** بحث فوري بالاسم أو النطاق أو البريد، تصفية حسب الحالة التشغيلية، وتصفية حسب باقة الاشتراك.
3. **نافذة إنشاء وتهيئة المستأجر (Queued Provisioning Modal):** إدخال اسم المؤسسة، الساب دومين، الباقة، البريد، كلمة المرور، مدة التجربة، وإعدادات MySQL المخصصة. يتم إطلاق وظيفة طابور خلفية (`ProvisionTenantJob`) عبر مدير قواعد البيانات `ProvisionerMySQLDatabaseManager` (عملية غير متداخلة مع معاملة DB لأن إنشاء قاعدة البيانات DDL غير خاضع للـ Transaction).
4. **إعادة محاولة التهيئة (Retry Provisioning):** في حال تعثر التهيئة وظهور حالة `failed`، يتاح للسوبر أدمن إعادة المحاولة عبر زر إجراء يرسل طلب `POST /retry-provisioning` مع حماية 2FA Step-up.
5. **نافذة تعديل الحالة والاشتراك (Status & Extension Modal):** تعديل حالة المستأجر وتمديد فترة الاشتراك بعدد أيام محدد.
6. **الأرشفة الآمنة (Asynchronous Tenant Archive):** الضغط على زر الحذف يرسل `DELETE` إلى `TenantArchiveController` لإنشاء نسخة احتياطية نهائية معتمدة ونقل المستأجر للأرشيف مع إرجاع `202 Accepted` وتطلب تأكيد 2FA، ولا يتم حذف قاعدة البيانات نهائياً من الـ Web مطلقاً (حذف DB مقتصر على أمر CLI حصري `php artisan tenants:purge`).
7. **إلغاء الأرشفة (Unarchive):** استعادة المؤسسة المؤرشفة عبر `POST /unarchive`.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم بناء الصفحة بنمط **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminTenantsView.vue              <-- Thin Orchestrator (~75 lines)
├── Components/SuperAdmin/
│   ├── TenantsFilterBar.vue                   <-- شريط الفلاتر والبحث وقوائم الاختيار
│   ├── TenantsTable.vue                       <-- جدول المستأجرين وبطاقات الهواتف مع الأزرار والشارات
│   ├── CreateTenantModal.vue                  <-- نافذة إنشاء وتهيئة المستأجر (Queued Provisioning)
│   └── EditTenantStatusModal.vue              <-- نافذة تعديل الحالة وتمديد الاشتراك
└── Composables/
    └── useSuperAdminTenants.js                <-- كبسولة المنطق والاتصال بـ centralApi وإدارة الحالات
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة للوحة القيادة وزر إنشاء مستأجر جديد.
* `BaseButton.vue`: أزرار الحفظ والإلغاء مع مؤشرات التحميل.
* `BaseInput.vue`: حقول إدخال اسم المؤسسة والـ Slug والبريد وكلمة المرور وتمديد الأيام.
* `BaseSelect.vue`: قوائم تصفية الحالة والباقة.
* `BaseSearchInput.vue`: حقل البحث الفوري.
* `TableSkeleton.vue`: هيكل التحميل للجداول.
* `EmptyState.vue`: حالة عدم وجود مستأجرين.
* `AppModal.vue`: الحاوية الموحدة للنوافذ المنبثقة.

---

## 4. الاعتماديات والـ APIs المرتبطة (Central Routes `backend/routes/central.php`)

كافة المسارات تعمل تحت وسيط المصادقة المركزية `AuthenticateCentral` وعزل الدومين المركزي `EnsureCentralContext`:

| العملية | الـ Endpoint | الصلاحية المركزية والوسائط | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- | :--- |
| **جلب سجل المستأجرين والباقات** | `GET /api/v1/super-admin/tenants` | `can:super_admin.tenants.view` | `search`, `status`, `plan_id` | قائمة المستأجرين والباقات |
| **إنشاء وتهيئة مستأجر جديد** | `POST /api/v1/super-admin/tenants` | `can:super_admin.tenants.manage` | `name`, `slug`, `email`, `phone`, `password`, `plan_id`, `trial_days` | 201 مع بدء `ProvisionTenantJob` |
| **إعادة محاولة التهيئة** | `POST /api/v1/super-admin/tenants/{id}/retry-provisioning` | `can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | - | 202 عند القبول (409 إذا لم تكن فاشلة) |
| **أرشفة مستأجر آمنة** | `DELETE /api/v1/super-admin/tenants/{id}` | `can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | - | 202 أرشفة مع نسخة احتياطية |
| **إلغاء أرشفة مستأجر** | `POST /api/v1/super-admin/tenants/{id}/unarchive` | `can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | - | 202 استعادة من الأرشيف |
| **تعديل حالة المستأجر وتمديد الاشتراك** | `POST /api/v1/super-admin/tenants/{id}/toggle-status` | `can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | `status`, `extend_days` | تحديث الحالة وتمديد الاشتراك |
| **رفع حدود المعدل المؤقتة** | `POST /api/v1/super-admin/tenants/{id}/rate-limits` | `can:super_admin.tenants.manage`<br>`RequireRecentTwoFactor` | `multiplier`, `expires_in_hours` | 200 تطبيق حد المعدل المؤقت |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * شريط فلاتر مرن، بطاقات لمسية متراصة لكل مستأجر مع أزرار بارتفاع $\ge 40\text{px}$، ونوافذ منبثقة ملائمة للمس.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول مركزي عالي الكثافة مع روابط خارجية للنطاقات وشارات ملونة واضحة لحالة التهيئة والحالة التشغيلية.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال عبر Tailwind dark mode.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php` و `lang/ar/provisioning.php`:
* `super.tenants_page_title`: إدارة المستأجرين والشركات / Tenants Management
* `super.new_tenant_btn`: إنشاء مستأجر جديد / New Tenant
* `super.tenant_org_col`: المستأجر / المؤسسة / Tenant / Organization
* `super.create_tenant_modal_title`: إنشاء وتهيئة مستأجر جديد / Create & Provision Tenant
* `super.edit_tenant_status_title`: تعديل حالة المستأجر / Edit Tenant Status
* `provisioning.statuses.pending`: قيد الانتظار / Pending
* `provisioning.statuses.running`: جاري التهيئة / In Progress
* `provisioning.statuses.ready`: جاهز للعمل / Ready
* `provisioning.statuses.failed`: تعثرت التهيئة / Failed

---

## 7. سجل الاختبارات والتحقق (Test Results & Audit Status)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-tenants-full-page-audit.spec.js` (تتطلب تشغيل خادم التحكم المركزي المحلي).
* ✅ **Feature Tests:**
  * `Tests\Feature\Tenancy\ProvisionTenantJobTest` -> نجاح اختبار طابور التهيئة.
  * `Tests\Feature\Tenancy\ArchiveTenantRequiresFinalBackupTest` -> نجاح التحقق من طلب النسخة الاحتياطية عند الأرشفة.
  * `Tests\Feature\Tenancy\DestroyTenantDoesNotDropDatabaseTest` -> تأكيد عدم حذف DB عبر الويب.
  * `Tests\Feature\Tenancy\TenantProvisioningGuardPinningTest` -> نجاح حماية مسار إعادة التهيئة.
* ⚠️ **غير متحقق:** التحقق البصري المباشر على خادم الإنتاج (محظور بموجب قواعد حماية البيئة الحية).
