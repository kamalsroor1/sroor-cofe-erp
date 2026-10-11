# تقرير تسليم مهمة Antigravity (E2E Business Flows & Documentation Refresh) — جولة المراجعة والإصلاحات

**التاريخ:** 11 أكتوبر 2026  
**الفرع:** `feature/e2e-docs-antigravity`  
**بيئة العمل:** `D:\projects\sroor-antigravity-e2e`  
**فريق العمل:** LEAD Agent (Antigravity) بمشاركة Lane D (Docs Historian) و Lane E (QA Tester)

---

## 1. ملخص تنفيذي (Executive Summary)

تم إنجاز جولة المراجعة والإصلاحات الشاملة (PR #9 Fix Round) استجابة لملاحظات مراجعة الكود، مع الالتزام التام بالقواعد المعمارية في `AGENTS.md` و `.claude/rules/`:
1. **دمج قاعدة الفروع الأحدث (Merge Base):**
   - تم دمج أحدث تغييرات `origin/feature/multi-tenant` بنجاح وتوفير أداة المصادقة الموحدة `e2e/utils/e2e-user.js`.
2. **Lane E (معالجة ملاحظات الاختبارات E2E):**
   - إزالة أي إشارة أو fallback لكود بيئة العميل الحقيقي (`2M`) واستيراد `E2E_WORKSPACE_CODE` من `e2e-user.js` مع رمي استثناء صريح عند غياب ملف الجلسة.
   - إلغاء وسم الاختبار الكامل بـ `test.fail()` على flows 1 و 4 وعزل فحوصات BUG-01 في اختبارات مخصصة لضمان تشغيل كافة خطوات التدفق والتحقق.
   - تصحيح كافة المقارنات المالية لتكون نصوص Decimal محكمة بدقة 3 خانات (`DECIMAL(12,3)`) بدلاً من `parseFloat` و `toBeCloseTo`.
   - قيادة واجهة الـ POS في flows 1 و 10 عبر معرّفات `data-testid="pos-product-tile"` بدلاً من الاكتفاء بالـ API.
   - إزالة الفحوصات الشرطية الصامتة (`if (...)`) وفرض التحقق غير المشروط (Unconditional Assertions).
   - التحقق الصريح من كود الخطأ `store_access_denied` واستخدام دور `storekeeper` الذي يمتلك `transfers.create` لإثبات فحص عزل الفروع.
   - إضافة تنظيف آلي للبيانات في خطوة `afterAll` لحماية حالة البيئة المحلية.
   - استخدام أرقام هواتف وهمية بوضوح (`0100009xxxx`).
   - التحقق من تنسيق Prettier لجميع الملفات بنسبة 100%.
3. **Lane D (استعادة وتحديث التوثيق الشامل):**
   - استعادة كافة الأقسام الإلزامية في وثائق الصفحات الـ 36 (التجاوب والوضع الليلي، مفاتيح الترجمة، وسجل الاختبارات).
   - تحديث وثائق شاشات السوبر أدمن لتعكس مسارات `backend/routes/central.php` وبوابات المصادقة المركزية `AuthenticateCentral` وصلاحيات `CentralPermission` وحماية العمليات الحساسة بـ 2FA Step-Up (`RequireRecentTwoFactor`).
   - تصحيح وصف تهيئة المستأجر بأنها وظيفة طابور خلفية (`ProvisionTenantJob`) عبر `ProvisionerMySQLDatabaseManager` وليست داخل معاملة قاعدة بيانات.
   - توثيق آليات الأرشفة الآمنة وإلغاء الأرشفة عبر `TenantArchiveController` وتأكيد أن حذف DB مقتصر على أمر CLI `php artisan tenants:purge`.
   - إدراج قائمة العناصر غير المتحقق منها ("غير متحقق") بصراحة وشفافية.

---

## 2. تقرير عيوب النظام المكتشفة (Defects / App Bugs Found)

### 🐛 BUG-01: تكرار احتساب مبيعات الكاش في الرصيد المتوقع للوردية (Shift Expected Cash Double-Counting)
- **الموقع:** `backend/app/Services/ShiftService.php:129-132` (داخل دالة `calculateShiftTotals()`) و `InvoiceService.php:291`.
- **خطوات إعادة الإنتاج:**
  1. فتح وردية جديدة برصيد افتتاحي `500.000 ج.م`.
  2. إجراء فاتورة بيع كاش عبر POS بقيمة `100.000 ج.م`.
  3. استعلام بيانات الوردية الحالية عبر `GET /api/v1/shifts/current`.
- **السلوك المتوقع:**
  - الرصيد المتوقع للنقدية = الرصيد الافتتاحي (`500.000`) + مبيعات الكاش (`100.000`) = `600.000 ج.م`.
- **السلوك الفعلي السابق:**
  - الرصيد المتوقع للنقدية كان يظهر `700.000 ج.م`.
- **السبب الجذري في الكود:**
  - في الكود الأصلي لـ `ShiftService.php`:
    ```php
    $paymentsCollected = (string) (Payment::where('created_at', '>=', $openedAt)
        ->whereNotNull('customer_id')
        ->where('payment_method', 'cash')
        ->sum('amount') ?? '0.000');
    ```
    نظراً لأن `InvoiceService.php:291` ينشئ سند دفع مع `customer_id` لكل فاتورة، كان يتم جمع مبيعات الفواتير ومضاعفتها مرة أخرى من جدول المدفوعات دون استبعاد مدفوعات الفواتير، مع غياب فلتر الفرع (`store_id`) مما كان يسرب مدفوعات الفروع الأخرى إلى الخزينة.
- **الحالة الحالية:**
  - تم إصلاح العيب في قاعدة `feature/multi-tenant` عبر حصر مبيعات الكاش في سندات القبض `cashVouchers()->collectedAtSale()` مع تطبيق فلتر الفرع بدقة، واختباره بنجاح في `Tests\Feature\ShiftExpectedCashTest`.

---

### 🐛 BUG-02: غياب حقل متوسط التكلفة المرجح (WAC) عن مورد الصنف `ItemResource`
- **الموقع:** `backend/app/Http/Resources/ItemResource.php` (السطر 34 وما بعده).
- **السلوك المتوقع:**
  - عند استدعاء `GET /api/v1/items/{id}` بعد تسجيل فاتورة مشتريات (10 وحدات بسعر 100 + 10 وحدات بسعر 120)، يجب أن يعيد المورد قيمة `weighted_avg_cost: 110.000`.
- **السلوك الفعلي:**
  - حقل `weighted_avg_cost` غير مُدرج في مصفوفة الإرجاع الخاصة بـ `ItemResource` ويظهر `cost_price` فقط (المحدد بسعر الشراء الأخير 120.000).
- **الإجراء المتخذ:**
  - عزل الفحص في اختبار مستقل موسوم بـ `test.fail()` دون إضعاف الـ assertion، مع توثيقه للمطورين لإضافة الحقل إلى `ItemResource`.

---

## 3. قائمة معرّفات الاختبار الموصى بإضافتها (Recommended Test IDs)

لتسهيل أتمتة الاختبارات واستقرارها مستقبلاً، يوصى بإضافة معرّفات `data-testid` التالية في واجهات الفرونت إند:
1. `pos-receipt-print-btn`: زر طباعة الإيصال في نافذة معاينة الإيصال (`PosReceiptModal.vue`).
2. `shift-close-submit-btn`: زر تأكيد إغلاق الوردية في نافذة الإغلاق واليومية العامة.
3. `stock-transfer-form`: حاوية نموذج إنشاء إذن التحويل المخزني.
4. `customer-statement-balance-card`: بطاقة عرض الرصيد الحالي في شاشة كشف حساب العميل.

---

## 4. قائمة ما لم يتم التحقق منه ("غير متحقق")

بناءً على معايير الأمان وقواعد حماية بيئة الإنتاج:
1. **غير متحقق:** الاتصال بقواعد البيانات الحية أو تشغيل اختبارات E2E على نطاق الإنتاج `baraa-solutions.com` (محظور بموجب قواعد حماية السيرفر الحي).
2. **غير متحقق:** تنزيل حزم APK الحقيقية كبيرة الحجم عبر شبكات OTA الحية في اختبارات E2E (تم الاعتماد على اختبارات الوحدات `ReleaseBinaryInspectorTest`).
3. **غير متحقق:** محاكاة الدخول كأدمن من السوبر أدمن على مستأجر حقيقي خارج البيئة المحلية.

---

## 5. مخرجات الفحص والتحقق (Verification Summary)

* **تنسيق Prettier:**
  ```text
  npx prettier --check e2e/flows/*-business-flow.spec.js e2e/utils/flows/flow-helpers.js
  Checking formatting...
  All matched files use Prettier code style!
  ```
* **فهرس اختبارات Playwright:**
  ```text
  npx playwright test business-flow --list
  Total: 37 tests in 11 files
  ```
