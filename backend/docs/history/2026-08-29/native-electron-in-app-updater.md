# سجل تعديل: نظام التحديث التلقائي المباشر لـ Electron وتحديث الإصدار إلى v1.1.0
* **التاريخ والوقت:** 2026-08-29 00:35
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** بناء محرك التحديث التلقائي المباشر (Native In-App Auto-Updater) داخل بيئة Electron دون الحاجة للتحميل اليدوي عبر المتصفح، مع شريط تحميل حي وإعادة تشغيل تلقائية للبرنامج.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `desktop/src/updater/nativeUpdater.js` - محرك تحميل وتثبيت التحديثات الحية لـ Electron عبر تدفق البيانات الحي (Streams) وتتبع نسب التقدم وإطلاق التثبيت الصامت أو إعادة التشغيل.
* `[MODIFIED]` `desktop/main.js` - تسجيل معالج الـ IPC `updater:download-and-install` وربطه بمحرك التحديثات الرئيسي.
* `[MODIFIED]` `desktop/preload.js` - تصدير كائن `updater` التفاعلي وتوفير مستمعات الأحداث (`onProgress`, `onComplete`, `onError`).
* `[MODIFIED]` `backend/resources/js/Composables/useAppUpdate.js` - ربط الـ Composable بمحرك Electron الأصلي مع الحفاظ على التراجع السلس (Fallback) وتحديث رقم الإصدار في التخزين المحلي.
* `[MODIFIED]` `backend/resources/js/Components/AppUpdateModal.vue` - تحديث واجهة المودال وتضمين شريط تقدم تفاعلي مع نصوص المراحل الحية وأيقونات التحميل الدوارة.
* `[MODIFIED]` `backend/lang/ar/app_update.php` & `backend/lang/en/app_update.php` - إضافة مفاتيح الترجمة للمراحل وإعادة التشغيل التلقائي.

## 2. القرارات التقنية:
* تم ضبط إصدار التحديث السحابي المركزي إلى `v1.1.0` (Build #110) في جدول `app_versions`.
* تتبع نسبة التحميل لحظياً من خلال دفق البيانات وتحديث الواجهة دون فتح أي نوافذ خارجية.
* تنفيذ إعادة التشغيل التلقائية (`clearCache` + `hardReload` / `app.relaunch`) فور اكتمال التنزيل.

## 3. التحقق والاختبار:
* [x] بناء حزمة التثبيت بنجاح (`npm run dist:win`).
* [x] رفع حزمة الـ Setup المحدثة للسيرفر السحابي (78.17 MB).
* [x] اختبار ومطابقة استجابة الـ API `/api/v1/app/check-update`.
* [x] خلو الكود من الأخطاء وسلامة الترجمات للغتين (ar & en).
