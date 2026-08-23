# سجل تعديل: منظومة المصاريف الإضافية الداينمك وتوزيع تكلفة الشحن (Dynamic Landed Costs & Multi-Expenses)

* **التاريخ والوقت:** 2026-08-18 23:25
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف من التعديل:** بناء منظومة ديناميكية بالكامل لإضافة وتوزيع أسطر متعددة من المصاريف (شحن، عتالة، كراتين، جمارك، نولون، إكراميات) على فواتير المشتريات والمبيعات، مع توزيع التكلفة تلقائياً على الأصناف لحساب التكلفة المحملة الفعلية ومتوسط التكلفة المرجح (WAC)، وتوليد سندات صرف من الخزينة عند السداد النقدي/الإلكتروني، وعرض تفاصيل المصاريف في شاشات التفاصيل فقط دون إضافتها في قوالب الطباعة.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[NEW]` `database/migrations/2026_08_18_233000_create_additional_expenses_table.php` - جدول `additional_expenses` البوليمورفي وحقول التكلفة والشحن.
* `[NEW]` `app/Models/AdditionalExpense.php` - موديل المصاريف الإضافية مع الـ Casts والدوال المساعدة لمسميات طرق التوزيع والسداد.
* `[MODIFIED]` `app/Models/Purchase.php` - إضافة علاقة `additionalExpenses()` وحقل `additional_expenses_total`.
* `[MODIFIED]` `app/Models/PurchaseItem.php` - إضافة `base_cost_price` و `allocated_expense` لتوثيق التكلفة الأساسية والمحملة.
* `[MODIFIED]` `app/Models/Invoice.php` - إضافة `shipping_cost` وعلاقة `additionalExpenses()`.
* `[MODIFIED]` `app/Services/PurchaseService.php` - منطق توزيع المصاريف المحملة (Landed Costs) بالوزن/الكمية والقيمة والتساوي، وتحديث متوسط التكلفة المرجح، وتوليد سندات الصرف.
* `[MODIFIED]` `app/Services/InvoiceService.php` - دعم المصاريف الإضافية ومصاريف الشحن المتعددة في المبيعات وتحديث الصافي.
* `[MODIFIED]` `app/Livewire/PurchaseCreate.php` - دعم إضافة وحذف أسطر المصاريف ديناميكياً مع محتسب لحظي للتكلفة المحملة لكل صنف.
* `[MODIFIED]` `resources/views/livewire/purchase-create.blade.php` - واجهة إضافة بنود المصاريف بأزرار سريعة وعرض سعر التكلفة بعد المصاريف.
* `[MODIFIED]` `app/Livewire/PurchaseIndex.php` & `resources/views/livewire/purchase-index.blade.php` - إضافة نافذة عرض تفاصيل الفاتورة والمصاريف المحملة بالكامل.
* `[MODIFIED]` `app/Livewire/InvoiceCreate.php` & `resources/views/livewire/invoice-create.blade.php` - إضافة جدول ديناميكي متعدد الأسطر للمصاريف والشحن والإكراميات مع أزرار سريعة وحساب الصافي.
* `[MODIFIED]` `app/Livewire/InvoiceEdit.php` & `resources/views/livewire/invoice-edit.blade.php` - دعم تعديل وحفظ المصاريف الإضافية المتعددة وتحديث سندات الخزينة.
* `[MODIFIED]` `app/Livewire/InvoiceShow.php` & `resources/views/livewire/invoice-show.blade.php` - عرض جدول تفاصيل المصاريف في شاشة تفاصيل الفاتورة فقط دون تغيير قوالب الطباعة.
* `[NEW]` `tests/Feature/LandedCostAndAdditionalExpensesFeatureTest.php` - 6 اختبارات شاملة لاحتساب التكلفة وسندات الخزينة والمبيعات المتعددة.
* `[MODIFIED]` `docs/05-planning/tasks-breakdown.md` - توثيق المرحلة الحادية عشرة.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **معادلة توزيع التكلفة المحملة (Landed Cost Formula):**
  * حسب الكمية / الوزن: `Allocated = Expense * (Item_Qty / Total_Qty)`
  * حسب القيمة: `Allocated = Expense * (Item_Base_Valuation / Total_Valuation)`
  * تكلفة الوحدة المحملة: `Landed_Unit_Cost = Base_Cost + (Allocated / Qty)`
* **حفظ تقييم المخزون المالي:** يتم تمرير `Landed_Unit_Cost` إلى دالة حساب متوسط التكلفة المرجح WAC وإلى حركة المخزون `purchase_in` لضمان أن سعر تكلفة المخزون والأرباح اللاحقة تأخذ في الحسبان مصاريف النولون والشحن والعتالة بدقة تامة.
* **الفصل بين حساب المورد والخزينة:** إذا كان المصروف مسدد من الخزينة (`treasury_cash`, `treasury_instapay`, `treasury_e_wallet`) يتم إنشاء سند صرف تلقائي `Payment` من الصندوق ولا يضاف لرصيد المورد. وإذا كان مضاف لحساب المورد (`supplier_account`) يُضاف لصافي الفاتورة ورصيد المورد.
* **الالتزام بالقواعد المالية `DECIMAL(12,3)` و `bcmath`:** كافة عمليات القسمة والضرب والتوزيع تمت بدقة 3 إلى 6 خانات عشرية باستخدام `bcdiv`, `bcmul`, `bcadd`.
* **الالتزام بعدم التغيير في الطباعة:** قوالب الطباعة (Thermal & A4) تظل بسيطة ونظيفة، بينما تظهر تفاصيل المصاريف في شاشات التفاصيل والمودال فقط.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم التحقق من خلو الكود من أي أخطاء Syntax.
* [x] تم تشغيل كافة اختبارات الـ PHPUnit (125 اختبار بنجاح 100% بدون أي أخطاء أو تحذيرات).
* [x] تم اختبار توزيع التكلفة بالوزن وبالقيمة، وسندات الصرف، واستعادة الفواتير الملغاة.
* [x] تم فحص اتجاه النصوص في العربية RTL والوضع الليلي Dark Mode.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. رفع التحديثات إلى مستودع Git والإنتاج عبر السكربت الآمن.
