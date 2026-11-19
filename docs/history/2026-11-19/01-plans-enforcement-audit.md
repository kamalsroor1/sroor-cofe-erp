# سجل تعديل: مراجعة تطبيق حدود الباقات ومميزاتها من أولها لآخرها
* **التاريخ والوقت:** 2026-11-19
* **الدور المفعل:** qa-tester / backend-architect / frontend-vue
* **الهدف:** التأكد إن كل حد (limit) وكل ميزة (feature) في الباقات متطبقة فعلًا على السيرفر في كل مسار، وإثبات ده باختبارات، وإصلاح أي ثغرة.

## الخلاصة في سطرين
* **كل الحدود التسعة متطبقة على السيرفر في كل مسار إنشاء لقيناه** (22 مسار × 3 حالات = 66 حالة في مصفوفة اختبار، كلها نجحت من أول تشغيل).
* **لقينا 3 ثغرات واتصلحوا:** (1) تصدير الفواتير CSV كان مفتوح للباقة المجانية رغم إن `reports.export` مش فيها، (2) تنبيهات تيليجرام كانت بتتبعت لأي محل حتى لو باقته من غير `telegram.notifications`، (3) **عملاء بلا حد**: تغيير اسم/رقم "العميل النقدي" كان بيخليه يتحسب عميل عادي، والبيعة اللي بعدها بتعمل عميل نقدي جديد برّه الحد، وتتكرر. وكمان قفلنا صفحات الطباعة الثلاثة بمميزاتها.

## 1. الملفات المعدلة
* `[NEW]` `backend/tests/Feature/Entitlements/LimitEnforcementMatrixTest.php` — مصفوفة (مورد × مسار إنشاء) بـ data provider: تحت الحد ينجح، اللي بعد الحد 403، الحد `0` = مش متاح، `null` = بلا حد. واختبار حارس بيفشل لو اتضاف `LimitResource` جديد من غير مسار في المصفوفة.
* `[NEW]` `backend/tests/Feature/Entitlements/LimitReachedDailyWorkTest.php` — قرار المالك عند 100%، وتطابق endpoint بانر الكاشير مع الـ checkout، وتغيير الحد من محرر الباقات في الكونسول.
* `[NEW]` `backend/tests/Feature/Entitlements/FeatureGateMatrixTest.php` — كل route عليه `feature:X` بيرجع 403 `subscription.feature_unavailable` لباقة من غير X (route بـ route)، وقائمة موثقة بالمميزات اللي مالهاش تطبيق، واختبارات الإصلاحين.
* `[NEW]` `e2e/flows/plan-limits-flow.spec.js` — Playwright: بانر الكاشير (قريب / وصل)، منع البيع قبل إرسال أي طلب، ويدجت الاستخدام في الداشبورد، وتنبيه الترقية وزرّه بيودي على `/billing`.
* `[MODIFIED]` `backend/routes/api.php` — `GET /invoices/export` بقى عليه `feature:reports.export`.
* `[MODIFIED]` `backend/app/Services/TelegramService.php` — `isShopEnabled()` بقت بتشترط إن باقة المحل فيها `telegram.notifications`.
* `[MODIFIED]` `backend/resources/js/views/Invoices/InvoicesView.vue` و`backend/resources/js/Components/Invoices/InvoicesBulkActionsBar.vue` — زرار تصدير CSV (من القائمة ومن التحديد الجماعي) بيستخبى لو الباقة مفيهاش `reports.export`.
* `[MODIFIED]` `backend/package.json` — سكربت `npm run e2e:plans`.
* `[MODIFIED]` `backend/app/Actions/Customers/UpdateCustomerAction.php` + `backend/lang/{ar,en}/customers.php` (`walk_in_identity_locked`) — العميل النقدي اسمه ورقمه ثابتين (422)، وباقي بياناته بتتعدل عادي.
* `[MODIFIED]` `backend/routes/tenant.php` — `feature:printing.thermal` و`feature:printing.a4` على صفحات طباعة الفاتورة، و`feature:items.movements` على طباعة حركة الصنف (كانت بتعدي من غير قفل الـ API).
* `[MODIFIED]` `backend/resources/js/Components/Settings/Shell/SettingsTabContent.vue` — تبويب إشعارات تيليجرام بقى جوه `FeatureGate`: الباقة اللي مفيهاش الميزة بتشوف رسالة "مقفولة" بدل فورم بيتحفظ ومفيش حاجة بتوصل.

## 2. مصفوفة الحدود (`LimitResource`)

نقطة التطبيق في كل الحالات: `PlanEnforcer::assertWithinLimit()` جوه `DB::transaction()` وبعد قفل على أقدم صف في `stores` (mutex للمستأجر)، والحد الفعلي من `TenantEntitlementService` (الباقة + الإضافات + استثناءات المحل في `tenant_limit_overrides`).

| الحد | مسارات الإنشاء ونقطة التطبيق | الاختبار | الحالة |
|---|---|---|---|
| `users` | `POST /users` ← `CreateUserAction` (النشط وغير النشط بيتحسبوا). مفيش مسار دعوة ولا استرجاع مستخدم من سلة المهملات | Matrix `users.api` + `PlanEnforcementApiTest` | ✅ |
| `stores` (فروع) | `POST /stores` ← `CreateStoreAction`؛ استرجاع من السلة ← `RestoreTrashRecordAction`؛ تغيير النوع ← `UpdateStoreAction` | Matrix `stores.api/restore` + `PlanEnforcementApiTest` | ✅ |
| `warehouses` | نفس مسارات الفروع (bucket منفصل بالنوع `main_warehouse`)، وتحويل فرع لمخزن | Matrix `warehouses.api/restore/retype` | ✅ |
| `vans` | نفس المسارات بالنوع `wholesale_van` | Matrix `vans.api/restore` | ✅ |
| `items` | `POST /items` ← `CreateItemAction`؛ استيراد Excel ← `CommitImportAction` (الدفعة كلها بترفض)؛ استرجاع من السلة | Matrix `items.api/import/restore` | ✅ |
| `customers` | `POST /customers`؛ إضافة سريعة في الكاشير `POST /pos/quick-customer`؛ استيراد؛ استرجاع. العميل النقدي مش بيتحسب ومش بيترفض، **ومبقاش ينفع يتغير اسمه أو رقمه** (`UpdateCustomerAction`) | Matrix ×4 + `PartyLimitsApiTest` + `test_renaming_the_walk_in_customer_cannot_free_a_customers_slot` | ✅ **اتصلحت ثغرة** |
| `suppliers` | `POST /suppliers`؛ استيراد؛ استرجاع | Matrix ×3 + `PartyLimitsApiTest` | ✅ |
| `invoices_month` | `InvoiceService::confirmInvoice()` واللي كل فواتير البيع بتعدي عليه: `POST /invoices`، `POST /pos/checkout`، `POST /mixes/invoice`، `POST /coffee-blender/invoice`. إعادة إرسال فاتورة محفوظة (offline replay) بتتعاد عادي حتى عند الحد | Matrix ×4 + `PlanUsageLimitsApiTest` + `LimitReachedDailyWorkTest` | ✅ |
| `storage_mb` | `PlanEnforcer::withinStorageLimit()` على رفع اللوجو (`UploadTenantBrandAssetAction` والإعدادات القديمة) وعلى ملف الاستيراد (`ImportSessionStore`) | `PlanUsageLimitsApiTest` (6 اختبارات) | ⚠️ متطبق، بس بيحسب **الملفات بس** (جدول `media` + ملفات الاستيراد الحية)، **مش حجم قاعدة البيانات**. ده مكتوب في `TenantStorageUsage`؛ لو المقصود في التسويق إن 200MB تشمل البيانات، فده مش متطبق |

### قواعد عامة اتأكدنا منها
| القاعدة | الاختبار | الحالة |
|---|---|---|
| تحت الحد بينجح، والسجل اللي بعد الحد بيرجع 403 `subscription.limit_reached` مع `details.limit` و`details.max`، ومفيش أي كتابة | Matrix (22 مسار) | ✅ |
| الحد `0` = غير متاح (أول سجل بيترفض) | Matrix (22 مسار) | ✅ |
| `null` = بلا حد | Matrix (22 مسار) | ✅ |
| عند 100% من حد الفواتير: القراءة والتقارير، المرتجعات، تحصيل المديونية، تعديل عميل، قفل الوردية كلها شغالة؛ البيع الجديد بس اللي بيترفض | `LimitReachedDailyWorkTest::test_at_the_monthly_invoice_cap_only_a_new_sale_is_refused` | ✅ |
| محل فوق الحد بعد downgrade: بياناته كلها باقية، بيقرا ويعدل، ومبيقدرش يضيف | `PlanEnforcementApiTest`, `PartyLimitsApiTest`, ومحرر الباقات في `LimitReachedDailyWorkTest` | ✅ |
| `GET /pos/invoice-limit` بيقول نفس اللي الـ checkout بيطبقه، فاتورة بفاتورة، ونفس رقم صفحة الاشتراك/الداشبورد | `LimitReachedDailyWorkTest::test_the_pos_banner_endpoint_matches_the_checkout_one_sale_at_a_time` | ✅ |
| تغيير رقم الحد من محرر الباقات (`PUT /super-admin/plans/{id}`) بيوصل للمحلات الموجودة في الطلب اللي بعده (رفع، بلا حد، تخفيض تحت الاستخدام) | `LimitReachedDailyWorkTest::test_a_limit_changed_in_the_console_plan_editor_reaches_existing_shops_at_once` + `EntitlementCacheScopeTest` | ✅ |
| استثناء المحل (رفع، تخفيض، بلا حد، منتهي) بيكسب على الباقة | `TenantOverridesEntitlementTest` | ✅ |
| عزل المستأجرين في العد | `PartyLimitsApiTest`, `PlanUsageLimitsApiTest`, `PosInvoiceLimitApiTest` | ✅ |

### `invoices_month` بالتفصيل
* **بيتحسب إيه:** كل صف في `invoices` (فواتير البيع، ومنها فواتير الخلطات) **اتعمل** في الشهر الحالي، **حتى الملغي والمحذوف** عشان محدش يلغي ويعيد. المرتجعات والمشتريات جداول تانية ومش بتتحسب.
* **الشهر:** الشهر التقويمي لـ `TenantClock::businessNow()`، يعني **منطقة المحل الزمنية (افتراضيًا Africa/Cairo) + وقت قفل اليوم التجاري**، على `created_at` (ختم السيرفر، فتاريخ فاتورة قديم مايطلعهاش من الشهر). بيرجع يعد من الأول يوم 1 (`test_the_count_starts_again_with_the_next_business_month`).
* ملحوظة: لو المحل ضابط وقت قفل اليوم بعد نص الليل، أول ساعات يوم 1 بتتحسب على الشهر اللي فات، وده متسق مع باقي النظام.

## 3. مصفوفة المميزات (`FeatureCatalog`)

`FeatureGateMatrixTest` بيلف على كل routes `api/v1/*` اللي عليها `feature:X`، ولكل ميزة بيعمل محل باقته فيها كل حاجة **إلا** X، ويتأكد إن **كل** route منهم بيرجع 403 `subscription.feature_unavailable` و`details.feature = X` (أكتر من 50 route).

| الميزة | أساسية؟ | نقطة التطبيق | الحالة |
|---|---|---|---|
| `pos.access` | ✅ | `feature:` على routes الكاشير (8) | ✅ |
| `invoices.create` | ✅ | `feature:` على `POST /invoices` | ✅ |
| `items.manage` / `items.movements` | ✅ | `feature:` على كتابة الأصناف / حركة الصنف | ✅ |
| `transfers.manage` | — | `feature:` (4 routes) | ✅ |
| `mixes.manage` | — | `feature:` (4 routes، ومنها `coffee-blender` القديم) | ✅ |
| `purchases.manage` / `purchases.reorder` | ✅ / — | `feature:` | ✅ |
| `expenses.manage` / `payments.manage` / `shifts.manage` / `treasury.view` / `returns.manage` | ✅ | `feature:` | ✅ |
| `reports.basic` | ✅ | `feature:` (11 route) | ✅ |
| `reports.advanced` | — | `feature:` على تقارير الأصناف والفروع + `ProfitVisibility` بيخفي الربح والتكلفة | ✅ |
| `audit.logs` | — | `feature:` على سجل النشاط وتصديره (API والويب) | ✅ |
| `reports.export` | ✅ (مش في المجانية) | **كان: مفيش.** دلوقتي `feature:` على `GET /invoices/export` + إخفاء الزرار في الواجهة | ✅ **اتصلح** |
| `telegram.notifications` | ✅ (مش في المجانية) | **كان: زرار الاختبار بس.** دلوقتي كمان `TelegramService::isShopEnabled()` بيوقف كل تنبيهات المحل | ✅ **اتصلح** |
| `printing.thermal` / `printing.a4` | ✅ | **كان: مفيش.** دلوقتي `feature:` على صفحتي طباعة الفاتورة (`routes/tenant.php`) | ✅ **اتقفل** |
| `items.movements` (صفحة الطباعة) | ✅ | الـ API كان مقفول، بس `/items/{id}/movements/print` لأ. دلوقتي مقفولة | ✅ **اتقفل** |
| `invoices.edit`, `whatsapp.share` | ✅ | **مفيش تطبيق:** مفيش route تعديل فاتورة، وواتساب لينك في المتصفح. مفعلين في كل الباقات، فالأثر الوحيد إن سحبهم بـ override مالوش تأثير | ⚠️ موثق في الاختبار |
| `quotations.manage`, `pos.offline`, `api.access`, `custom.domain` | — | **مفيش تطبيق ولا ميزة متبنية.** الاختبار بيتأكد إنها مش متباعة في أي باقة | ❌ مش متبنية (ومش بتتباع) |

* **صفحات الويب (طباعة/تصدير):** `test_the_tenant_print_pages_carry_their_plan_feature` بيتأكد إن كل صفحة عليها الميزة بتاعتها.
* **مميزات في الواجهة بس:** مفيش. كل ميزة الواجهة بتقفلها (`FeatureGate`, `meta.feature`, `hasFeature`) ليها تطبيق على السيرفر، ما عدا الأربع الأساسية اللي فوق (والواجهة أصلًا مش بتقفلها).
* **الإتاحة والسحب (grant / revoke):** `TenantOverridesEntitlementTest` (السحب بيكسب على الباقة، والإتاحة بتعدي الباقة) و`PlanEnforcementApiTest` (الاسم القديم `blender.access`).

## 4. الباقة المجانية
| البند | الاختبار | الحالة |
|---|---|---|
| الحدود 2 مستخدم / فرع 1 / 0 مخزن / 0 عربية / 100 صنف / 150 فاتورة شهريًا / 50 عميل / 20 مورد / 200MB | `PermanentFreePlanTest::test_the_catalog_free_plan_is_the_permanent_free_plan`, `PartyLimitsApiTest`, `PlanEnforcementApiTest::test_a_free_plan_tenant_gets_the_free_plans_limits` | ✅ |
| من غير `reports.export` و`telegram.notifications` | `PlanEnforcementApiTest` + `FeatureGateMatrixTest::test_the_catalog_free_plan_has_no_report_export` | ✅ (ودلوقتي متطبقين فعلًا) |
| دائمة: من غير انتهاء، والمحل `active` دايمًا | `PermanentFreePlanTest::test_a_free_tenant_never_leaves_active_through_the_lifecycle_sweep`, `FreePlanLifecycleReviewTest` | ✅ |
| من غير فواتير SaaS | `PermanentFreePlanTest::test_a_free_plan_is_never_invoiced` | ✅ |
| التجربة بترجع للمجانية لما تخلص من غير دفع | `PermanentFreePlanTest::test_a_self_signup_trial_that_ends_unpaid_falls_back_to_the_free_plan_as_active` (+ 4 حالات حدية) | ✅ |
| النزول للمجانية بيتجدول لآخر المدة المدفوعة، ومن غير فاتورة، ويتلغي بالدفع | `FreePlanLifecycleReviewTest::test_a_downgrade_to_free_is_scheduled_never_invoiced_and_applied_at_the_end_of_the_term` (+ الإلغاء والدفع) | ✅ |

## 5. الواجهة
| الجزء | المراجعة | الاختبار | الحالة |
|---|---|---|---|
| ويدجت الاستخدام في الداشبورد (`DashboardPlanUsage`) | بيقرا `/billing/subscription` (نفس عدّاد السيرفر)، بيخفي الحد `0` و`null`، والفرع 1 من 1 مش تحذير | `tests/js/planLimits.test.js`, `plan-limit.test.js` + E2E | ✅ |
| تنبيه الترقية العام (403) | الـ interceptor في `api.js` بيفرق بين `limit_reached` و`feature_unavailable` وبين 403 الصلاحيات، وزرار الترقية لصاحب `billing.manage` بس | E2E | ✅ |
| بانر الكاشير + منع البيع | `usePosInvoiceLimit`: بيعيد القراية قبل ما يمنع (فبداية شهر جديد أو ترقية مابتقفلش الكاشير)، والسيرفر هو المرجع، والمرتجعات مش بتعدي على الحارس | E2E | ✅ |
| زرار "تصدير ABC Excel" في تقرير المخزون | بيعمل `emit('export-abc')` ومحدش بيسمعه: **زرار ميت** (مش ثغرة في الباقات) | — | ⚠️ متابعة |

## 6. الثغرات اللي لقيناها واتصلحت
1. **تصدير الفواتير CSV مفتوح للباقة المجانية.** `reports.export` متشالة من المجانية في `PlanCatalog`، بس مفيش أي route عليه `feature:reports.export`، فـ`GET /invoices/export` كان شغال لأي محل. **الإصلاح:** middleware على الـ route + إخفاء زرارين التصدير في صفحة الفواتير. **اختبار الانحدار:** `FeatureGateMatrixTest::test_the_invoice_export_needs_the_report_export_feature` (اتأكدنا إنه بيفشل من غير الإصلاح).
2. **تنبيهات تيليجرام بتتبعت لمحلات باقتها مفيهاش الميزة.** الـ route الوحيد المقفول كان زرار "رسالة تجريبية"؛ الملخص اليومي، وتنبيه الوردية المتأخرة، والمخزون المنخفض، وفرق الوردية، وتنبيهات قفل الحساب/PIN كانوا بيتبعتوا لأي محل ضابط bot token. **الإصلاح:** في نقطة واحدة، `TelegramService::isShopEnabled()` (كل تنبيهات المحل بتعدي عليها عن طريق `forShopOnly()`)، فالأمر `notify:dispatch-tenant-alerts` كمان بيتخطى المحل. خارج المستأجر (المنصة) مفيش تغيير. **اختبارات الانحدار:** `test_a_shop_without_telegram_notifications_gets_no_scheduled_alert` و`test_a_shop_only_alert_is_never_sent_without_the_feature`. وفي الواجهة تبويب تيليجرام في الإعدادات بقى مقفول للباقة دي (`FeatureGate`) بدل ما المحل يضبطه ومحدش يقوله إنه مش هيوصل.
3. **عملاء بلا حد عن طريق العميل النقدي** (لقاها `security-auditor`). `PUT /customers/{walkInId}` باسم ورقم حقيقيين بيخلي الصف يتحسب عميل عادي (`PartyLimitCounter` بيعرف العميل النقدي بالاسم/الرقم)، والبيعة اللي بعدها من غير عميل بتعمل عميل نقدي جديد من غير فحص الحد. يتكرر بلا نهاية. **الإصلاح:** `UpdateCustomerAction` بيرفض (422 `customers.walk_in_identity_locked`) أي تغيير في اسم أو رقم العميل النقدي. **اختبار الانحدار** بيعيد الاستغلال خطوة بخطوة (اتأكدنا إنه بيفشل من غير الإصلاح).
4. **صفحات طباعة من غير قفل ميزة:** طباعة حركة الصنف كانت بتعدي من قفل `items.movements` اللي على الـ API، وطباعة الفاتورة مكانش عليها `printing.*`. اتقفلوا (أثرهم بس على سحب الميزة بـ override، لأنهم في كل الباقات).

## 7. نتايج التشغيل (فعلية)
* PHPUnit (`php -d memory_limit=2G vendor/bin/phpunit tests/Feature/<Dir>`، أكتر من run بالتوازي):
  * Entitlements 192/192 (بعد آخر تعديل)، Console 174/174، Billing 564 + 1 skipped (موجود قبل كده)، Settings 7/7، Export 2/2، Security 20/20، Import 34/34، Auth 3/3، Stores 70/70، Platform 101/101، Central 372/372، Tenancy 217/217، Signup 122/122، Branding 88/88، `InvoicePrintRoutesTest` 51/51، `LangKeyParityTest` 112/112، `CustomersApiTest` 13/13، Telegram 2/2.
  * Unit 687/688: الفشل الوحيد كان رقم موبايل شكله حقيقي في اختبار جديد بتاعنا، اتغير لرقم وهمي و`NoHardcodedRealPhonesTest` بقى 12/12.
  * Api 1639/1640: الفشل الوحيد `ReportsPerformanceRegressionTest` بسبب تعديل غير متكومت في `lang/ar` (`receipt_voucher`: "سند قبض فلوس (تحصيل من زبون)" ← "سند قبض نقدية (تحصيل من عميل)") موجود في الـ working tree من قبل الشغل ده، ومش من التعديلات دي.
* اختبارات الانحدار للإصلاحات الثلاثة اتأكدنا إنها بتفشل لما الإصلاح يتشال مؤقتًا.
* `composer analyse`: 0 أخطاء، من غير ما الـ baseline يكبر.
* Pint على ملفات PHP بتاعتنا بس، وESLint + Prettier على ملفات JS بتاعتنا بس.
* مراجعة: `code-reviewer` (approve with nits، اتطبقوا كلهم) و`security-auditor` (ثغرة العميل النقدي + صفحات الطباعة، اتصلحوا).
* **E2E:** الملف اتكتب (`npm run e2e:plans`) بس **ماتشغلش**: MariaDB المحلية واقفة وتشغيلها محتاج `sudo`. الاختبارات بتعمل mock لحالة الباقة، فمش بتكتب في الداتابيز، بس صفحة الدخول (auth-setup) محتاجة الداتابيز.
* **E2E للكونسول:** مش ممكن محليًا لأن الكونسول بيتخدم على `admin.sroor.test` بس (مش متعرف في `/etc/hosts`). مسار "تغيير الباقة من الكونسول بيوصل للمحل" متغطي end-to-end على مستوى الـ API في `LimitReachedDailyWorkTest`.

## 8. متابعات مقترحة (مش متنفذة)
* لو حد 200MB المفروض يشمل البيانات، محتاج قرار: حساب حجم قاعدة المستأجر (مكلف) أو تعديل نص التسويق.
* زرار "تصدير ABC Excel" الميت: يا إما يتوصل بالسيرفر (ويتقفل بـ `reports.export`) يا إما يتشال. و`ExportController` (كشوف الحساب وتصدير المخزون) مش متوصل بأي route؛ لو اتوصل لازم ياخد `feature:reports.export`.
* `invoices.edit` / `whatsapp.share`: لو مطلوب إن سحبها من محل يأثر، محتاجة نقاط تطبيق.
* العميل النقدي بيتعرف بالاسم/الرقم؛ الأمتن عمود `is_walk_in`. و`StoreSalesInvoiceRequest` لسه بيعمل العميل النقدي في `prepareForValidation()` (قبل `authorize()`) وبأسماء ثابتة، المفروض يستخدم `ResolvesWalkInCustomer`.
