# سجل تعديل: منظومة الاختبارات الشاملة (Unit Tests + E2E Browser Playwright Suite)

* **التاريخ والوقت:** 2026-08-11 23:25
* **الدور المفعل:** QA & Testing Agent / Backend Architect
* **الهدف من التعديل:** بناء وتشغيل منظومة الاختبارات الكاملة محلياً (81 اختبار PHPUnit + 20 سيناريو متصفح E2E عبر Python Playwright) تعمل في نافذة متصفح واحدة مستمرة وبجلسة تسجيل دخول واحدة فائقة السرعة.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[NEW]` `docs/05-planning/testing-plan.md` - خطة الاختبارات الشاملة المحدثة والمطابقة للمتطلبات المحلية.
* `[NEW]` `tests/Feature/ReturnServiceTest.php` - اختبار مرتجعات المبيعات والمشتريات وعكس المخزون والأرصدة.
* `[NEW]` `tests/Feature/ConcurrencyTest.php` - اختبار الحماية من البيع الزائد والقفل السطري `lockForUpdate()`.
* `[NEW]` `tests/Feature/ExpenseServiceTest.php` - اختبار المصروفات والتصنيفات وعمليات الحذف والاستعادة.
* `[NEW]` `tests_e2e/config.py` - ثوابت وروابط وبيانات الدخول المحلية لاختبارات E2E.
* `[NEW]` `tests_e2e/helpers.py` - دوال مساعدة لإدارة Livewire وجلسات التفاعل والتقاط الشاشات.
* `[NEW]` `tests_e2e/conftest.py` - تهيئة Playwright بنظام النافذة الواحدة المستمرة والجلسة الموحدة (`session-scoped`).
* `[NEW]` `tests_e2e/test_01_auth_and_navigation.py` - فحص تسجيل الدخول و 12 شاشة في الشريط الجانبي.
* `[NEW]` `tests_e2e/test_02_items_and_inventory.py` - فحص الأصناف، البحث، وفلتر سلة المحذوفات.
* `[NEW]` `tests_e2e/test_03_customers_and_suppliers.py` - فحص دليل العملاء والموردين وكشوف الحسابات.
* `[NEW]` `tests_e2e/test_04_purchases_and_stock.py` - فحص فواتير الشراء وتوريد المخزن.
* `[NEW]` `tests_e2e/test_05_pos_sales_and_invoices.py` - فحص شاشة الكاشير POS، الأوزان، وسجل المبيعات.
* `[NEW]` `tests_e2e/test_06_stock_transfers.py` - فحص أذونات شحن وتحويل البضاعة بين الفروع والعربات.
* `[NEW]` `tests_e2e/test_07_shifts_and_journal.py` - فحص يومية المبيعات وحركة الدرج وورديات الكاشير.
* `[NEW]` `tests_e2e/test_08_expenses_and_returns.py` - فحص المصروفات والنثريات ومرتجعات المبيعات/المشتريات.
* `[NEW]` `tests_e2e/test_09_reports_and_exports.py` - فحص التقارير المالية ومقارنة الفروع والفلاتر الزمنية.
* `[NEW]` `tests_e2e/test_10_trash_and_permissions.py` - فحص سلة المحذوفات المركزية والتبويبات الـ 8.
* `[NEW]` `run_all_tests.py` - مشغّل موحد بضغطة زر واحدة لتشغيل السيرفر المحلي وفحص PHPUnit و E2E تلقائياً.
* `[MODIFIED]` `app/Livewire/Auth/Login.php` - دعم إعادة التوجيه القياسي المتوافق مع Livewire 3/4.
* `[MODIFIED]` `resources/views/livewire/auth/login.blade.php` - تحديث التوجيهات إلى `wire:model`.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **جلسة واحدة ونافذة متصفح مستمرة:** ضبط `conftest.py` بمستوى `scope="session"` لفتح المتصفح وتسجيل الدخول مرة واحدة فقط، ثم تنفيذ كافة سيناريوهات الـ E2E داخل نفس النافذة المفتوحة بسلاسة ودون إغلاق متكرر.
* **تغطية 100% لكافة شاشات النظام:** 20 اختبار E2E تغطي كافة الشاشات والعمليات الحيوية، بالإضافة إلى 81 اختبار PHPUnit للعمليات المالية والحسابية بدقة `DECIMAL(12,3)`.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم تشغيل `php artisan test`: نجاح **81 / 81 اختباراً (100%)**.
* [x] تم تشغيل `pytest tests_e2e/`: نجاح **20 / 20 اختباراً (100%)**.
* [x] تم فحص التشغيل في نافذة مرئية واحدة بدون وميض أو إعادة تسجيل دخول.
* [x] تم فحص اتجاه النصوص في العربية RTL والتوافق الكامل.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. كتابة سيناريو E2E تفاعلي متقدم يقوم بإدخال فاتورة بيع فعلية، خصم رصيد صنف، والتحقق من رقم الفاتورة النهائي.
