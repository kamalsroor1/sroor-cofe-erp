# سجل تعديل: تطبيق وحماية الصلاحيات والأدوار الشامل في الباك إند والفرونت إند (RBAC Enforcement)

* **التاريخ والوقت:** 2026-08-12 18:15
* **الدور المفعل:** Backend Architect & Frontend UI & QA Testing Agent
* **الهدف من التعديل:** التأكد من تطبيق كافة الصلاحيات (27 صلاحية عبر 7 أقسام) في جميع المسارات، ومكونات Livewire 4 في الباك إند (`abort_if(!auth()->user()->can(...), 403)`)، وحجب الأزرار والأعمدة الحساسة في واجهات Blade (`@can(...)`)، واكتمال حزمة اختبارات المتصفح التفاعلية Playwright E2E لـ 11 اختباراً بنجاح 100%.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[MODIFIED]` `app/Providers/AppServiceProvider.php` - تفعيل `Gate::before` لتجاوز المدير العام (Super-admin bypass).
* `[MODIFIED]` `routes/web.php` - حماية كافة مسارات النظام بـ `middleware('can:...')`.
* `[MODIFIED]` `resources/views/components/layouts/app.blade.php` - استبدال التوجيهات الثابتة بتوجيهات `@can(...)` الدقيقة في السايدبار والهيدر.
* `[MODIFIED]` `app/Livewire/ItemIndex.php` & `resources/views/livewire/item-index.blade.php` - حماية إضافة، تعديل، حذف، واستعادة الأصناف وعمود التكلفة `@can('items.view_cost')`.
* `[MODIFIED]` `app/Livewire/InvoiceCreate.php` & `resources/views/livewire/invoice-create.blade.php` - حماية `pos.access`, `invoices.create`, و `invoices.discount`.
* `[MODIFIED]` `app/Livewire/InvoiceEdit.php` - حماية `invoices.edit` و `invoices.discount`.
* `[MODIFIED]` `app/Livewire/InvoiceIndex.php` & `resources/views/livewire/invoice-index.blade.php` - حماية `invoices.view`, `invoices.cancel`, `invoices.delete`, `trash.access`.
* `[MODIFIED]` `app/Livewire/InvoiceShow.php` & `resources/views/livewire/invoice-show.blade.php` - حماية `invoices.view`, `invoices.delete`, `invoices.edit`.
* `[MODIFIED]` `app/Livewire/StoreIndex.php` - حماية `stores.manage` و `trash.access`.
* `[MODIFIED]` `app/Livewire/StoreStockIndex.php` & `resources/views/livewire/store-stock-index.blade.php` - حماية `items.view`, `items.edit`, `transfers.create`.
* `[MODIFIED]` `app/Livewire/CustomerIndex.php` & `resources/views/livewire/customer-index.blade.php` - حماية `customers.manage`, `customers.statement`, `trash.access`.
* `[MODIFIED]` `app/Livewire/SupplierIndex.php` & `resources/views/livewire/supplier-index.blade.php` - حماية `suppliers.manage`, `suppliers.statement`, `trash.access`.
* `[MODIFIED]` `app/Livewire/ExpenseIndex.php` - حماية `expenses.manage`, `trash.access`.
* `[MODIFIED]` `app/Livewire/ReturnIndex.php` & `app/Livewire/ReturnCreate.php` - حماية `returns.manage`, `trash.access`.
* `[MODIFIED]` `app/Livewire/StockTransferIndex.php` & `app/Livewire/StockTransferCreate.php` - حماية `transfers.view`, `transfers.create`.
* `[MODIFIED]` `app/Livewire/DailyJournalIndex.php` & `app/Livewire/CashShiftManager.php` - حماية `daily_journal.view`, `daily_journal.close_shift`.
* `[MODIFIED]` `app/Livewire/ReportsIndex.php` - حماية `reports.view`.
* `[MODIFIED]` `app/Livewire/CustomerStatement.php` & `app/Livewire/SupplierStatement.php` - حماية `customers.statement`, `suppliers.statement`.
* `[MODIFIED]` `app/Livewire/TrashIndex.php` - حماية `trash.access`.
* `[MODIFIED]` `app/Livewire/CoffeeBlender.php` - حماية `pos.access`, `invoices.create`.
* `[MODIFIED]` `app/Livewire/Auth/RolePermissionManager.php` & `app/Livewire/Auth/UserManager.php` - حماية `roles.manage`.
* `[MODIFIED]` `tests/Feature/RolePermissionTest.php` - تحديث وتوسيع اختبارات الصلاحيات.
* `[MODIFIED]` `tests_e2e/test_01_stores_and_branches.py` - تحديث حزمة الـ 11 اختباراً وتدقيق الـ Assertions.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **Gate Super-Admin Bypass:** استخدام `Gate::before(fn($user, $ability) => $user->hasRole('admin') ? true : null)` يضمن أن المدير العام يمتلك حق الوصول المطلق تلقائياً مع تفويض باقي الأدوار بدقة عبر Spatie Permissions.
* **Double-Layer Guarding:** حماية المسارات برمجياً بالـ middleware، وحماية دوال Livewire بـ `abort_if(!auth()->user()->can(...), 403)`، وحماية عناصر واجهات Blade بـ `@can` و `@cannot`.
* **Cost & Discount Protection:** إخفاء أسعار التكلفة في الشاشات لمن ليس لديهم `items.view_cost`، ومنع تطبيق أي خصم في نقطة البيع إذا لم يكن لدى الكاشير `invoices.discount`.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم فحص خلو الكود من أي أخطاء Syntax أو Missing Imports.
* [x] تم تشغيل واجتياز اختبارات المتصفح التفاعلية Playwright E2E بنسبة 100% (11/11 اختباراً).
* [x] تم تشغيل واجتياز اختبارات PHPUnit كاملة بنسبة 100% (93/93 اختباراً).
* [x] تم فحص التوافق التام مع اللغة العربية وتوجيه RTL والتصميم المتجاوب.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. البدء في السيناريو الثاني من اختبارات الـ E2E (دورة المشتريات والتوريد وحسابات الموردين).
