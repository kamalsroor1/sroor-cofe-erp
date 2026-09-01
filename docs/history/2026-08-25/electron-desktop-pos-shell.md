# سجل تعديل: تحويل المنظومة إلى تطبيق ديسكتوب أصيل (Native Frameless Desktop POS)
* **التاريخ والوقت:** 2026-08-25 18:35
* **الدور المفعل:** Frontend UI & Desktop Systems Architect
* **الهدف:** تطبيق حزمة التحويل الكاملة لتطبيق سطح المكتب الأصيل دون المساس بأي من شاشات الويب أو الموبايل.

## 1. الملفات المنشأة والمعدلة:
* `[NEW]` `backend/resources/js/Components/Common/DesktopTitlebar.vue` - شريط عنوان ديسكتوب فاخر مدمج مع سحب النافذة (`-webkit-app-region: drag`) ومؤشر بنج السيرفر وحالة طابعة الإيصالات وأزرار النافذة (- ▢ ✕).
* `[NEW]` `backend/resources/js/Components/Common/DesktopShortcutsModal.vue` - نافذة دليل اختصارات الكيبورد السريعة للكاشير (`F1`).
* `[NEW]` `backend/resources/js/Composables/useAudioFeedback.js` - محرك مؤثرات صوتية احترافي للـ POS عبر Web Audio API (صوت قراءة الباركود، صوت جرس النجاح عند البيع، وصوت فتح الدرج الميكانيكي).
* `[MODIFIED]` `backend/resources/js/Composables/useDesktopHardware.js` - دعم التحكم في تصغير وتكبير وإغلاق النافذة بدون إطارات، وفحص البنج الحي.
* `[MODIFIED]` `backend/resources/js/App.vue` - دمج `DesktopTitlebar` و `DesktopShortcutsModal` وحفظ النوافذ مع مستمع الاختصارات العالمي (`F1`, `F12`).
* `[MODIFIED]` `backend/resources/js/views/POS/PosView.vue` - إضافة أصوات قراءة الباركود والإنهاء والدرج وحفظ الفاتورة مع التنبيه الصوتي.
* `[MODIFIED]` `backend/resources/css/app.css` - إضافة فئات التخصيص المحصورة بـ `.electron-app` (أشرطة التمرير الناعمة، منع التحديد العشوائي، ومناطق السحب).
* `[MODIFIED]` `desktop/main.js` - تفعيل `frame: false` مع قائمة كليك يمين native، أيقونة شريط المهام (System Tray)، وقنوات IPC لأزرار النافذة.
* `[MODIFIED]` `desktop/preload.js` - توسيع جسر التخاطب ليشمل `minimize`, `maximize`, `close`, `pingServer`.
* `[MODIFIED]` `backend/routes/api.php` - إضافة مسار `GET /api/v1/ping` لفحص سرعة استجابة السيرفر.

## 2. القرارات والضمانات المعمارية:
* **عزل كامل 100% عن الويب والموبايل:** كافة أزرار التحكم بالنافذة وشريط العنوان والمودالات محكومة بـ `isDesktop` وفئة `.electron-app`، وبالتالي لا يظهر أي بكسل إضافي على متصفح الويب أو تطبيق الموبايل.
* **صفر اعتمادات خارجية للمؤثرات الصوتية:** تم توليد نغمات الكاشير رياضياً عبر Web Audio API Oscillators بدون أي ملفات mp3/wav خارجية لضمان السرعة والعمل Offline.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` اجتاز بنجاح في 33.2s).
* [x] حزمة التثبيت المستقلة `dist/win-unpacked/سرور كوفي ERP & POS.exe` مجمعة بنجاح.
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
