# سجل تعديل: تثبيت القائمة الجانبية ومراقبة اليومية المفتوحة والإنذارات وتوحيد حقول التاريخ

* **التاريخ والوقت:** 2026-08-13 21:18
* **الدور المفعل:** Frontend UI & Backend Architect
* **الهدف من التعديل:** منع وميض وتغيير مقاس القائمة الجانبية (Sidebar Flickering / Layout Shift) عند التنقل بين الصفحات، إزالة الإيموشنز من أسماء الروابط والإبقاء على الأيقونات فقط، وإضافة مؤشر لحالة الوردية المفتوحة في الهيدر والإنذار عند مرور أكثر من 24 ساعة، مع توحيد مدخلات التاريخ Date Inputs عبر كل الشاشات.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[MODIFIED]` `resources/views/components/layouts/app.blade.php` - تثبيت القائمة الجانبية عبر سكربت مبكر في Head مع منع الـ Layout Shifts، إزالة الإيموشنز من أسماء العناصر، إضافة زر مؤشر حالة اليومية، وبانر تحذيري عام عند تأخر الوردية لأكثر من 24 ساعة.
* `[MODIFIED]` `resources/views/livewire/daily-journal-index.blade.php` - تحسين بطاقة اليومية والوردية لتمييز الورديات المتأخرة والتحذير منها (+24h).
* `[MODIFIED]` `app/Livewire/InvoiceIndex.php` & `resources/views/livewire/invoice-index.blade.php` - إضافة فلاتر التاريخ (من / إلى) كـ `type="date"`.
* `[MODIFIED]` `app/Livewire/PurchaseIndex.php` & `resources/views/livewire/purchase-index.blade.php` - إضافة فلاتر التاريخ (من / إلى) كـ `type="date"`.
* `[MODIFIED]` `app/Livewire/ReturnIndex.php` & `resources/views/livewire/return-index.blade.php` - إضافة فلاتر التاريخ (من / إلى) كـ `type="date"`.
* `[MODIFIED]` `resources/views/livewire/customer-statement.blade.php` - إضافة حقول التاريخ `type="date"` لكشف حساب العميل.
* `[MODIFIED]` `resources/views/livewire/supplier-statement.blade.php` - إضافة حقول التاريخ `type="date"` لكشف حساب المورد.
* `[MODIFIED]` `resources/views/livewire/reports-index.blade.php` - تحسين حقول التاريخ والخيارات المنسدلة للتباين اللوني.
* `[MODIFIED]` `resources/views/livewire/expense-index.blade.php` - إضافة فلاتر التاريخ `type="date"`.
* `[MODIFIED]` `resources/views/livewire/purchase-create.blade.php` - إضافة حقل تاريخ الفاتورة `type="date"`.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **Zero Layout Shift & Zero Font Resizing Sidebar:** تم استبدال الـ `x-show` و `:class` في كل عناصر القائمة الجانبية بنظام قواعد CSS نقية فورية خالية من أي تأخير (`.sidebar-text-full`, `.sidebar-text-mini`, `.sidebar-nav-item`) يتم تفعيلها فوراً من خلال الكلاس المطبق على `<html>` في الـ `<head>`، مما يمنع نهائياً أي وميض أو تصغير/تكبير للخطوط عند تحميل أي صفحة جديدة.
* **إزالة الإيموشنز والاعتماد على SVG Icons:** تنقية أسماء الروابط من الإيموشن لتحقيق مظهر أكثر احترافية وأناقة.
* **Live Shift Status & Overdue Warning:** حساب فارق ساعات فتح الوردية النشطة `$activeShift->opened_at`، وإظهار مؤشر أخضر نابض لليومية المفتوحة، أو مؤشر أحمر وبانر عام مشتعل عند تجاوز 24 ساعة دون تقفيل لتنبيه الكاشير والإدارة.
* **توحيد حقول التواريخ:** استخدام `type="date"` مع تنسيق واضح وخط عربي متناسق ودعم التباين الليلي/النهاري.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم التحقق من عدم وجود أخطاء في الـ Syntax.
* [x] اجتياز جميع اختبارات الـ PHPUnit بنسبة 100% (100/100 اختبار، 360 Assertion).
* [x] اجتياز اختبارات E2E Playwright الخاصة بالتنقل وتسجيل الدخول بنسبة 100%.
* [x] التحقق من توافق الوضع الليلي والنهاري ودعم الـ RTL.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. رفع التعديلات إلى مستودع GitHub (`git push`).
2. تشغيل سكريبت النشر إلى السيرفر الحي وتصفير الكاش (`deploy_all_locations.py`).
