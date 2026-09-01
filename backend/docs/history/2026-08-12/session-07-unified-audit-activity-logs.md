# سجل تعديل: نظام سجل العمليات والرقابة الذاتية الموحد (Unified Activity & Audit Logs)

* **التاريخ والوقت:** 2026-08-12 18:42
* **الدور المفعل:** (Backend Architect / Frontend UI / QA Testing / Docs PM)
* **الهدف من التعديل:** دمج وترقية نظام الرقابة والـ Logs ليصبح نظاماً بصرياً ذكياً وشاملاً لكافة حركات النظام (فواتير، مخزون، ورديات، مشتريات، أمان) مع شاشة تفاعلية مريحة لغير التقنيين وفلاتر سريعة ومقارنة التعديلات.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[NEW]` `database/migrations/2026_08_12_183500_create_activity_logs_table.php` - جدول قاعدة بيانات الأنشطة والرقابة.
* `[NEW]` `app/Models/ActivityLog.php` - نموذج Eloquent مع العلاقات والشارات الملونة.
* `[NEW]` `app/Services/ActivityLogService.php` - خدمة التسجيل المركزية الموحدة لكافة الأقسام.
* `[NEW]` `app/Livewire/ActivityLogIndex.php` - مكون Livewire مع الفلاتر الزمنية وإحصائيات اليوم وتصدير CSV.
* `[NEW]` `resources/views/livewire/activity-log-index.blade.php` - واجهة المستخدم التفاعلية (Timeline + Table + Diff Modal).
* `[NEW]` `tests/Feature/ActivityLogTest.php` - حزمة اختبارات Feature كاملة.
* `[MODIFIED]` `app/Services/InvoiceService.php` - ربط حركات إنشاء، تعديل، إلغاء، وحذف الفواتير.
* `[MODIFIED]` `app/Services/ShiftService.php` - ربط حركات فتح وتقفيل الورديات مع نقدية الدرج والعجز/الزيادة.
* `[MODIFIED]` `app/Services/StockTransferService.php` - ربط إنشاء وإلغاء أذونات تحويل البضاعة بين الفروع.
* `[MODIFIED]` `app/Services/PurchaseService.php` - ربط فواتير الشراء والتوريد.
* `[MODIFIED]` `app/Livewire/Auth/Login.php` - ربط تسجيلات الدخول الناجحة ومحاولات الدخول الفاشلة.
* `[MODIFIED]` `database/seeders/PermissionsSeeder.php` - إضافة صلاحية `logs.view`.
* `[MODIFIED]` `app/Livewire/Auth/RolePermissionManager.php` - إدراج الصلاحية في مصفوفة الإدارة.
* `[MODIFIED]` `resources/views/components/layouts/app.blade.php` - إضافة رابط `📜 سجل العمليات والرقابة` في السايدبار والقائمة العلوية.
* `[MODIFIED]` `routes/web.php` - مسار `/activity-logs` محمي بصلاحية `logs.view`.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **الدمج الموحد:** دمج التقنية السابقة في خدمة مركزية حديثة توفر الدقة البرمجية (JSON Properties) واللغة العربية الوصفية للمستخدمين العاديين.
* **دعم الفروع وعربات التوزيع:** تسجيل `store_id` مع كل حركة وفلترة السجل حسب كل فرع.
* **الأمان والصلاحيات:** عزل الشاشة بـ `logs.view` محصورة بالمدير العام.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم التحقق من عدم وجود أخطاء في الـ Syntax.
* [x] اجتياز 100/100 اختبار في حزمة PHPUnit بنسبة نجاح 100%.
* [x] تم اختبار المتصفح التفاعلي Playwright على السيرفر الحي وتأكيد عمل الشاشة والفلاتر.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. الاستمرار في مراقبة الأداء والاستمتاع بالنظام الرقابي الحي.
