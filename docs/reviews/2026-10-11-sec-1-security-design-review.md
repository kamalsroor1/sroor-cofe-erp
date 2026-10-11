# SEC-1: مراجعة التصميم الأمني في نهاية W2 (Phase 1)

* **التاريخ:** 2026-10-11
* **النطاق:** `feature/multi-tenant` عند `0f55e048` (بعد الدفعات 1–5 ودمج PR #4 و#7)، قراءة فقط من الكود، من غير أي طلب على سيرفر حي.
* **المراجع:** `security-auditor`، بالمقارنة مع صف SEC-1 في `docs/05-planning/phase-1-plan.md` و`.claude/rules/security-and-operations.md` و`multi-tenancy.md`.
* **القيم السرية** مشار ليها بـ "(redacted)" بس، ومفيش أي قيمة مكتوبة هنا.

## 1. الملخص

| الخطورة | العدد | الحالة بعد الدفعة 6 |
|---|---|---|
| Critical | 1 | مفتوحة — قرار الـ CTO (تدوير الأسرار) |
| Medium | 8 | 6 بتتقفل في lane 6A؛ M2 = IDEN-3.5 (W3)؛ M8 = IDEN-2.4 + CSP (W3) |
| Low | 15 | معظمها في lane 6A؛ الباقي متسجل كمتابعة |

جانب المنصة واللوحة المركزية سليم: عزل الـ control plane، الـ 2FA والـ step-up، الرفع (`ImageSanitizer`، الـ APK/EXE)، الباك أب والأرشفة والحذف النهائي، تحديثات Android وDesktop. المخاطر الباقية جوه المحل (تسريب بين الفروع، فجوات صلاحيات) وفي API المحل اللي كان بيرد من غير محل محدد.

## 2. حالة البوابة (معيار القبول: صفر blockers مفتوحة)

* **مسار IDEN-3:** محتاج M1 وM7 يتقفلوا (lane 6A)، وM2 هو IDEN-3.5 نفسه (أول W3).
* **مسار OPS:** الـ Critical (C1) بيمنع أي cut-over على production لحد ما الأسرار تتدوّر.
* **ENTI-2/3، BRND، POSB-4/5/8، OPS-10:** مفيش كود لسه؛ شروط التصميم في §5 لازم تتطبق.

## 3. النتائج

### Critical
* **C1 — أسرار production في ملفات متتبعة في ريبو عام** (سكريبتات `*.py` في جذر الريبو، و`.github/workflows/deploy.yml`؛ القائمة الكاملة في `docs/07-operations/secrets.md`). التصليح: تدوير SSH وDB و`APP_KEY` على سيرفر Hostinger، SSH بالمفاتيح بس، إلغاء `deploy.yml` وتقاعد السكريبتات (OPS-8)، وعدم استخدام أي قيمة منهم على الـ VPS. **قديمة ومعروفة؛ التدوير مؤجل بقرار الـ CTO.**

### Medium
| # | المشكلة | التصليح | الحالة |
|---|---|---|---|
| M1 | API المحل بيشتغل من غير محل محدد على الدومين الرئيسي (بيقرا/يكتب في قاعدة المنصة) | 404 لكل routes المحل لما الـ tenancy مش initialised؛ تنضيف `personal_access_tokens` المركزية | lane 6A |
| M2 | المحل الموقوف/الملغي/المؤرشف لسه بيقدر يدخل ويكتب | IDEN-3.5 | W3 |
| M3 | حركة الصنف وتفاصيل مخزونه بتكشف الفروع التانية | `store.active` + `readFilter` + فلترة `storeStocks` | lane 6A |
| M4 | سند التحصيل/الصرف ممكن يسدد مستند فرع تاني أو عميل/مورد تاني | تطابق العميل/المورد + `ActiveStore::canRead` جوه الـ transaction | lane 6A |
| M5 | الكاشير يقدر يصرف لمورد من الدرج | `suppliers.manage` لسند الصرف | lane 6A |
| M6 | `items.view_cost` مش متطبقة؛ التكلفة والأرباح ظاهرة لأي حد | إخفاء التكلفة والأرباح من غير الصلاحية + صلاحيات على `/stores/stocks` و`/dashboard` | lane 6A |
| M7 | routes الـ web القديمة (session login + كتابة) سطح auth تاني من غير صيانة | شيلها وسيب صفحات الطباعة بس | lane 6A |
| M8 | توكنات المحل مالهاش انتهاء، ومفيش CSP على دومينات المحلات | IDEN-2.4 (Q-B9) + CSP للمحلات | W3 |

### Low
L1 عرض أي فرع من غير صلاحية · L2 `id === 1` كأدمن عام · L3 فاتورة الخلّاط من غير `store.active:concrete` · L4 رسايل الأخطاء الخام للعميل · L5 رسالة spatie في 403 · L6 Telescope مش مخبّي حقول حساسة · L7 معادلات في CSV · L8 route الـ impersonation القديم · L11 إنشاء محل من غير step-up · L12 صلاحية الأدوار تقدر تدّي نفسها أدمن · L13 إنشاء العميل النقدي قبل الـ authorization — **كلهم في lane 6A.**
L9 `cleartext` في Capacitor · L10 `update_webhook.php` (يتشال بعد خط الـ VPS، قرار الـ CTO) · L14 تثبيت نسخ `sanctum`/`telescope` ونقل `@capacitor/cli` لـ dev · L15 مهام Telegram المجدولة في السياق المركزي (OPS-10) — **متابعة.**

قرار مطلوب (مش ثغرة): كشف حساب العميل/المورد بيعرض مستندات كل الفروع للكاشير اللي معاه `customers.statement`. صح لو الرصيد على مستوى المحل كله (A6).

## 4. اللي اتراجع وطلع سليم
عزل الـ admin host (`EnsureCentralContext`)؛ Telescope/Pulse/Horizon ورا الـ central guard؛ توكنات المشغّلين بانتهاء وabilities؛ step-up قبل الـ throttle؛ توكنات المحل في قاعدة المحل؛ quick-login مقفول في production؛ rate limits على كل route عام؛ `X-Store-Id` متحقق منه في `ApiTokenAuth` و`ResolveActiveStore`؛ الرفع (magic bytes، إعادة ترميز، أسماء من السيرفر، disks خاصة)؛ مفيش `$guarded = []` ولا `$request->all()` ولا SQL خام من مدخلات؛ كل الموديلات المركزية مثبتة على الاتصال المركزي؛ مفاتيح الكاش مقسومة بالمحل؛ باسورد قاعدة المحل مشفر؛ الحذف النهائي CLI بس ومن غير `--force`؛ Electron (`contextIsolation`، `sandbox`، IPC من مصدر موثوق)؛ تحديث Android (https + sha256 + شهادة التوقيع).

## 5. شروط تصميم لمهام W3 اللي مالهاش كود لسه
* **IDEN-3.5:** في `ResolveApiTenancy` أو middleware بيغطي مسارات الدخول كلها؛ بعد قفل M1 وM7.
* **ENTI-3.3 (إيصالات الدفع):** `central_private` و`CentralMedia` بس؛ الـ public disk بتاع المحل مقروء من `/tenancy/assets/*`.
* **IDEN-2.6/2.9 (impersonation):** شيل route الـ stancl؛ `IMPERSONATION_ENABLED=false` لحد الـ guard؛ التوكن في الـ URL fragment بس؛ منع الصلاحيات المدمّرة في `Gate::before`؛ تسجيل الجلسة في `CentralAuditLog`.
* **POSB-4 (PIN المدير):** موافقة مربوطة بالتوكن والفرع والصلاحية والسياق، TTL 120 ثانية، استهلاك ذرّي، قفل بعد 5 محاولات، ممنوع وقت الـ impersonation، والـ PIN مش وسيلة دخول.
* **POSB-5 (الفواتير المعلّقة):** قراءة مقسومة بالفرع (404 لفرع تاني)، استئناف ذرّي، payload بقائمة مسموحة.
* **POSB-8 (حركات الخزينة):** بعد M5؛ `lockForUpdate` على الوردية؛ `store_id` من `ActiveStore` بس.
* **OPS-10 (webhook تيليجرام):** `X-Telegram-Bot-Api-Secret-Token` بـ `hash_equals`، مفيش توكن في الـ URL، throttle، أكواد ربط لمرة واحدة بعمر قصير.
* **SETG-9:** `settings.controls.manage` ماتتمنحش من `roles.manage` إلا لو الممنوح ليه عنده الصلاحية (مرتبط بـ L12).

## 6. ما لم يُغطَّ
`composer audit` (مش متاح في الـ shell؛ يتشغّل في CI)، أي اختبار حي، فحص تاريخ git للأسرار (مسؤولية gitleaks)، إعدادات nginx/VPS وقيم `.env` الحقيقية.
