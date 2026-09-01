# سجل تعديل: نظام ترقيم الفواتير بكود الفرع وإظهار بيانات الفرع في الواجهات والطباعة

* **التاريخ والوقت:** 2026-08-12 16:30
* **الدور المفعل:** Backend Architect Agent & Frontend UI Agent
* **الهدف من التعديل:** تضمين كود الفرع في رقم الفاتورة (`INV-{STORE_CODE}-{YYYYMMDD}-{SEQ}`) وإبراز الفرع المصدر في سجل الفواتير، صفحة التفاصيل، وقوالب الطباعة الحرارية و A4.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[MODIFIED]` `d:\projects\sroor\app\Services\InvoiceService.php` - تحديث دالة `generateUniqueNumber(?int $storeId)` وتضمين كود الفرع المعقم.
* `[MODIFIED]` `d:\projects\sroor\app\Livewire\InvoiceIndex.php` - إضافة خاصية فلترة الفواتير حسب الفرع `$selectedStore` وتمرير قائمة الفروع للواجهة.
* `[MODIFIED]` `d:\projects\sroor\resources\views\livewire\invoice-index.blade.php` - إضافة قائمة منسدلة لاختيار الفرع، إضافة عمود الفرع وبادجات مميزة للفرع/العربية في الجدول وكروت الموبايل.
* `[MODIFIED]` `d:\projects\sroor\resources\views\livewire\invoice-show.blade.php` - إضافة كارت تفاصيل الفرع، الكود، الهاتف، العنوان، واسم الكاشير.
* `[MODIFIED]` `d:\projects\sroor\resources\views\layouts\print-thermal.blade.php` - إضافة سطر بيانات الفرع، الكود، الهاتف، العنوان، واسم الكاشير في إيصال الكاشير الحراري (80mm).
* `[MODIFIED]` `d:\projects\sroor\resources\views\layouts\print-a4.blade.php` - إضافة كارت تفاصيل الفرع والاتصال في فاتورة A4 الرسمية.
* `[MODIFIED]` `d:\projects\sroor\tests\Feature\InvoiceServiceTest.php` - تحديث الاختبارات وإضافة اختبار الفروع المتعددة والتسلسل المستقل لكل فرع.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **صيغة الترقيم المعتمدة:** `INV-{STORE_CODE}-{YYYYMMDD}-{SEQ}`.
  - مثال للمخزن الرئيسي: `INV-MAIN-20260812-0001`
  - مثال لفرع المعادي: `INV-SHOPMAADI-20260812-0001`
  - مثال لعربية التوزيع: `INV-VAN01-20260812-0001`
* **استقلالية السلاسل الرقمية:** كل فرع يبدأ من `0001` يومياً دون أي تصادم أو قفل متبادل بين الكاشيرات في الفروع المختلفة.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم تشغيل اختبارات PHPUnit بنجاح كامل: **85 من أصل 85 اختبار ناجحة 100% (320 assertions)**.
* [x] تم اختبار استقلالية الأرقام بين الفروع المختلفة بنجاح (`test_multi_store_independent_invoice_numbering`).
* [x] تم تشغيل واختبار شاشات الـ POS و Invoices عبر Playwright E2E بنجاح 100%.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. رفع التحديث للسيرفر عند موافقة المستخدم.
