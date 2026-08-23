# 👑 وثيقة المكون والصفحة: لوحة تحكم السوبر أدمن المركزية (`SuperAdminDashboardView.vue`)

> **المسار (Route):** `/super-admin`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminDashboardView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **لوحة تحكم السوبر أدمن المركزية (`/super-admin`)** مركز القيادة والتحكم لمنظومة الـ Multi-Tenant SaaS:
1. **مؤشرات المنصة الحيوية (Platform KPI Metrics):** إجمالي المستأجرين، المستأجرين النشطين، الحسابات التجريبية، الحسابات الموقوفة، والإيراد الشهري المتكرر (MRR).
2. **توزيع الباقات والاشتراكات (Plans Distribution):** عدد المشتركين في كل باقة مع الأسعار الشهرية.
3. **أحدث المستأجرين المسجلين (Recent Tenants Stream):** أحدث المستأجرين مع الدومين والباقة وحالة الاشتراك وتاريخ الإنشاء.
4. **إعدادات الهوية والمنصة المركزية (Platform Branding & Whitelabel):** اسم المنصة، الوصف، بريد الدعم، ورقم هاتف الدعم الفني مع تحديث لحظي لـ Pinia Store.
5. **معلومات بيئة السيرفر والنظام المركزي (Central Server Specs):** إصدار PHP، إصدار Laravel، محرك قاعدة البيانات MySQL، وبيئة التشغيل Environment.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 425 سطراً إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

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
    └── useSuperAdminDashboard.js              <-- كبسولة المنطق وجلب المؤشرات وتحديث الإعدادات
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة الموحدة وأزرار الانتقال السريع لإدارة المستأجرين والباقات.
* `BaseButton.vue`: زر حفظ إعدادات المنصة مع مؤشرات التحميل.
* `BaseInput.vue`: حقول إدخال اسم المنصة والوصف وبريد وهاتف الدعم.
* `StatCardSkeleton.vue`: هياكل تحميل وميضية لبطاقات المؤشرات.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب مؤشرات لوحة السوبر أدمن** | `GET /api/v1/super-admin/dashboard` | - | المؤشرات، إحصائيات الباقات، أحدث المستأجرين، وبيانات السيرفر |
| **جلب إعدادات المنصة المركزية** | `GET /api/v1/super-admin/settings` | - | اسم المنصة، الوصف، وبيانات الدعم |
| **حفظ إعدادات المنصة والهوية** | `POST /api/v1/super-admin/settings` | `platform_name`, `platform_subtitle`, `support_email`, `support_phone` | حفظ الإعدادات وتحديث الهوية المركزية |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي لبطاقات المؤشرات، بطاقات المستأجرين والباقات متراصة، حقول إدخال وأزرار بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * شبكة خماسية لبطاقات المؤشرات، تقسيم 1/3 لتوزيع الباقات و 2/3 لأحدث المستأجرين، وشبكة رباعية لمعلومات السيرفر.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.super_admin_title`: لوحة تحكم السوبر أدمن / Super Admin Dashboard
* `super.total_tenants`: إجمالي الشركات المسجلة / Total Registered Tenants
* `super.active_tenants`: المستأجرين النشطين / Active Tenants
* `super.mrr`: الإيراد الشهري المتكرر (MRR) / Monthly Recurring Revenue
* `super.platform_settings_title`: الهوية واسم المنصة العام / Platform Whitelabel & Branding

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-dashboard-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.16 ثانية.
