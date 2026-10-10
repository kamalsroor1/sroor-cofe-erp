# ☕ وثيقة المكون والصفحة: حاسبة وتوليفة خلطات البن والأصناف المركبة (`CoffeeBlenderView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** معمل توليفة خلطات البن وتجميع المنتجات (Coffee Blender Studio)
* **المسار (Route):** `/coffee-blender`
* **اسم المسار (Route Name):** `coffee_blender.index`
* **الصلاحية المطلوبة (Permission):** `items.create`
* **الملف الرئيسي:** `resources/js/views/CoffeeBlender/CoffeeBlenderView.vue` (~96 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المحرك الرياضي المتخصص لمحامص ومقاهي القهوة المختصة لتركيب وتوليف خلطات الإسبريسو والقهوة المقطرة (Espresso Blends).
  * تحديد نسب مئوية أو أوزان دقيقة بالجرامات للأصناف الخام (مثل: بن كولومبي 60% + بن إثيوبي 40%).
  * الحساب اللحظي والدقيق لتكلفة الجرام والكيلوجرام الناتج بناءً على تكاليف الشراء الحالية للمواد الخام عبر مكتبة `bcmath`.
  * حساب هامش الربح المستهدف واقتراح سعر البيع القطاعي والجملة للخلطة المركبة.
  * خياران تنفيذيان مباشرين:
    1. **توليد فاتورة بيع فورية:** خصم المكونات الخام مباشرة من المخزن وإصدار فاتورة بيع للعميل للخلطة المخصصة.
    2. **حفظ وتجميع صنف مركب:** إنشاء صنف جديد في دليل الأصناف وتحديد وصفته المعتمدة.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
CoffeeBlenderView.vue (~96 lines)
├── CoffeeBlenderHeader.vue           <-- رأس الصفحة، اسم التوليفة، والوزن الإجمالي المطلوب
├── CoffeeBlenderIngredientsCard.vue  <-- جدول اختيار حبوب البن الخام، النسب المئوية %، الأوزان، وتكلفة كل مكون
├── CoffeeBlenderOutputCard.vue       <-- بطاقة النتائج المالية (إجمالي التكلفة، تكلفة الجرام، هامش الربح، أزرار التنفيذ)
└── CoffeeBlenderHistoryModal.vue     <-- نافذة استعراض الوصفات المحفوظة مسبقاً وتطبيقها بضغطة زر
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `AppModal.vue`.
* **المخازن المستخدمة:** `useAuthStore`, `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`) والجرامات والنسب المئوية.

---

## 4. الاعتماديات والـ APIs:
* `POST /api/v1/coffee-blender/calculate`: احتساب تكاليف التوليفة ونسب المكونات لحظياً:
  * **الكنترولر:** `App\Http\Controllers\Api\CoffeeBlenderController@calculate`
  * **Form Request:** `App\Http\Requests\CalculateBlendCostRequest`
  * **Action:** `App\Actions\Blends\CalculateBlendCostAction`
* `POST /api/v1/coffee-blender/invoice`: إصدار فاتورة بيع فورية للخلطة وخصم الحبوب الخام من المخزون:
  * **Form Request:** `App\Http\Requests\CreateBlenderInvoiceRequest`
  * **Action:** `App\Actions\Blends\CreateBlenderInvoiceAction`
* `GET /api/v1/items`: جلب قائمة حبوب البن والمواد الخام المتوفرة بالفرع.

---

## 5. نطاق الفروع وعزل البيانات (Store Scoping):
* تُرسل ترويسة `X-Store-Id` للتحقق من توافر رصيد حبوب البن الخام في المستودع المحدد.
* يتم فحص صلاحية الوصول للمخزن عبر `ClientStoreGuard::concrete($request)`.
* محاولة الخصم من مخزن غير مصرح به تُرجع **HTTP 403** مع كود `store_access_denied`.

---

## 6. القواعد المالية الصارمة والدقة بالجرام:
1. **الدقة المتناهية `DECIMAL(12,3)` و `bcmath`:** نظراً لأن خلطات البن تُحسب بالجرام وكسور الجرام، تُجرى كافة العمليات الحسابية بدقة متناهية وبتقريب متماثل للنصف للأعلى (`symmetric half-up at 3 dp`).
2. **خصم متعدد داخل `DB::transaction()` مع `lockForUpdate()`:** عند إصدار الفاتورة، يتم قفل كافة بنود البن الخام المكونة للتوليفة وخصم أوزانها بالتوازي داخل Transaction ذرية واحدة تمنع كسر قيد المخزون.
