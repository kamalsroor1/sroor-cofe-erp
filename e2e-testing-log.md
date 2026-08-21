# 📸 سجل نظام الفحص البصري والزحف الشامل (E2E Visual Testing & Crawling System Log)

> **⚠️ الغرض من النظام:**
> نظام فحص بصري وتفاعلي شامل مبني باستخدام **Playwright**، يقوم بفتح كل صفحة ومسار في تطبيق "سرور كوفي ERP" (Pure Laravel API + Vue 3 SPA)، وتجربة العناصر التفاعلية والمودالز، والتقاط لقطات شاشة كاملة (Full-Page Screenshots) بدقة فائقة على مقاسين (**Desktop** و **Mobile**)، وحفظها في هيكل زمني منظم ومستثنى من Git للمراجعة البصرية واكتشاف أي أخطاء تصميمية أو ترجمة دون إتلاف أي بيانات.

---

## 1. الهيكل التقني لنظام الـ E2E (`e2e/`)

```text
d:\projects\sroor\
├── e2e/
│   ├── .auth/                        # جلسة تسجيل الدخول المؤقتة وحالة التخزين (مستثناة من Git)
│   │   └── user.json
│   ├── auth/
│   │   └── login.setup.js           # سكربت الإعداد المسبق للمصادقة وتخزين الـ Storage State
│   ├── crawlers/
│   │   └── crawler.spec.js          # محرك الزحف البصري الشامل والتفاعل التلقائي مع الصفحات
│   ├── flows/
│   │   └── login-flow.spec.js       # نماذج تدفقات المستخدم الكاملة (Multi-Step Flows) مع Assertions
│   ├── utils/
│   │   ├── screenshot-helper.js     # حساب مسارات الصور وتثبيت طابع وقت الجلسة YYYY-MM-DD/HH-mm-ss
│   │   ├── interaction-helper.js    # فتح المودالز بأمان، استبعاد الأزرار الخطرة، واكتشاف الروابط
│   │   └── reporter-helper.js       # توليد تقارير _report.json و _report.md وطباعة ملخص الـ Terminal
│   └── pages.config.js              # السجل المركزي لكافة صفحات وموديولات المنصة
├── playwright.config.js             # إعدادات Playwright المركزية (Auth Setup + Desktop + Mobile)
└── e2e-testing-log.md               # السجل والتوثيق المعتمد
```

---

## 2. هيكلة حفظ لقطات الشاشة (Screenshot Directory Hierarchy)

تُحفظ كافة لقطات الشاشة بنظام زمني هرمي دقيق ومستثنى تماماً من Git:

```text
e2e/screenshots/
└── [YYYY-MM-DD]/                      ← مجلد تاريخ اليوم (مثال: 2026-08-21)
    └── [HH-mm-ss]/                   ← مجلد وقت بدء التشغيل (مثال: 20-05-00)
        ├── _report.json              ← تقرير فني شامل بصيغة JSON
        ├── _report.md                ← تقرير تنفيذي بصيغة Markdown
        ├── flows/                    ← تدفقات المستخدم المخصصة
        │   └── login-and-pos-flow/
        │       ├── 01-login-screen-initial.png
        │       ├── 02-credentials-filled.png
        │       └── 03-dashboard-landing-success.png
        └── [module-name]/            ← مجلد الموديول (pos, items, invoices, customers, ...)
            ├── desktop/              ← مقاس سطح المكتب (1920x1080)
            │   ├── 01-[page-name].png
            │   └── 01-[page-name]-modal-open-1.png
            └── mobile/               ← مقاس الهاتف الذكي (390x844)
                ├── 01-[page-name].png
                └── 01-[page-name]-modal-open-1.png
```

---

## 3. مصفوفة الصفحات والموديولات المسجلة في `pages.config.js`

| # | الموديول (`module`) | المسار (`route`) | اسم الصفحة | نوع المصادقة |
|---|---|---|---|---|
| 01 | `auth` | `/login` | تسجيل الدخول | عام (Guest) |
| 02 | `dashboard` | `/` | لوحة التحكم والتحليلات اللحظية | محمي (Auth) |
| 03 | `pos` | `/pos` | نقطة البيع السريعة والكاشير (POS) | محمي (Auth) |
| 04 | `invoices` | `/invoices` | فواتير المبيعات | محمي (Auth) |
| 05 | `items` | `/items` | الأصناف والمخزون | محمي (Auth) |
| 06 | `items` | `/items/1/movements` | كارت صنف وحركات المخزون | محمي (Auth) |
| 07 | `stores` | `/stores` | إدارة الفروع والمخازن | محمي (Auth) |
| 08 | `stores` | `/stores/stocks` | أرصدة الفروع والمخازن | محمي (Auth) |
| 09 | `customers` | `/customers` | العملاء وكشوف الحساب | محمي (Auth) |
| 10 | `customers` | `/customers/1/statement` | كشف حساب عميل تفصيلي | محمي (Auth) |
| 11 | `suppliers` | `/suppliers` | الموردين وحسابات التوريد | محمي (Auth) |
| 12 | `suppliers` | `/suppliers/1/statement` | كشف حساب مورد تفصيلي | محمي (Auth) |
| 13 | `purchases` | `/purchases` | فواتير المشتريات والتوريد | محمي (Auth) |
| 14 | `purchases` | `/purchases/create` | فاتورة مشتريات جديدة | محمي (Auth) |
| 15 | `purchases` | `/purchases/smart-reorder` | رادار إعادة الطلب الذكي | محمي (Auth) |
| 16 | `expenses` | `/expenses` | المصروفات والعهد النثرية | محمي (Auth) |
| 17 | `returns` | `/returns` | مرتجعات المبيعات والمشتريات | محمي (Auth) |
| 18 | `returns` | `/returns/create` | تسجيل مرتجع جديد | محمي (Auth) |
| 19 | `transfers` | `/stock-transfers` | التحويلات المخزنية بين الفروع | محمي (Auth) |
| 20 | `transfers` | `/stock-transfers/create` | إذن تحويل مخزني جديد | محمي (Auth) |
| 21 | `blender` | `/coffee-blender` | محرك وخلاط توليفات البن | محمي (Auth) |
| 22 | `shifts` | `/daily-journal` | دفتر اليومية وورديات الخزينة | محمي (Auth) |
| 23 | `reports` | `/reports` | التقارير المالية والأرباح والخسائر | محمي (Auth) |
| 24 | `users` | `/users` | إدارة المستخدمين والموظفين | محمي (Auth) |
| 25 | `users` | `/roles` | مصفوفة الصلاحيات والأدوار | محمي (Auth) |
| 26 | `users` | `/activity-logs` | سجل التدقيق الأمني والنشاطات | محمي (Auth) |
| 27 | `settings` | `/settings` | إعدادات النظام والمؤسسة والطباعة | محمي (Auth) |
| 28 | `settings` | `/profile` | الملف الشخصي وتغيير كلمة المرور | محمي (Auth) |
| 29 | `settings` | `/trash` | سلة المحذوفات واستعادة السجلات | محمي (Auth) |
| 30 | `super-admin` | `/super-admin/dashboard` | لوحة تحكم السوبر أدمن والـ MRR | محمي (Auth) |
| 31 | `super-admin` | `/super-admin/tenants` | إدارة المستأجرين والتهيئة | محمي (Auth) |
| 32 | `super-admin` | `/super-admin/plans` | باقات الاشتراك وتعديل الأسعار | محمي (Auth) |
| 33 | `marketing` | `/brochure` | بروشور المنصة والأسعار التفاعلي | عام (Public) |

---

## 4. طرق التشغيل (Execution Scripts)

تم تسجيل أوامر التشغيل الاحترافية في `package.json`:

```bash
# 1. الزحف والتصوير الشامل لكافة صفحات النظام (Desktop + Mobile)
npm run e2e:all

# 2. الزحف على مقاس شاشة سطح المكتب فقط (1920x1080)
npm run e2e:desktop

# 3. الزحف على مقاس شاشة الهاتف المحمول فقط (390x844)
npm run e2e:mobile

# 4. الزحف على موديول محدد فقط (مثل موديول الـ POS أو المشتريات)
npm run e2e:module -- pos
npm run e2e:module -- purchases
npm run e2e:module -- items

# 5. الزحف والتصوير لصفحة واحدة محددة بالمسار
npm run e2e:page -- /pos
npm run e2e:page -- /daily-journal

# 6. تشغيل سيناريوهات وتدفقات المستخدم الكاملة (Flows)
npm run e2e:flow
```

---

## 5. قواعد الأمان واستبعاد الإجراءات الخطرة (Safety & Security Rules)

1. **حماية البيانات الحقيقية:**
   * محرك الزحف يحتوي على فلتر أمان صارم في `interaction-helper.js` يستبعد تلقائياً الضغط على أي زر يحتوي على كلمات حساسة مثل: (`حذف`, `مسح`, `delete`, `destroy`, `force_delete`, `إلغاء الفاتورة`, `إغلاق الوردية`, `تصفير`, `خروج`, `logout`).
   * يتم تسجيل الأزرار المستبعدة في مصفوفة `skippedSensitiveButtons` وتضمينها في التقرير.
2. **عزل البيانات وسرية الاعتمادات:**
   * بيانات الدخول تُقرأ من متغيرات البيئة `process.env.E2E_USER_PHONE` و `process.env.E2E_USER_PASSWORD` دون كتابة أي كلمات مرور حقيقية داخل الكود.
   * استثناء كامل لمجلدات الصور والـ Auth من تتبع Git داخل `.gitignore`.

---

## 6. نموذج ملخص الـ Terminal عند انتهاء الفحص

عند انتهاء كل عملية تشغيل، يطبع المحرك ملخصاً فورياً ومنظماً:

```text
======================================================
✅ تم تصوير 33 صفحة بنجاح
⚠️ 0 صفحات فشلت (تفاصيلها في _report.json)
🔍 0 صفحات جديدة مكتشفة مش مسجلة في pages.config.js
📁 الصور محفوظة في: d:\projects\sroor\e2e\screenshots\2026-08-21\20-05-00\
======================================================
```

---
* **حالة النظام:** ✅ مبني بالكامل وجاهز للتشغيل والتقاط الشاشات.
