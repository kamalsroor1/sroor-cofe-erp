# سجل تعديل: إصلاح أخطاء تكلفة المخزون (المتوسط المرجح) وإعادة احتساب التكلفة التاريخية

* **التاريخ والوقت:** 2026-09-29 19:30
* **الدور المفعل:** Backend Architect (مع QA Testing للاختبارات)
* **الهدف من التعديل:** مراجعة تكلفة المخزون كشفت أن الأرباح المسجلة مبالغ فيها بسبب رصيد أول بتكلفة صفر، ومشتريات بتكلفة صفر، وإلغاء مشتريات لا يعكس المتوسط المرجح، وحذف فيزيائي لحركات المخزون. تم إصلاح الكود ليمنع تكرار الخطأ، وإضافة أمر `inventory:recost` لتصحيح البيانات، وتطبيقه على **النسخة المحلية فقط** (`sroor_prod_copy`) بأسعار الافتتاح المعتمدة من المالك.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[NEW]` `app/Support/WeightedAverageCost.php` - مصدر واحد لحساب المتوسط المرجح: `add()` و `remove()` بـ bcmath (scale 6 داخلياً وتقريب half-up لثلاث خانات).
* `[NEW]` `app/Console/Commands/RecalculateInventoryCostsCommand.php` - أمر `inventory:recost` (dry-run افتراضياً، `--apply`، `--recost-invoices`، `--fix-deposit-costs`، `--items`، `--exclude`، `--opening-costs`، `--report`).
* `[NEW]` `database/migrations/2026_09_29_000001_add_cost_price_to_return_items_table.php` - عمود `return_items.cost_price DECIMAL(12,3) NULL` (محمي بـ `hasColumn` مع `down()` حقيقي).
* `[NEW]` `storage/app/inventory/opening_costs_2026-09-29.json` - أسعار الافتتاح المعتمدة من المالك (المجلد مُستثنى من git ويجب رفعه يدوياً للسيرفر).
* `[MODIFIED]` `app/Models/Item.php` - دالة `effectiveCost()`: المتوسط المرجح إن كان أكبر من صفر وإلا `cost_price`.
* `[MODIFIED]` `app/Services/PurchaseService.php`:
  * رفض سطر شراء بتكلفة صفر.
  * توزيع خصم الفاتورة على تكلفة الوحدة بالقيمة.
  * `effectiveCost()` بدل `?:`.
  * `cancelPurchase` يزيل الكمية الملغاة من المتوسط.
  * `deletePurchase` لم يعد يحذف الحركات.
  * `calculateWeightedAverageCost` يفوّض لـ `WeightedAverageCost`.
* `[MODIFIED]` `app/Services/StockService.php`:
  * `depositStock` داخل `DB::transaction` ويمزج الإيداع في المتوسط ويرفض التكلفة الصفرية غير المعروفة.
  * `deductStock` يقبل `unitCost` اختياري ويسجل `effectiveCost()`.
  * `adjustStock` يسجل `effectiveCost()`.
* `[MODIFIED]` `app/Services/InvoiceService.php`:
  * `effectiveCost()` للبيع.
  * مزج المخزون المرتجع بتكلفة البيع عند إلغاء/تعديل/حذف الفاتورة.
  * **إيقاف الحذف الفيزيائي لحركات المخزون.**
* `[MODIFIED]` `app/Services/ReturnService.php`:
  * مرتجع المبيعات بتكلفة سطر الفاتورة الأصلي، وحفظها في `return_items.cost_price` ومزجها في المتوسط.
  * مرتجع المشتريات يزيل تكلفته من المتوسط.
  * إصلاح المتغير غير المعرّف `$purchase`.
* `[MODIFIED]` `app/Services/StockTransferService.php` - `unit_cost` = `effectiveCost()`.
* `[MODIFIED]` `app/Services/InventoryAnalyticsService.php` و `ReorderAssistantService.php` و `ProfitLossService.php` و `ExportService.php` و `app/Http/Controllers/ReportPrintController.php` - استبدال `weighted_avg_cost ?: cost_price` (البديل لا يعمل لأن "0.000" قيمة truthy) وتقييم المخزون بـ `effectiveCost()`.
* `[MODIFIED]` `app/Models/ReturnItem.php` - إضافة `cost_price` (fillable + cast).
* `[MODIFIED]` `app/Livewire/ItemIndex.php`:
  * منع رصيد أول بتكلفة صفر.
  * إنشاء الصنف مع الإيداع داخل `DB::transaction`.
  * منع تعديل `cost_price` يدوياً لصنف له حركات.
* `[MODIFIED]` `app/Livewire/PurchaseCreate.php` - قاعدة `items.*.cost_price` أصبحت `gt:0`، ومعاينة التكلفة المحملة تشمل الخصم.
* `[MODIFIED]` `app/Livewire/TrashIndex.php` - منع استرجاع فاتورة بيع/شراء محذوفة (أثرها المخزني عُكس عند الحذف) برسالة عربية.
* `[NEW]` `tests/Unit/WeightedAverageCostTest.php` (8 اختبارات)، `tests/Feature/InventoryCostIntegrityTest.php` (23)، `tests/Feature/RecalculateInventoryCostsCommandTest.php` (7).
* `[MODIFIED]` `tests/Feature/InvoiceEditAndDailyJournalTest.php` - الاختبار كان فاشلاً قبل التعديل: كان يستدعي `updateInvoice` والدالة في الكومبوننت اسمها `saveInvoice` منذ commit 837440a0، وأضيف له فرع رئيسي لأن `InvoiceEdit` يتطلب `store_id`. لم تُخفَّف أي assertion.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **مصدر واحد للحساب:** كل عمليات المتوسط المرجح تمر عبر `WeightedAverageCost` بـ bcmath. الاقتطاع القديم (`bcdiv(..., 3)`) كان ينتج مثلاً 229.999 بدل 230 بعد عكس إلغاء.
* **إلغاء/مرتجع الشراء** يزيل الكمية بتكلفة دخولها: `(S×W − q×c)/(S−q)`، ولا يرجع أبداً تكلفة ≤ صفر.
* **الإيداع** يُمزج في المتوسط. الإيداع بتكلفة صفر يُقيَّم بالتكلفة الحالية، ويُرفض إن لم توجد أي تكلفة معروفة.
* **إرجاع البضاعة من فاتورة بيع** (إلغاء/تعديل/حذف) يعود بتكلفة البيع الأصلية ويُمزج في المتوسط.
* **لا حذف فيزيائي لحركات المخزون:** زوج `sales_out` + `cancellation_in` يبقى أثراً دفترياً، فلا توجد فجوات في كارت الصنف.
* **الأمر `inventory:recost`:**
  * يقفل صفوف الأصناف (`lockForUpdate`) قبل قراءة الدفتر، ثم يقفل الفواتير قبل تعديلها، والكل داخل `DB::transaction`.
  * idempotent: الهدف يُشتق من المستندات المصدر وليس من المتوسط المخزن.
  * لا يحذف أي صف.
  * يسجل `audit_logs` لكل صنف وفاتورة (قبل/بعد + `run_id`) وسجل نشاط ملخص وتقرير CSV.
  * تحديث الفواتير يتم بـ query builder حتى لا يتغير `updated_at`.
* **`pint`:** طُبق على الملفات الجديدة فقط. الملفات القديمة غير منسقة بـ Pint، وتشغيله عليها كان سيعيد تنسيق ملفات مالية كاملة ويخفي التعديل الفعلي.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] لا أخطاء Syntax (`php -l` لكل الملفات المعدلة).
* [x] مجموعة الاختبارات الكاملة `php artisan test`: **180 اختباراً ناجحاً / 687 assertion** (قبل التعديل: 142 اختباراً منها 1 فاشل).
* [x] اختبار Rollback: الإيداع المرفوض لا يغير الرصيد ولا ينشئ إيداعاً، وفاتورة الشراء المرفوضة لا تُنشأ.
* [x] تطبيق محلي على `sroor_prod_copy`:
  * نسخة احتياطية: `backups/sroor_prod_copy_before_recost_2026-09-29.sql.gz`.
  * نسخة مقارنة: قاعدة `sroor_before_recost`.
  * migration، ثم dry-run، ثم apply (15 صنفاً، 144 سطراً، 83 فاتورة)، ثم dry-run ثانٍ = **صفر تغييرات**.
  * المتوسط المخزن لـ 29/29 صنفاً = إعادة الحساب المستقلة (Python).
* [x] قائمة الأرباح والخسائر (`ProfitLossService`) قبل/بعد:
  * أغسطس: مجمل الربح 52,746.747 ← 44,983.852.
  * سبتمبر: مجمل الربح 77,917.163 ← 33,321.733.
  * الإجمالي: تكلفة البضاعة المباعة 829,435.089 ← 881,793.414 (+52,358.325).
* [ ] RTL/الوضع الليلي: لا تغييرات واجهات (رسائل تحقق عربية فقط تُعرض في الأماكن الموجودة).

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. مراجعة المالك لتقرير `backups/recost_applied_2026-09-29.csv` وأرقام قبل/بعد.
2. النشر على الإنتاج ينفذه مسؤول السيرفر، وليس الـ agent:
   1. نشر الكود ورفع ملف أسعار الافتتاح.
   2. `php artisan down`.
   3. نسخة احتياطية.
   4. `migrate`.
   5. dry-run ومقارنته بالمحلي.
   6. `--apply --recost-invoices --fix-deposit-costs --exclude=2,7`.
   7. dry-run للتأكد من صفر تغييرات.
   8. `php artisan up`.
3. قرار الأصناف المحذوفة 2 و7 (مبيعات بتكلفة صفر ≈ 11,100 ج.م) — مستثناة حالياً بقرار المالك.
4. مراجعة المشتريات بقيمة صفر (#1، #2، #4، #5، #6) وأثرها على أرصدة الموردين.
5. تدوير كلمة مرور SSH المكتوبة نصاً في سكربتات Python بجذر المشروع.

---

## 5. إصلاحات مراجعة الكود (Review Fixes)
* **[BLOCKER] أمر `inventory:recost`:** يبدأ الحساب بمتوسط صفر.
  * أي رصيد موجود قبل أول وارد له تكلفة (تسوية جرد بالزيادة أو رصيد قديم) أو بيع أثناء متوسط = صفر يجعل الصنف **غير محلول**: يُتخطى بالكامل وينتهي الأمر بـ exit 1، إلا إذا مُررت له تكلفة افتتاحية.
  * لا تُكتب تكلفة صفر أبداً في `invoice_items.cost_price`.
* **أسطر الشراء:** مطابقة سطر الشراء بالكمية ودورياً، فلم يعد (إلغاء ← استعادة ← إلغاء) يسبب `RuntimeException`. أي خطأ في صنف يُسجَّل كغير محلول بدل إسقاط التشغيل كله.
* **الأقفال والتقسيم:**
  * dry-run لا يأخذ أقفالاً (`FOR UPDATE`).
  * إعادة تكلفة الفواتير مقسمة على دفعات من 200.
  * `--apply` مرفوض ما لم يكن التطبيق في وضع الصيانة (`php artisan down`) أو مع `--force`. الخيار موثق في `--help` لأن ترتيب الأقفال (أصناف ← فواتير) عكس `InvoiceService`.
* **ملف التقرير:** يُفتح ويُتحقق منه **قبل** بدء المعاملة.
* **`InvoiceIndex::restoreInvoice`:** يرفض الاستعادة برسالة عربية، وزر الاستعادة استُبدل بشارة "محذوفة". أزرار الاستعادة في سلة المحذوفات معطلة للفواتير والمشتريات.
* **`PurchaseService`:** رفض الخصم السالب، والخصم ≥ إجمالي الأصناف، وأي تكلفة محملة ≤ صفر بعد الخصم.
* **`ItemIndex`:** حقل التكلفة معطل مع تلميح عربي للأصناف التي لها حركات.
* **الاختبارات:**
  * 8 اختبارات جديدة. المجموعة الكاملة **188/188 (719 assertion)**.
  * dry-run على `sroor_prod_copy`: صفر تغييرات، بلا أصناف غير محلولة (باستثناء 2 و7 المستثناة).
  * dry-run على `sroor_before_recost`: أهداف مطابقة تماماً للمطبَّق (29 صنفاً، 144 سطراً، الصنف 16 = 384.398، فرق COGS = 52,358.321).
