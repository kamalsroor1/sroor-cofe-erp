# سجل تعديل: فصل تسجيل دخول السوبر أدمن عن المستأجرين وميزة الدخول السريع (Tenant Impersonation)

* **التاريخ والوقت:** 2026-08-20 17:00
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** فصل شاشة ومسارات تسجيل دخول السوبر أدمن عن المستأجرين تماماً، ومنع التداخل بين الحسابات، وتوفير ميزة الدخول الفوري كمسؤول أي متجر (Tenant Impersonation) بضغطة زر واحدة.

---

## 1. الملفات المعدلة والمنشأة:
* `[NEW]` `backend/database/migrations/2026_08_20_170000_create_tenant_user_impersonation_tokens_table.php` - جدول رموز الدخول السريع المشفرة.
* `[NEW]` `backend/app/Actions/SuperAdmin/ImpersonateTenantAction.php` - إجراء توليد رابط الدخول السريع الآمن لأي متجر.
* `[NEW]` `backend/app/Http/Controllers/Auth/SuperAdminAuthController.php` - متحكم مصادقة السوبر أدمن المخصص للمنصة المركزية.
* `[NEW]` `backend/resources/js/Pages/SuperAdmin/Auth/Login.vue` - شاشة تسجيل دخول السوبر أدمن الفاخرة (Dark Slate & Purple Hub).
* `[MODIFIED]` `backend/config/tenancy.php` - تفعيل ميزة `UserImpersonation`.
* `[MODIFIED]` `backend/routes/web.php` - تسجيل مسارات `admin/login` و `admin/logout` و `admin/super/tenants/{id}/impersonate`.
* `[MODIFIED]` `backend/routes/tenant.php` - مسار معالجة الدخول `impersonate/{token}` ومسار الخروج والعودة للوحة المركزية.
* `[MODIFIED]` `backend/app/Http/Controllers/SuperAdminController.php` - دمج ميزة الـ Impersonation.
* `[MODIFIED]` `backend/app/Http/Middleware/HandleInertiaRequests.php` - تمرير خاصية `is_impersonating` للواجهة.
* `[MODIFIED]` `backend/resources/js/Pages/SuperAdmin/Tenants/Index.vue` - إضافة زر "⚡ دخول للمتجر".
* `[MODIFIED]` `backend/resources/js/Pages/SuperAdmin/Tenants/Show.vue` - إضافة زر الدخول المباشر كمسؤول المتجر.
* `[MODIFIED]` `backend/resources/js/Layouts/AppLayout.vue` - إضافة شريط التنبيه العلوي للسوبر أدمن مع زر العودة للوحة السوبر أدمن.
* `[MODIFIED]` `backend/resources/js/Layouts/SuperAdminLayout.vue` - إضافة زر تسجيل الخروج الآمن.

---

## 2. القرارات المعمارية والأمنية:
* عزل تام لمصادقة السوبر أدمن عبر التحقق من جدول المستخدمين المركزي ودور `admin`.
* رموز الدخول السريع ذات صلاحية مؤقتة مشفرة ولمرة واحدة فقط (Single-use token).
* شريط تنبيهي واضح داخل متجر المستأجر يوضح أن الجلسة الحالية مدارة بواسطة السوبر أدمن مع إمكانية إنهاء الجلسة والرجوع فوراً للوحة المركزية.

---

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وبناء الأصول بنجاح (0 errors)
* [x] فحص المسارات المركزية ومسارات المستأجرين
* [x] التحقق من سلامة الترحيل (Migration)
* [x] رفع التعديلات فوراً إلى الـ Git
