# 🏪 وثيقة المكون والصفحة: تفاصيل المستأجر والتحكم والمحاكاة (`SuperAdminTenantShowView.vue`)

> **المسار (Route):** `/super-admin/tenants/:id`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantShowView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% وفق المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **تفاصيل المستأجر والتحكم والمحاكاة (`/super-admin/tenants/:id`)** لوحة الإشراف المباشر والتحكم الدقيق في حساب المؤسسة الفردية:
1. **رأس الصفحة التنفيذي والتنقل السريع:** عرض اسم المؤسسة، الهوية، الدومين، الباقة، الحالة، وأزرار الإجراءات السريعة (دخول كأدمن Impersonate، تعديل الحالة والاشتراك، تشغيل الميجريشن المركزي وقواعد البيانات، وحذف المستأجر).
2. **شبكة المؤشرات التشغيلية الحية (Live Operational Stats Grid):** إجمالي المستخدمين، عدد الفروع والمخازن، عدد الأصناف، فواتير المبيعات، وإجمالي حجم العمليات المالية.
3. **تخصيص وحدات القياس المعتمدة للمستأجر (Allowed Units Management):** استعراض وإضافة وحذف وحفظ وحدات القياس الخاصة بحساب المستأجر، مع إمكانية الإضافة السريعة من القوالب العامة أو كتابة وحدة جديدة مخصصة.
4. **مصفوفة المميزات الاستثنائية والـ Overrides (Feature Flags Matrix):** تفعيل أو تعطيل مميزات الباقة بشكل استثنائي للمستأجر وحفظها فورياً.
5. **نافذة تعديل الحالة وتمديد الاشتراك (Status & Subscription Modal):** تعديل حالة الحساب وتمديد فترة الاشتراك بعدد أيام محدد.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 571 سطراً إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

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
    └── useSuperAdminTenantShow.js             <-- كبسولة المنطق وإدارة البيانات والعمليات
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `TenantShowHeader.vue`: الرأس التنفيذي مع شارات الحالة، المعرف، الرابط الخارجي، وأزرار التحكم.
* `BaseButton.vue`: أزرار الحفظ والإلغاء والدخول والتحديث والميجريشن.
* `BaseInput.vue`: حقول إدخال الوحدة المخصصة وتمديد الأيام.
* `AppModal.vue`: الحاوية الموحدة للنوافذ المنبثقة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ والحذف.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب تفاصيل المستأجر والإحصائيات** | `GET /api/v1/super-admin/tenants/{id}` | - | بيانات المستأجر، المؤشرات، الميزات، والوحدات |
| **تعديل ميزة محددة استثنائياً** | `POST /api/v1/super-admin/tenants/{id}/override-feature` | `feature_key` | تحديث مصفوفة الميزات |
| **تحديث وحفظ وحدات القياس** | `POST /api/v1/super-admin/tenants/{id}/update-units` | `units` | حفظ قائمة الوحدات المعتمدة |
| **تشغيل الميجريشن للمستأجر** | `POST /api/v1/super-admin/tenants/{id}/run-migrations` | - | تنفيذ ملفات الميجريشن على قاعدة بيانات المستأجر |
| **تعديل الحالة وتمديد الاشتراك** | `POST /api/v1/super-admin/tenants/{id}/toggle-status` | `status`, `extend_days` | تحديث الحالة وتمديد الاشتراك |
| **حذف المستأجر** | `DELETE /api/v1/super-admin/tenants/{id}` | - | حذف المستأجر نهائياً |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي للرأس التنفيذي، شبكة المؤشرات موزعة بمرونة، بطاقات الميزات والوحدات ملائمة للمس مع أزرار بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * تقسيم عمودين 6/6 لوحدات القياس ومصفوفة الميزات، شبكة خماسية للمؤشرات.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

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

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-tenant-show-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 5.24 ثانية.
