# سجل تعديل: إطلاق نظام الـ Monorepo Hub والـ Multi-Tenancy وإصلاح استجابات Inertia

* **التاريخ والوقت:** 2026-08-20 16:45
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** هيكلة مشروع `erp-hub` ودمج الباك إند والموبايل، وتجهيز مشغل موحد بضغطة زر واحدة لكافة الخدمات، وإصلاح استجابات Inertia لتبديل الفروع والثيم، وتنظيف وتنسيق الشريط العلوي.

---

## 1. الملفات المعدلة:
* `[NEW]` `start.bat` - ملف تنفيذي بضغطة زر واحدة لتشغيل سيرفر الباك إند، وفرونت إند Vite، والموبايل معاً.
* `[NEW]` `start.ps1` - سكربت PowerShell للتشغيل الموحد.
* `[MODIFIED]` `package.json` - تهيئة سكربت `npm run dev` الموحد باستخدام `concurrently`.
* `[MODIFIED]` `backend/routes/tenant.php` - تصحيح مسارات `/store/switch` و `/theme-toggle` لتعيد استجابة Inertia سليمة وتمنع خطأ Raw JSON.
* `[MODIFIED]` `backend/routes/web.php` - تصحيح مسارات تبديل الفرع والثيم المركزية.
* `[MODIFIED]` `backend/resources/js/Layouts/AppLayout.vue` - تنظيف الشريط العلوي وإزالة البادجات المكررة للفرع وتنسيق شارة الوردية والتاريخ والوقت.
* `[MODIFIED]` `backend/bootstrap/providers.php` - تسجيل `Inertia\ServiceProvider` و `Stancl\Tenancy\TenancyServiceProvider`.
* `[MODIFIED]` `backend/config/tenancy.php` - ضبط لاحقة قواعد بيانات المستأجرين على `.sqlite`.
* `[MODIFIED]` `mobile/resources/js/Components/SideMenu.vue` - تصحيح الترميز والصياغة.
* `[MODIFIED]` `mobile/vite.config.js` - تخصيص منفذ مستقل (5174) لخادم الموبايل.

---

## 2. القرارات التقنية:
* تم استخدام `back()->with('success', ...)` لكافة طلبات Inertia مع دعم استجابة JSON للطلبات القادمة من الـ API الخارجية وتطبيق الموبايل.
* تم توحيد اختيار وتبديل الفروع في زر تفاعلي واحد في الشريط العلوي لتوفير مساحة بصرية مريحة واستجابة فورية.

---

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وبناء الأصول بنجاح (0 errors)
* [x] فحص تبديل الفروع وعودة استجابة Inertia سليمة
* [x] تشغيل الخوادم الثلاثة بنجاح معاً (Backend 8000, Frontend 5173, Mobile 5174)
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن
