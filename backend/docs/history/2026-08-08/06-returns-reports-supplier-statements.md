# سجل تعديل: تنفيذ منظومة المرتجعات، كشف حساب المورد، وتقارير الأرباح الشاملة (Phase 2 & Phase 3)

* **التاريخ والوقت:** 2026-08-08 18:17
* **الدور المفعل:** Full Stack AI Squad (Backend Architect, Frontend UI, QA Testing)
* **الهدف من التعديل:** تنفيذ منظومة المرتجعات (مبيعات ومشتريات) وعكس حركات المخزون، كشف حساب تفصيلي متحرك للموردين، وشاشة التقارير المالية المتكاملة لحساب تكلفة البضاعة المباعة (COGS) وربحية كل صنف وتقييم المخزون.

---

## 1. الملفات التي تم إنشاؤها وتعديلها (Modified & Created Files)

### 🔄 طبقة الخدمات الخلفية (Backend Services):
* `[NEW]` `app/Services/ReturnService.php` - خدمة معالجة مرتجعات المبيعات (إعادة الكمية للمخزن وتخفيض مديونية العميل) ومرتجعات المشتريات (صرف الكمية وتخفيض مستحقات المورد) داخل `DB::transaction()` وقفل `lockForUpdate()`.

### 🖥️ مكونات Livewire وشاشات العرض:
* `[NEW]` `app/Livewire/ReturnCreate.php` & `resources/views/livewire/return-create.blade.php` - شاشة تسجيل المرتجعات مع بحث سريع عن الأصناف وتحديد نوع المرتجع والكميات المرتجعة بدقة الميزان.
* `[NEW]` `app/Livewire/ReturnIndex.php` & `resources/views/livewire/return-index.blade.php` - سجل استعراض المرتجعات مع الفلترة حسب النوع (مبيعات / مشتريات).
* `[NEW]` `app/Livewire/SupplierStatement.php` & `resources/views/livewire/supplier-statement.blade.php` - كشف حساب متحرك للمورد (توريدات +، سندات صرف -، مرتجعات -، والرصيد بعد كل حركة).
* `[NEW]` `app/Livewire/ReportsIndex.php` & `resources/views/livewire/reports-index.blade.php` - شاشة التقارير والأرباح الشاملة (الإيراد، تكلفة البضاعة COGS، صافي الربح، هامش الربح %، ربحية كل صنف، وتقييم المخزون بسعر التكلفة وسعر البيع).
* `[MODIFIED]` `resources/views/components/layouts/app.blade.php` - ربط سجل المرتجعات والتقارير المالية في القائمة الجانبية (Sidebar).
* `[MODIFIED]` `routes/web.php` - تفعيل المسارات: `/returns`, `/returns/create`, `/suppliers/{id}/statement`, `/reports`.

### 🧪 التحقق والاختبارات:
* `[MODIFIED]` `tests/Feature/LivewirePagesTest.php` - إضافة اختبارات التحقق من صحة صفحات المرتجعات، كشف حساب المورد، وشاشة التقارير والأرباح.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* كل حركة مرتجع تُسجل حركة مخزنية رسمية `StockMovement` مع إثبات رقم السند والسعر والتكلفة لمنع أي تلاعب أو خلل في الجرد.
* تطبيق معادلات `bcmath` في استخراج نسب هوامش الربح لكل صنف.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم تشغيل حزمة الاختبارات بالكامل: **20 passed (57 assertions) in 1.67s**.
* [x] كافة المسارات تعمل بحالة HTTP 200 ومتوافقة مع RTL.

---

## 4. الخطوات التالية (Next Steps)
* تفعيل خصائص الـ PWA و تصدير الكشوفات لـ Excel / CSV.
