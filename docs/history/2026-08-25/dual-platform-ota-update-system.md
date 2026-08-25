# سجل تعديل: نظام التحديث المستقل لبرنامج سطح المكتب وتطبيق الموبايل (Dual-Platform Independent OTA Update System)

* **التاريخ والوقت:** 2026-08-25 21:55
* **الدور المفعل:** Full-Stack Architect & System Updater
* **الهدف:** بناء منظومة تحديثات هوائية (OTA) مرنة ومستقلة تماماً، تتيح لإدارة النظام نشر تحديثات خاصة بتطبيق الموبايل (Android APK) بشكل مستقل، أو نشر تحديثات خاصة ببرنامج سطح المكتب (Windows Desktop .EXE) بشكل مستقل، مع فحص التحديثات التلقائي داخل كل منصة.

---

## 1. الملفات المعدلة والجديدة:

* `[MODIFIED]` `backend/app/Actions/AppVersions/CreateAppVersionAction.php` - ضبط تسمية الحزم وامتداداتها حسب المنصة (`sroor-coffee-erp-v*.apk` للأندرويد و `Sroor-ERP-POS-Setup-v*.exe` للويندوز).
* `[MODIFIED]` `backend/app/Actions/AppVersions/DownloadLatestApkAction.php` - دعم نوع المحتوى (`Content-Type`) المناسب ومسارات التنزيل الاحتياطية لملفات تثبيت الديسكتوب.
* `[MODIFIED]` `backend/resources/js/Components/SuperAdmin/UploadApkModal.vue` - دعم ديناميكي لامتدادات وأنواع الملفات المقبولة وتسميات الحزم بحسب المنصة المختارة.
* `[MODIFIED]` `backend/resources/js/Components/SuperAdmin/AppVersionsTable.vue` - إضافة شارات المنصة البصرية الملونة (`📱 Android` و `💻 Windows`).
* `[MODIFIED]` `backend/resources/js/Composables/useAppUpdate.js` - محرك فحص وتنزيل التحديثات المستقل لكل منصة بناءً على نوع العميل.
* `[MODIFIED]` `backend/resources/js/Components/AppUpdateModal.vue` - تفعيل ظهور نافذة التحديث على الديسكتوب والموبايل مع تفاصيل التحديث.
* `[MODIFIED]` `backend/resources/js/Components/Common/DesktopTitlebar.vue` - إضافة شارة تحديث نابضة في شريط العنوان للديسكتوب عند توفر إصدار جديد.

---

## 2. القرارات التقنية:

1. **الاستقلالية التامة للمنصات (Independent Platform Releases):** تمكين السوبر أدمن من إطلاق تحديث للموبايل فقط دون التأثير على الديسكتوب، أو إطلاق تحديث للديسكتوب فقط دون التأثير على الموبايل.
2. **الفحص والتنزيل الذكي:** التطبيق يرسل نوع منصته (`platform: 'android'` أو `platform: 'windows'`) ويستقبل فقط التحديث المتوافق معه مع تنزيل ملف التثبيت المناسب (`.apk` أو `.exe`).

---

## 3. التحقق والاختبار:

* [x] خلو الكود من الأخطاء والبناء سليم بنجاح (`npm run build`).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] النشر الناجح على السيرفر السحابي `baraa-solutions.com`.
