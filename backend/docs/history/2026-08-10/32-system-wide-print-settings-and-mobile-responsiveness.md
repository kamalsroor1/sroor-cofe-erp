# سجل تعديل: إعدادات الطباعة العامة وتحسين التوافق مع شاشات الموبايل

* **التاريخ والوقت:** 2026-08-10 21:50
* **الدور المفعل:** Full-Stack Architect & Frontend UI Agent
* **الهدف من التعديل:**
  1. جعل إعداد إظهار/إخفاء عبارة "سرور كوفي - لتوزيع خامات مطاحن البن" إعداداً عاماً للنظام (System-wide) مع حفظه في جدول `settings` وتخزينه في الـ Cache تلقائياً.
  2. توفير واجهة تعديل لاسم المؤسسة والوصف الفرعي وخيار الإخفاء/الإظهار من صفحة الملف الشخصي.
  3. تحسين التوافق مع الموبايل (Mobile Responsiveness) في كافة صفحات النظام وشاشات الفواتير واليومية مع توفير Mobile Cards View للفواتير.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[NEW]` `database/migrations/2026_08_10_221000_create_settings_table.php` - جدول إعدادات النظام العامة مع القيم الافتراضية.
* `[NEW]` `app/Models/Setting.php` - نموذج إعدادات النظام مع دوال `get()`, `set()`, `getBool()`, `allCached()` وتفعيل الـ Cache الدائم مع الـ Invalidation التلقائي.
* `[MODIFIED]` `app/Livewire/Auth/Profile.php` - إضافة خصائص ودوال `updateGeneralSettings` لإدارة بيانات وترويسة الطباعة العامة.
* `[MODIFIED]` `resources/views/livewire/auth/profile.blade.php` - إضافة قسم "إعدادات ترويسة الطباعة العامة" بمفتاح Toggle Switch وتصميم Responsive وDual-Theme كامل.
* `[MODIFIED]` `resources/views/layouts/print-thermal.blade.php` - استخدام اسم المؤسسة والوصف الفرعي ديناميكياً من `Setting` مع احترام خيار الإخفاء/الإظهار.
* `[MODIFIED]` `resources/views/layouts/print-a4.blade.php` - استخدام اسم المؤسسة والوصف الفرعي ديناميكياً من `Setting` مع احترام خيار الإخفاء/الإظهار.
* `[MODIFIED]` `resources/views/components/layouts/app.blade.php` - تحسين استجابة الهيدر العلوي للموبايل (زر الثيم والاسم).
* `[MODIFIED]` `resources/views/livewire/invoice-index.blade.php` - إضافة Mobile Cards View للهواتف والشاشات الصغيرة مع الحفاظ على الجدول الكامل للأجهزة اللوحية والمكتبية.
* `[MODIFIED]` `resources/views/livewire/invoice-show.blade.php` - تحسين أزرار العمليات والطباعة لتكون `flex-wrap` متجاوبة مع الموبايل.
* `[MODIFIED]` `resources/views/livewire/invoice-create.blade.php` - تحسين تجاوب الهيدر وشريط محتويات الفاتورة مع الموبايل.
* `[MODIFIED]` `resources/views/livewire/invoice-edit.blade.php` - تحسين تجاوب الهيدر وشريط محتويات الفاتورة مع الموبايل.
* `[MODIFIED]` `resources/views/livewire/daily-journal-index.blade.php` - تحسين أزرار التبويبات للتجاوب والالتفاف على الموبايل.
* `[MODIFIED]` `tests/Feature/SystemImprovementsTest.php` - إضافة اختبارات `test_setting_model_and_caching` و `test_profile_general_printing_settings_update`.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **إعدادات عامة وسريعة بالـ Cache:** تم اعتماد `Cache::rememberForever('app_settings_all', ...)` لجلب كافة الإعدادات في استعلام واحد واسترجاعها بسرعة فائقة (Zero DB overhead) مع تفريغ الكاش فور إجراء أي تحديث.
* **Responsive Card View على الموبايل:** توفير تصميم كروت أنيقة للفواتير على شاشات الجوال (<640px) مع توفير أزرار وصول سريع للطباعة الحرارية والتعديل والحذف دون الحاجة للتمرير الأفقي المرهق.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم تشغيل جميع اختبارات النظام (53 اختباراً) ونجحت بنسبة 100%.
* [x] تم اختبار حفظ وتعديل الإعدادات العامة من الـ Profile وتحديث الكاش.
* [x] تم اختبار قوالب الطباعة (A4 و Thermal) والتأكد من إخفاء/إظهار الوصف الفرعي بناءً على الإعداد.
* [x] تم التحقق من استجابة واجهات الموبايل والتوافق مع الوضعين الليلي والنهاري (Dark/Light).

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. رفع التحديثات للسيرفر ومتابعة الاستخدام اليومي.
