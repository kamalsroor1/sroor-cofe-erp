# 👑 وثيقة المكون والصفحة: لوحة تحكم السوبر أدمن المركزية (`SuperAdminDashboardView.vue`)

> **المسار (Route):** `/super-admin`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminDashboardView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ التحديث الشامل:** 2026-10-11  
> **الحالة المعمارية:** ✅ محدثة وموثقة 100% وفق بنية المنصة المركزية (`backend/routes/central.php`).

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **لوحة تحكم السوبر أدمن المركزية (`/super-admin`)** مركز القيادة والتحكم لمنظومة الـ Multi-Tenant SaaS المدارة عبر مصادقة مركزية مستقلة (`AuthenticateCentral`):
1. **مؤشرات المنصة الحيوية (Platform KPI Metrics):** إجمالي المستأجرين، المستأجرين النشطين، الحسابات التجريبية، الحسابات الموقوفة، والإيراد الشهري المتكرر (MRR).
2. **توزيع الباقات والاشتراكات (Plans Distribution):** عدد المشتركين في كل باقة مع الأسعار الشهرية.
3. **أحدث المستأجرين المسجلين (Recent Tenants Stream):** أحدث المستأجرين مع الدومين والباقة وحالة الاشتراك وتاريخ الإنشاء.
4. **إعدادات الهوية والمنصة المركزية (Platform Settings & Whitelabel):** اسم المنصة، الوصف، بريد الدعم، رقم الهاتف، والأصول الرقمية (`logo_light`, `logo_dark`, `favicon`, `app_icon`) مع حماية التعديل بـ 2FA Step-up.
5. **معلومات بيئة السيرفر والنظام المركزي (Central Server Specs):** إصدار PHP، إصدار Laravel، محرك قاعدة البيانات MySQL، وبيئة التشغيل Environment.
6. **رابط المراقبة الآمن (Telescope / Monitoring Bridge):** توليد رابط مؤقت مشفر لدخول Telescope و Horizon عبر `POST /telescope-link`.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم بناء الصفحة بنمط **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminDashboardView.vue            <-- Thin Orchestrator (~75 lines)
├── Components/SuperAdmin/
│   ├── SuperAdminMetricsGrid.vue              <-- بطاقات المؤشرات الخمسة للمنصة
│   ├── SuperAdminPlansDistribution.vue        <-- بطاقة توزيع الباقات والمشتركين والأسعار
│   ├── SuperAdminRecentTenants.vue            <-- بطاقة أحدث المستأجرين المسجلين وحالاتهم
│   ├── SuperAdminPlatformSettingsCard.vue     <-- بطاقة إعدادات المنصة المركزية والهوية
│   └── SuperAdminServerSpecsCard.vue          <-- بطاقة مواصفات السيرفر المركزي (PHP, Laravel, MySQL, Env)
└── Composables/
    └── useSuperAdminDashboard.js              <-- كبسولة المنطق وجلب المؤشرات عبر centralApi
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة الموحدة وأزرار الانتقال السريع لإدارة المستأجرين والباقات.
* `BaseButton.vue`: زر حفظ إعدادات المنصة مع مؤشرات التحميل.
* `BaseInput.vue`: حقول إدخال اسم المنصة والوصف وبريد وهاتف الدعم.
* `StatCardSkeleton.vue`: هياكل تحميل وميضية لبطاقات المؤشرات.

---

## 4. الاعتماديات والـ APIs المرتبطة (Central Routes `backend/routes/central.php`)

كافة المسارات تعمل تحت وسيط المصادقة المركزية `AuthenticateCentral` وعزل الدومين المركزي `EnsureCentralContext`:

| العملية | الـ Endpoint | الكنترولر والوسائط | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- | :--- |
| **جلب مؤشرات لوحة السوبر أدمن** | `GET /api/v1/super-admin/dashboard` | `SuperAdminApiController`<br>`can:super_admin.dashboard.view` | - | المؤشرات، إحصائيات الباقات، أحدث المستأجرين، وبيانات السيرفر |
| **جلب إعدادات المنصة والهوية** | `GET /api/v1/super-admin/platform-settings` | `PlatformSettingsController`<br>`can:super_admin.settings.view` | - | إعدادات المنصة وأصول العلامة التجارية |
| **حفظ وتحديث إعدادات المنصة** | `PUT /api/v1/super-admin/platform-settings` | `PlatformSettingsController`<br>`can:super_admin.settings.manage`<br>`RequireRecentTwoFactor` | `name`, `short_name`, `support_email`, `support_phone` | 200 تحديث إعدادات المنصة |
| **توليد رابط المراقبة الآمن** | `POST /api/v1/super-admin/telescope-link` | `TelescopeLinkController`<br>`can:super_admin.monitoring.view` | - | 200 رابط دخول مؤقت موقع |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي لبطاقات المؤشرات، بطاقات المستأجرين والباقات متراصة، حقول إدخال وأزرار بارتفاع $\ge 40\text{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * شبكة خماسية لبطاقات المؤشرات، تقسيم 1/3 لتوزيع الباقات و 2/3 لأحدث المستأجرين، وشبكة رباعية لمعلومات السيرفر.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال عبر Tailwind dark mode.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.super_admin_title`: لوحة تحكم السوبر أدمن / Super Admin Dashboard
* `super.total_tenants`: إجمالي الشركات المسجلة / Total Registered Tenants
* `super.active_tenants`: المستأجرين النشطين / Active Tenants
* `super.mrr`: الإيراد الشهري المتكرر (MRR) / Monthly Recurring Revenue
* `super.platform_settings_title`: الهوية واسم المنصة العام / Platform Whitelabel & Branding

---

## 7. سجل الاختبارات والتحقق (Test Results & Audit Status)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-dashboard-full-page-audit.spec.js` (تتطلب تشغيل خادم التحكم المركزي المحلي).
* ✅ **Feature Tests:**
  * `Tests\Feature\Platform\HorizonCentralWebGuardTest` -> نجاح حماية لوحة المراقبة.
  * `Tests\Feature\Platform\TelescopeCentralWebGuardTest` -> نجاح حماية جسر Telescope.
* ⚠️ **غير متحقق:** الاتصال بقواعد البيانات الحية للسوبر أدمن على الإنتاج (محظور بموجب قواعد حماية السيرفر الحي).
