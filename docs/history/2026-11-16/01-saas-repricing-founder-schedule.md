# سجل تعديل: إعادة تسعير الباقات وجدول أسعار المؤسسين (3 خطوات) وتمايز الباقات
* **التاريخ والوقت:** 2026-11-16
* **الدور المفعل:** backend-architect / frontend-vue / qa-tester / code-reviewer / security-auditor / docs-historian
* **الهدف:** تطبيق قرارات المالك بتاريخ 2026-11-15 على التسعير: قائمة أسعار جديدة، جدول أسعار للمؤسسين الأوائل (50 عميلاً) على ثلاث خطوات، عروض ترويجية مؤقتة، وتمايز واضح بين الباقات (Enterprise باقة خدمة). العمل **غير مُلتزم به (uncommitted)** وقت كتابة هذا السجل.

## 0. قرارات المالك (2026-11-15)
* **أسعار القائمة (بدون ضريبة قيمة مضافة):** Basic 499 / Pro 999 / Enterprise 1,499 شهرياً. السنوي = 10 أشهر: 4,990 / 9,990 / 14,990.
* **جدول المؤسسين (أول 50 عميلاً دافعاً):**

| الباقة | السنة 1 (founder) | السنة 2 (transition) | من الشهر 25 |
|---|---|---|---|
| Basic | 349 | 429 | 499 (قائمة) |
| Pro | 699 | 849 | 999 (قائمة) |
| Enterprise | 1,099 | 1,299 | 1,499 (قائمة) |

  * أرقام Enterprise (founder/transition) **اشتقها Claude**: المالك اختار 1,499 بينما الأرقام الموصى بها في الدراسة افترضت 1,999. تحتاج تأكيد المالك.
* **العرض الترويجي "كان/الآن"** (مثلاً 650 -> 499) يظهر فقط أثناء عرض زمني حقيقي ساري.
* **تمايز الباقات:**
  * Enterprise = باقة خدمة: الدعم المميز مشمول وغير قابل للشراء كإضافة هناك، الـ onboarding مجاني مع الدفع السنوي، وأُزيل `api.access`.
  * Basic يرى رقم **إجمالي الربح** فقط؛ مقارنة الفروع وربحية الأصناف نُقلا إلى `reports.advanced` (Pro فما فوق).
  * لا حدود على عدد الفواتير/الأصناف في Basic.
* **المصادر:** [competitor-pricing-research-2026-11.md](../../03-architecture/competitor-pricing-research-2026-11.md)، [plan-differentiation-opinion-2026-11.md](../../03-architecture/plan-differentiation-opinion-2026-11.md).

## 1. الملفات المعدلة
المستند مبني على `git status` (الشجرة تحوي تعديلات أخرى غير متعلقة، مثل Android ولغات عامة؛ لا تُنسب لهذا السجل). الملفات المتعلقة:

**Backend (جديد)**
* `[NEW]` `backend/app/Enums/Billing/FounderPriceStep.php` — خطوات Founder / Transition / List.
* `[NEW]` `backend/app/Support/Billing/PlanPromotion.php` — منطق العرض الترويجي الساري.
* `[NEW]` `backend/app/Http/Resources/Billing/PriceScheduleResource.php` — `price_schedule` في حمولة اشتراك المستأجر.
* `[NEW]` `backend/app/Http/Controllers/Api/Central/PlanController.php` — `POST/PUT` للباقات بإعدادات كاملة.
* `[NEW]` `backend/app/Http/Requests/StorePlanRequest.php` و `backend/app/Http/Requests/Concerns/ValidatesPlanPayload.php` — تحقق مشترك للإنشاء والتعديل.
* `[NEW]` `backend/app/Actions/Plans/CreatePlanAction.php`، `backend/app/DTOs/Plans/{PlanDTO,PlanWriteResult}.php`، `backend/app/Services/Plans/PlanSettingsWriter.php` — الإنشاء والكتابة الذرية للإعدادات (الباقة الجديدة مخفية افتراضياً).
* `[NEW]` أربع migrations مركزية: `2026_11_15_100000_add_founder_transition_and_promotion_to_plans_table`، `2026_11_15_100100_apply_2026_11_price_list`، `2026_11_15_100200_add_included_billing_cycle_to_plan_addon_table`، `2026_11_15_100300_apply_enterprise_service_plan`.

**Backend (معدّل)**
* `[MODIFIED]` `backend/app/Services/Billing/FounderPricingService.php` — الخطوات الثلاث (`stepFor`, `stepPrice`, `planPrice`, `nextStepChange`, `transitionUntil`)، العدّ بالدورات المفوترة لا بالتواريخ فقط.
* `[MODIFIED]` `backend/config/billing.php` — `founder.transition_months = 12`، `founder.notice_days = 30`.
* `[MODIFIED]` `IssueTrialEndInvoicesAction`، `IssueSubscriptionInvoiceAction`، `RequestRenewalInvoiceAction`، `IssueSubscriptionInvoiceDTO`، `BillingInvoiceResource` — سعر المؤسس عند نهاية التجربة، و`price_step` على بنود الفاتورة.
* `[MODIFIED]` `GetTenantSubscriptionAction`، `TenantSubscriptionSummaryResource` — `price_schedule` مع `notice_due`.
* `[MODIFIED]` `GetPublicPlansAction`، `PublicPlanResource`، `PlanResource`، `Plan`، `PlanAddon`، `Addon`، `PublicCatalogCache` — العرض الترويجي، خانات المؤسسين، `included_addons`، `tax_rate`.
* `[MODIFIED]` `UpdatePlanAction`، `UpdatePlanRequest`، `SuperAdminApiController`، `GetSuperAdminPlansDataAction` — تعديل كامل الإعدادات.
* `[MODIFIED]` `ProfitVisibility`، `GetInventoryValuationReportAction`، `ReportController`، `DashboardCache`، `GetDashboardOverviewAction` — إخفاء الربح حسب الباقة (يشمل تجريد تقريري inventory/comprehensive)، وحماية `/reports/items` و`/reports/stores`، ومفتاح كاش لوحة التحكم يشمل رؤية الربح.
* `[MODIFIED]` `database/seeders/Catalog/*` و`lang/{ar,en}/*.php` — كتالوج الباقات والنصوص.

**Frontend**
* `[NEW]` `Components/SuperAdmin/PlanEditor/*` (`PlanEditorModal` + أقسام الأساسيات/الأسعار/العرض/الحدود/الميزات/الإضافات + `PlanPriceInput`)، `Composables/usePlanEditor.js`، `helpers/planEditor.js`.
* `[NEW]` `Components/Landing/PlanPrice.vue`، `PlanIncludedAddons.vue`، `Landing/Pricing/PricingFounderSchedule.vue` — بطاقات التسعير العامة مع الضريبة من إعداد المنصة، قسم جدول المؤسسين، وأسعار مشطوبة للعروض.
* `[NEW]` `Components/Billing/SubscriptionPriceSchedule.vue` — جدول الأسعار في فوترة المستأجر مع لافتة `notice_due`.
* `[NEW]` `Components/Reports/ReportsLockedSection.vue` — تبويبات التقارير المقفلة.
* `[MODIFIED]` `PricingPlans.vue`، `PlansShowcase.vue`، `HomePlans.vue`، `PlanCard.vue` (عام + SuperAdmin)، `PlansGrid.vue`، `TenantBillingSection.vue`، `BillingInvoiceDocument.vue`، `ReportsNavigationTabs.vue`، `useReports.js`، `usePublicPlans.js`.
* `[DELETED]` `Components/SuperAdmin/EditPlanModal.vue` — استُبدل بـ `PlanEditorModal`.

**اختبارات**
* `[NEW]` `FounderPriceScheduleTest`، `FounderScheduleTermChainTest`، `SuperAdminPlanCreateApiTest`، `SuperAdminPlanWriteRulesApiTest`، `PublicPlansPromotionApiTest`، `tests/js/planEditor.test.js`.
* `[MODIFIED]` `FounderPricingServiceTest`، `IssueTrialEndInvoicesCommandTest`، `ApprovedPlanCatalogMigrationTest`، `PlansSchemaMigrationTest`، `PublicPlansApiTest`، `ProfitReportsGatingApiTest`، `SuperAdminPlansApiTest`، `landingCatalog.test.js` وغيرها.

**توثيق**
* `[NEW]` `docs/03-architecture/competitor-pricing-research-2026-11.md`، `plan-differentiation-opinion-2026-11.md` (أُعدّا قبل هذا السجل).
* `[MODIFIED]` `docs/pages/super-admin-plans.md` — شجرة المحرر الجديد و`POST /plans`.

## 2. القرارات التقنية
* **العدّ بالدورات المفوترة:** خطوة المؤسس تحتاج نافذة التاريخ **وعدد الأشهر المفوترة** معاً (`stepFor()`)، لتفادي انحراف التواريخ (مراجعة M2). نهاية الـ transition **مشتقة** (`founder_price_until + transition_months`) ولا تُخزَّن، فتغيير القيمة في الإعداد يحرك النهاية لكل المؤسسين (قرار منتج مثل `months`).
* **الفاتورة تُسعَّر بالخطوة عند بداية الدورة**، وفاتورة نهاية التجربة تستعمل سعر المؤسس إن وُجدت خانة حرة. إن نفدت الخانات قبل الدفع تُطبق قاعدة "السعر المُكرَّم" (honoured price).
* الخطوة التي لا يوجد لها سعر على الباقة للدورة تسقط إلى سعر القائمة. الإضافات تدفع سعرها كاملاً دائماً (قرار Q-E4 قائم).
* **لا إشعار فعلي:** لا توجد قناة تواصل مع المالك، فالتنبيه لافتة داخل التطبيق فقط (`notice_due` قبل `notice_days = 30` يوماً).
* **العرض "كان/الآن"** يُحسب في `PlanPromotion` ويظهر فقط أثناء عرض زمني ساري، حتى لا يصبح خصماً وهمياً دائماً.
* **إنشاء الباقات:** الباقة الجديدة مخفية افتراضياً حتى لا تظهر في الصفحة العامة قبل اكتمالها؛ التحقق في trait مشترك والكتابة في `PlanSettingsWriter`.
* **الربح:** قرار نقل مقارنة الفروع وربحية الأصناف إلى `reports.advanced` يُنفَّذ في الخادم (`ProfitVisibility` + بوابة المسارين)، لا بالإخفاء في الواجهة فقط؛ ومفتاح كاش الداشبورد يشمل رؤية الربح كي لا يتسرب رقم لباقة أدنى.

## 3. التحقق والاختبار
* `php artisan test --filter='FounderPricing|SuperAdminPlan|PublicPlans|ProfitReportsGating'`: 67/67 ناجح (بعد مرحلة الباك إند الأولى).
* qa-tester أضاف 115 اختباراً (`FounderPriceScheduleTest`، `PublicPlansPromotionApiTest`، `SuperAdminPlanWriteRulesApiTest`) كلها ناجحة، و`FounderScheduleTermChainTest` (9 حالات: بداية 31 يناير، 29 يناير، 28/29 فبراير).
* بعد إصلاحات المراجعة: الحزم المتأثرة 1,129/1,132، والفشل الباقي سابق للعمل.
* الحدود الجديدة: `PlanUsageLimitsApiTest` + الاستيراد + الإعدادات + الكاشير والتزامن 344/344، و`Migration|Rollback` 104/104، و`ImportApiTest|SubscriptionUsageApiTest|PlanUsageLimitsApiTest|PlanEnforcementApiTest` 72/72.
* الـ suite الكاملة بـ `memory_limit=2G`: 3,872 اختباراً، 3,831 ناجحاً، 41 فاشلاً. 23 منها (الاستيراد واستهلاك الاشتراك) كانت بسبب تشغيل الـ suite أثناء تعديل حد التخزين، وأُعيد تشغيلها فنجحت. الـ 18 الباقية سابقة للعمل: `StoreIsolationReturnsPurchasesExpensesTest` (15)، `InvoicePrintRoutesTest` (2)، `TenantBillingApiTest` حالة المتجر الموقوف (1). الـ suite في عملية واحدة تنهار أحياناً ("Premature end of PHP process") حتى على `HEAD` نظيف.
* `npm run build` ناجح، و`node --test tests/js/*.test.js` 362/362، وESLint/Prettier نظيفة على الملفات المعدلة.
* Pint نظيف على الملفات المعدلة. Larastan: لا أخطاء جديدة، والـ baseline لم يكبر (الأخطاء الباقية في ملفات لم تُلمس).
* **فحص المتصفح:** صفحة `/pricing` محلياً: الكروت (499 مشطوب و349)، قسم المؤسسين بالخطوات الثلاث، والإضافات المضمّنة في Enterprise. لا أخطاء console. سطر «شامل الضريبة» مخفي لأن نسبة المنصة محلياً 0.
* **لم يُختبر:** محرر الباقات في السوبر أدمن (يتطلب دخولاً مركزياً بـ 2FA)، وعرض الاستهلاك الجديد في صفحة اشتراك العميل، وتبويبات التقارير المقفولة. لا اختبار تزامن لحد الفواتير.

## 3.1 المراجعات (code-reviewer + security-auditor)
* **الأمان:** لا ثغرات حرجة أو عالية ولا تسريب بين المحلات. M1 تسريب ربحية الأصناف من `/reports/inventory` و`comprehensive`: أُصلح بـ `ProfitVisibility::inventory()`. M2 اشتقاق COGS/الهامش/الصافي من الربح الإجمالي: قبله المالك (2026-11-16). Low: الباقة الجديدة مخفية افتراضياً، ومنع الإضافات المتوقفة، ومفتاح كاش الداشبورد: أُصلحت.
* **مراجعة الكود (التسعير):** تكرار 13 شهراً بسعر المؤسس عند البدء يوم 29–31: أُصلح بالعدّ بالدورات المدفوعة. الضريبة الثابتة 14% في الواجهة: صارت `tax_rate` من إعدادات المنصة. تبويبات التقارير المقفولة لـ Basic: قفل برسالة «متاح في Pro». سعر المؤسس أعلى من القائمة: 422. تواريخ العرض بالـ offset: أُصلحت.
* **مراجعة الكود (الحدود):** APPROVE WITH NITS. أُضيف فهرس `invoices(created_at)`، وملفات الاستيراد المتروكة لا تُحسب بعد ساعة، وتضييق الـ catch في `SettingController`، وقراءة اللوجو السابق داخل القفل. مقبول وموثّق: في الباقة المحدودة تتسلسل مبيعات المحل على قفل صف الفروع.

## 4. ملاحظات / ديون تقنية / إجراءات للمالك
* **إجراء للمالك:** `billing_tax_rate` للمنصة قيمته الافتراضية 0؛ يجب ضبطه على **14** من إعدادات المنصة في السوبر أدمن، وإلا ستظهر الأسعار والفواتير بدون ضريبة.
* **قرار مُتخذ (2026-11-16):** Basic يرى إجمالي الربح، ويُقبل أن COGS والهامش والصافي للفترة قابلة للاشتقاق منه.
* **قرار معلّق:** الفاتورة الأولى التي يصدرها الأدمن بدون خانة founder ما زالت تستهلك خانة.
* **أُصلح (2026-11-16) بموافقة المالك:** `max_invoices_per_month` مفروض على كل فاتورة بيع (يشمل الملغاة، بشهر العمل)، و`max_storage_mb` مفروض على رفع اللوجو والاستيراد (إيصالات الدفع مستثناة عمداً)، ومسار CSV لسجل النشاط محمي بـ `feature:audit.logs`. الحقلان عادا إلى محرر الباقات وصفحة الاستهلاك.
* **قبل النشر:** أي باقة مخصّصة قديمة مخزّن فيها 100 فاتورة / 500 ميجا ستُطبق عليها الحدود فعلاً؛ تُراجع قائمة الباقات.
* `docs/sroor-erp-pricing-and-features-brochure.html` ما زال بالأسعار القديمة.
* لا يوجد إشعار push/بريد لتجديد الأسعار.
* لا توجد وثائق `docs/modules/` للفوترة تذكر الأسعار القديمة (بحث ولم يُعثر)؛ أما `docs/03-architecture/pricing-model-recommendation-2026-10.md` و`pricing-restructuring-and-packages-study.md` فسجلات قرار تاريخية لم تُعدَّل.

## 5. رسالة commit مقترحة
```
feat(billing): reprice plans and add 3-step founder price schedule

- list prices 499/999/1499, yearly = 10 months
- founder -> transition -> list steps counted by billed cycles
- plan promotions, public plan founder slots and included add-ons
- super-admin plan create/update with full settings
- Enterprise as service plan; Basic profit visibility limits
- enforce monthly invoice cap, storage cap and audit-log CSV gate
```
