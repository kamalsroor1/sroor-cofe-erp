# 🏢 وثيقة المكون والصفحة: إدارة الشركات والمستأجرين المركزية (`SuperAdminTenantsView.vue`)

> **المسار (Route):** `/super-admin/tenants`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantsView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة الشركات والمستأجرين المركزية (`/super-admin/tenants`)** مركز إدارة وتجهيز حسابات المؤسسات المشتركة بالمنظومة:
1. **سجل المستأجرين والشركات (Tenants Directory):** استعراض المؤسسات مع الدومين والنطاق الفرعي والباقة وبيانات المدير والمسؤول والحالة وتاريخ الإنشاء.
2. **الفلاتر والبحث المتقدم:** بحث فوري بالاسم أو النطاق أو البريد، تصفية حسب الحالة (نشط، تجريبي، موقوف)، وتصفية حسب باقة الاشتراك.
3. **نافذة إنشاء وتهيئة المستأجر (Auto-Provisioning Modal):** إدخال اسم المؤسسة، الساب دومين، الباقة، البريد، كلمة المرور، مدة التجربة، وإعدادات MySQL المخصصة.
4. **نافذة تعديل الحالة والاشتراك (Status & Extension Modal):** تعديل حالة المستأجر وتمديد فترة الاشتراك بعدد أيام محدد.
5. **الحذف والإلغاء الآمن:** حذف المستأجر وإلغاء ربط النطاق وقاعدة البيانات.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 584 سطراً إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminTenantsView.vue              <-- Thin Orchestrator (~75 lines)
├── Components/SuperAdmin/
│   ├── TenantsFilterBar.vue                   <-- شريط الفلاتر والبحث وقوائم الاختيار
│   ├── TenantsTable.vue                       <-- جدول المستأجرين وبطاقات الهواتف مع الأزرار
│   ├── CreateTenantModal.vue                  <-- نافذة AppModal لإنشاء وتهيئة المستأجر وقاعدة البيانات
│   └── EditTenantStatusModal.vue              <-- نافذة AppModal لتعديل الحالة وتمديد الاشتراك
└── Composables/
    └── useSuperAdminTenants.js                <-- كبسولة المنطق والاتصال بالـ API وإدارة الحالات
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

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب سجل المستأجرين والباقات** | `GET /api/v1/super-admin/tenants` | `search`, `status`, `plan_id` | قائمة المستأجرين والباقات |
| **إنشاء وتهيئة مستأجر جديد** | `POST /api/v1/super-admin/tenants` | `name`, `slug`, `email`, `phone`, `password`, `plan_id`, `trial_days`, `tenancy_db_name`, `tenancy_db_username`, `tenancy_db_password` | إنشاء الحساب وقاعدة البيانات |
| **تعديل حالة المستأجر وتمديد الاشتراك** | `POST /api/v1/super-admin/tenants/{id}/toggle-status` | `status`, `extend_days` | تحديث الحالة وتمديد الاشتراك |
| **حذف مستأجر** | `DELETE /api/v1/super-admin/tenants/{id}` | - | حذف المستأجر وإلغاء الربط |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * شريط فلاتر مرن، بطاقات لمسية متراصة لكل مستأجر مع أزرار بارتفاع $\ge 40	ext{px}$، ونوافذ منبثقة ملائمة للمس.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول مركزي عالي الكثافة مع روابط خارجية للنطاقات وشارات ملونة واضحة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.tenants_page_title`: إدارة المستأجرين والشركات / Tenants Management
* `super.new_tenant_btn`: إنشاء مستأجر جديد / New Tenant
* `super.tenant_org_col`: المستأجر / المؤسسة / Tenant / Organization
* `super.create_tenant_modal_title`: إنشاء وتهيئة مستأجر جديد / Create & Provision Tenant
* `super.edit_tenant_status_title`: تعديل حالة المستأجر / Edit Tenant Status

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-tenants-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.93 ثانية.
