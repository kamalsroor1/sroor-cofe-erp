# سجل تعديل: تفعيل النسخ الاحتياطي التلقائي اليومي في تيليجرام وتنبيهات عجز الورديات الفورية

* **التاريخ والوقت:** 2026-08-13 22:50
* **الدور المفعل:** Backend Architect & Frontend UI
* **الهدف من التعديل:** 
  1. بناء خدمة تصدير وضغط قاعدة البيانات تلقائياً (`.sql.gz`) وإرسالها كملف مرفق إلى جروب التيليجرام يومياً الساعة `12:05 ص` لحفظ نسخة أمان دورية.
  2. إضافة تنبيه فوري يرسل إلى جروب الإدارة عند إغلاق أي كاشير لورديته بوجود عجز أو زيادة نقدية في الدرج.

---

## 1. الملفات المنشأة والمعدلة (Files Created & Modified)
* `[NEW]` `app/Services/DatabaseBackupService.php` - خدمة تصدير جداول وبيانات قاعدة البيانات وضغطها بـ Gzip (مستوى ضغط 9).
* `[NEW]` `app/Jobs/SendTelegramDatabaseBackupJob.php` - وظيفة خلفية لتصدير ورفع النسخة الاحتياطية في الـ Queue.
* `[NEW]` `app/Console/Commands/SendTelegramDatabaseBackupCommand.php` (`backup:telegram`) - أمر سطر الأوامر لتصدير وإرسال النسخة الاحتياطية.
* `[MODIFIED]` `app/Services/TelegramService.php` - إضافة دوال `sendDocument` و `sendDatabaseBackupNotification` و `sendShiftDiscrepancyNotification`.
* `[MODIFIED]` `app/Services/ShiftService.php` - إرسال تنبيه فوري لتيليجرام عند تقفيل الشفت في حال وجود `cash_difference != 0`.
* `[MODIFIED]` `routes/console.php` - جدولة إرسال النسخة الاحتياطية يومياً في تمام الساعة `00:05` بعد منتصف الليل.
* `[MODIFIED]` `app/Livewire/Auth/Profile.php` & `resources/views/livewire/auth/profile.blade.php` - إضافة زر `💾 إرسال نسخة احتياطية للجروب الآن` للتصدير اليدوي الفوري.

---

## 2. التحقق والتأكيد (Verification)
* [x] اجتياز 100/100 اختبار PHPUnit بنجاح (360 Assertion).
* [x] تم تشغيل الأمر واختبار وصول ملف النسخة الاحتياطية المضغوط داخل جروب التيليجرام بنجاح.
* [x] تم نشر التحديث وبناء كاشات الإنتاج على كافة نطاقات السيرفر.
