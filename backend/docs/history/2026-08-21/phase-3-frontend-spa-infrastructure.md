# سجل تعديل: المرحلة 3 - تجهيز بنية الواجهة الأساسية (Vue Router + Pinia + API Client + Login Page)
* **التاريخ والوقت:** 2026-08-21 14:45
* **الدور المفعل:** Frontend / UI Specialist & Backend Architect Agent
* **الهدف:** تأسيس البنية التحتية الكاملة للـ Vue 3 SPA المستقل (تثبيت وإعداد Vue Router 4 و Pinia، بناء عميل Axios الموحد مع Interceptors للمصادقة والفروع، بناء useAuthStore و useAppConfigStore، وإعداد شاشات LoginView و DashboardView وإطار SpaLayout مع الحفاظ على Inertia بالتوازي بنظام Dual-Engine).

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/resources/js/services/api.js` - عميل Axios المركزي لإدارة التوكن وهيدرات الـ Tenant والـ Store والأخطاء.
* `[NEW]` `backend/resources/js/stores/auth.js` - Pinia Auth Store (المستخدم، التوكن، الصلاحيات، الفروع).
* `[NEW]` `backend/resources/js/stores/appConfig.js` - Pinia AppConfig Store (سياق النظام، الورديات، التنبيهات، الترجمات، الثيم).
* `[NEW]` `backend/resources/js/router/index.js` - موجه Vue Router مع Navigation Guards لحماية المسارات.
* `[NEW]` `backend/resources/js/App.vue` - المكون الجذري لتطبيق SPA.
* `[NEW]` `backend/resources/js/Layouts/SpaLayout.vue` - الإطار العام المتجاوب لـ SPA.
* `[NEW]` `backend/resources/js/views/Auth/LoginView.vue` - شاشة تسجيل الدخول التفاعلية بنظام API.
* `[NEW]` `backend/resources/js/views/DashboardView.vue` - شاشة لوحة التحكم المستقلة.
* `[NEW]` `backend/resources/js/spa.js` - نقطة الدخول الرئيسية للـ SPA.
* `[NEW]` `backend/resources/views/spa.blade.php` - وعاء Blade لتوجيه مسار `/spa`.
* `[MODIFIED]` `backend/resources/js/helpers/trans.js` - دعم الترجمات للـ SPA و Inertia معاً.
* `[MODIFIED]` `backend/vite.config.js` - إضافة `resources/js/spa.js` لمدخلات التحزيم.
* `[MODIFIED]` `backend/routes/web.php` و `backend/routes/tenant.php` - تسجيل مسارات استضافة الـ SPA.
* `[NEW]` `backend/tests/Feature/Api/SpaInfrastructureTest.php` - اختبارات مسار استضافة الـ SPA.

## 2. القرارات التقنية:
* تفعيل نظام المحرك المزدوج (Dual-Engine Mode): تشغيل واجهات Inertia الأصلية بالتوازي مع مسار `/spa` دون أي تعارض لضمان ترحيل آمن وتدريجي.
* تخزين التوكن في `localStorage` مع مزامنة الـ State في `useAuthStore` و `useAppConfigStore`.
* اجتياز البناء الكامل عبر `npm run build` بنجاح وتوليد ملفات التحزيم للـ SPA بدون أخطاء.

## 3. التحقق والاختبار:
* [x] اجتياز بناء Vite بالكامل بنجاح (`npm run build` في 6.67 ثانية).
* [x] اجتياز كافة اختبارات الـ API والـ SPA (14/14 tests passed, 109 assertions).
* [x] مطابقة الهوية البصرية (Dark Slate & Amber/Emerald) واللغة العربية RTL بالكامل.
