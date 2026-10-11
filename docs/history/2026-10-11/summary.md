# ملخص: Phase 1 — W2 الدفعات 1–6 (2026-10-09 → 2026-10-11)

> **البرنش:** `feature/multi-tenant`، و`HEAD` = `0f55e048` = `origin/feature/multi-tenant` وقت الكتابة. مفيش deploy ولا لمس للـ production.
> السجلات دي اتكتبت يوم 2026-10-11 من `git log` ونتائج GitHub Actions وتعليقات الـ PRs. اللي مالوش دليل مكتوب "غير موثّق".

## 1. السجلات
| # | السجل | البنود الأساسية |
|---|---|---|
| 01 | [W2 الدفعة 1](01-w2-batch-1-central-auth-lifecycle-catalog.md) | IDEN-1.2/1.3، IDEN-3.2/3.3، ENTI-2.1، ENTI-1.8/1.9، POSB-6، QA-4 (جزء A) |
| 02 | [W2 الدفعة 2](02-w2-batch-2-2fa-entitlements-permissions-rounding.md) | OPS-3، IDEN-1.12/1.6، ENTI-2.2، ENTI-1.10، QA-2، STOR-1، SETG-2 امتداد، SETG-13 |
| 03 | [W2 الدفعة 3](03-w2-batch-3-console-cutover-backups.md) | IDEN-1.4/1.8/1.9/1.11، OPS-5، (IDEN-1.7 مبكرًا) |
| 04 | [W2 الدفعة 4](04-w2-batch-4-provisioning-branding-formatters.md) | OPS-2، BRND-2/3/5، SETG-6، SETG-10 |
| 05 | [W2 الدفعة 5](05-w2-batch-5-archive-purge-store-scoping-app-updates.md) | OPS-9، STOR-2 (مبكرًا من W3)، APP-3 امتداد، APP-7، APP-5 امتداد، OFFL-2، دمج PR #4 و#7 |
| 06 | [BUG-01: كاش الوردية المكرر](06-bug-01-shift-cash-double-count.md) | `DailyCashFlowService`، `payments.store_id` |
| 07 | [التعاون مع Antigravity](07-antigravity-collaboration-setup.md) | issues + labels `antigravity:*` + Automation + حلقة المراجعة |
| 08 | [W2 الدفعة 6: SEC-1 وi18n وقفل W2](08-w2-batch-6-sec1-i18n-closeout.md) | SEC-1 (M1، M3–M7، L1–L8، L11–L13)، تنضيف i18n (~150 نص)، صفحة إعدادات المنصة (BRND-2 واجهة)، شيل branding «سرور كوفي» من الـ splash والـ manifest |

## 2. CI لكل دفعة (GitHub Actions، push على `feature/multi-tenant`)
| الدفعة | commit | PHPUnit (sqlite) | JS unit | mysql | gitleaks | الحالة |
|---|---|---|---|---|---|---|
| 1 | `450cd1f1` | 1612 passed، 1 skipped | 78/78 | فشل (3) | فشل (ثابت test) | **أحمر** |
| 2 | `9d486424` | 1981 passed، 1 skipped | 137/137 | فشل (2، rollback migrations) | أخضر | **أحمر** |
| 3 | `dd791ef9` ← `cbd6589e` | 2339 passed، 1 skipped (= 2340) | 150/150 | أخضر | أخضر بعد الإصلاح | **أخضر** بعد `cbd6589e` |
| 4 | `edde1ca6` ← `0c9dbac8` | 2540 passed، 1 skipped (= 2541) | 172/172 | أخضر | أخضر بعد الإصلاح | **أخضر** بعد `0c9dbac8` |
| 5 (+ PR #4، #7) | `0f55e048` | 2831 passed، 1 skipped (= 2832) | 193/193 | أخضر | أخضر | **أخضر** |
| 6 (SEC-1 + i18n + إعدادات المنصة) | سيُحدَّث | قيد التشغيل | قيد التشغيل | قيد التشغيل | قيد التشغيل | **قيد التشغيل** |

* job الـ `php` = Pint + Larastan (level 5 + baseline) + PHPUnit. job الـ `frontend` = ESLint + Prettier + JS tests + build. job الـ `desktop` = ESLint + Prettier.
* فشل `mysql` في الدفعتين 1 و2 (rollback الـ migrations المرتبطة بـ `billing_invoices`) اتقفل بتحديث سلاسل الـ rollback في اختبارات الـ migrations ضمن commit الدفعة 3؛ وفشل `gitleaks` في الدفعة 1 اتقفل بتغيير ثوابت الاختبار (`fixture*`) ضمن الدفعة 2.
* كل فشل `gitleaks` في W2 كان ثوابت داخل tests/E2E اتمسكت بقاعدة `sroor-hardcoded-password`، مش secrets حقيقية.
* مراجعات `code-reviewer` و`security-auditor` اتعملت على كل دفعة من 3 لـ 5 قبل الـ commit، والملاحظات اتصلّحت في lanes متابعة (3G–3L، 4G، 5F، 5G).

## 3. ما لم يُتحقق منه
* تشغيل Playwright E2E على الدفعات: غير موثّق (spec الأوفلاين اتكتب ومحمّل، لكن ما اتشغلش لعدم وجود بيئة لوكل جاهزة وقتها).
* deploy حقيقي، Google Drive حقيقي، restore drill، `tenants:purge` على DB حقيقية، تحديث فعلي على Android/Windows، درج كاش على طابعة: لم يُنفَّذ.
* بوابة SEC-1 (نهاية W2): المراجعة والإصلاحات في الدفعة 6 ([08](08-w2-batch-6-sec1-i18n-closeout.md))؛ نتيجة الـ suite الكامل وCI لسه هتتسجل. المفتوح: C1 (تدوير الأسرار، بيد الـ CTO)، M2 = IDEN-3.5، M8 = IDEN-2.4 + CSP (W3).

## 4. محتاج قرار / متابعة من الـ CTO
1. **الورديات المقفولة قبل BUG-01:** `expected_cash_balance` / `cash_difference` المخزنة أعلى من الحقيقة ولم يُعد كتابتها. أي تصحيح تاريخي محتاج موافقتك وbackup.
2. قرار `refund_amount` في المرتجع (من [ملخص 2026-10-09](../2026-10-09/summary.md) §6) لسه مفتوح.
3. STOR-2 وبوابات Horizon/Telescope (IDEN-1.7) اتنفذوا قبل W3 — اتسجلوا في [`phase-1-plan.md`](../../05-planning/phase-1-plan.md) §8.1 (حالة W2) مع باقي البنود.
4. PR #9 (E2E + docs) لسه draft بتغييرات مطلوبة؛ issues #10، #11، #12 في طابور Antigravity.
5. إعداد Google Drive للـ backups وأول restore drill (بإيدك، `backup-restore.md` §2 و§6).
6. قبل أول deploy بالـ migration `500400`: استعلام قراءة للتأكد من عدم تكرار `(platform, version_code)` في `app_versions`، وbackup لجدول `tenants`.
