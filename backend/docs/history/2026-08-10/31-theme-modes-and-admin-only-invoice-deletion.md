# سجل تعديل: نظام الدارك مود واللايت مود مع حفظ التفضيل لكل مستخدم + قصر حذف الفواتير على المدير العام

* **التاريخ والوقت:** 2026-08-10 20:58
* **الدور المفعل:** Full Stack AI Squad
* **الهدف من التعديل:**
  1. قصر صلاحية حذف وإلغاء فواتير المبيعات على "المدير العام (Admin)" حصرياً في مستوى الـ Service ومكونات Livewire وإخفاء أزرار الحذف عن الكاشير والمستخدمين الآخرين.
  2. بناء نظام متكامل للوضع الليلي والنهاري (Dark / Light Mode) مع زر تبديل فوري في الهيدر، وحفظ تفضيل المظهر تلقائياً لكل مستخدم في قاعدة البيانات (`users.theme_preference`) و `localStorage` دون أي وميض أو تأخير.

---

## 1. الملفات المعدلة والمنشأة (Modified & New Files)
* `[NEW]` `database/migrations/2026_08_10_210000_add_theme_preference_to_users_table.php` - حقل تفضيل المظهر في جدول المستخدمين.
* `[MODIFIED]` `app/Models/User.php` - إضافة `theme_preference` إلى `$fillable`.
* `[MODIFIED]` `app/Services/InvoiceService.php` - فحص صلاحية `admin` الصارمة في دالتي `cancelInvoice` و `deleteInvoice`.
* `[MODIFIED]` `app/Livewire/InvoiceIndex.php` - حظر حذف وإلغاء الفواتير لغير المدير العام.
* `[MODIFIED]` `app/Livewire/InvoiceShow.php` - حظر حذف الفواتير لغير المدير العام.
* `[MODIFIED]` `resources/views/livewire/invoice-index.blade.php` - إخفاء زر الحذف عن غير المدير العام.
* `[MODIFIED]` `resources/views/livewire/invoice-show.blade.php` - إخفاء زر الحذف عن غير المدير العام.
* `[MODIFIED]` `resources/views/components/layouts/app.blade.php` - إضافة زر تبديل الثيم الفوري ودعم الوضعين المتكاملين.
* `[MODIFIED]` `app/Livewire/Auth/Profile.php` & `resources/views/livewire/auth/profile.blade.php` - إمكانية اختيار وحفظ المظهر من صفحة الملف الشخصي.
* `[MODIFIED]` `routes/web.php` - إضافة مسار حفظ الثيم `/theme-toggle`.
* `[MODIFIED]` `tests/Feature/RolePermissionTest.php` - إضافة اختبار مؤتمت للتأكد من قدرة الأدمن فقط على الحذف ومنع الكاشير.

---

## 2. التحقق والاختبار (Verification & Testing)
* [x] تم تشغيل كافة الاختبارات المؤتمتة (51 اختباراً، 163 تأكيداً) واجتيازها بنسبة 100%.
* [x] تم تطبيق الـ Migration والنشر على السيرفر الحي بنجاح مع الحفاظ الكامل على كافة البيانات والفواتير والأرصدة.
