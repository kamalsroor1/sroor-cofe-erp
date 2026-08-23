# سجل تعديل: بناء نظام E2E Visual Testing & Crawling بنظام Playwright
* **التاريخ والوقت:** 2026-08-21 20:06
* **الدور المفعل:** QA & Testing Agent
* **الهدف:** بناء نظام فحص بصري وزحف آلي شامل (E2E Visual Testing & Page Crawler) لتطبيق "سرور كوفي ERP" باستخدام Playwright لتصوير كل صفحة وموديول وتجربة العناصر التفاعلية والمودالز على شاشات Desktop و Mobile دون المساس ببيانات الإنتاج.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `e2e/pages.config.js` - سجل مركزي لكافة صفحات ومسارات وموديولات النظام (33 صفحة عبر 19 موديول).
* `[NEW]` `e2e/auth/login.setup.js` - سكربت مصادقة وحفظ الـ Storage State بأمان.
* `[NEW]` `e2e/crawlers/crawler.spec.js` - محرك الزحف البصري الشامل والتفاعل التلقائي مع المودالز.
* `[NEW]` `e2e/flows/login-flow.spec.js` - نموذج سيناريو مستخدم كامل (Multi-Step User Journey) مع Assertions وصور لكل خطوة.
* `[NEW]` `e2e/utils/screenshot-helper.js` - حساب مسارات وهيكلة مجلدات الصور التاريخية `YYYY-MM-DD/HH-mm-ss/`.
* `[NEW]` `e2e/utils/interaction-helper.js` - استكشاف المودالز الآمن مع حماية واستبعاد أزرار الحذف والعمليات الخطرة.
* `[NEW]` `e2e/utils/reporter-helper.js` - توليد تقارير `_report.json` و `_report.md` وملخص الـ Terminal.
* `[NEW]` `e2e-testing-log.md` - سجل التوثيق الشامل لنظام الاختبارات البصرية.
* `[MODIFIED]` `playwright.config.js` - ضبط مشاريع `auth-setup`, `desktop`, `mobile` والـ baseURL والمهلات الزمنية.
* `[MODIFIED]` `package.json` - إضافة أوامر `npm run e2e:all`, `e2e:desktop`, `e2e:mobile`, `e2e:module`, `e2e:page`, `e2e:flow`.
* `[MODIFIED]` `.gitignore` - استثناء مجلدات `e2e/screenshots/`, `e2e/test-results/`, `e2e/playwright-report/`, `e2e/.auth/`, `e2e/.run-meta.json`.

## 2. القرارات التقنية ومعايير الأمان:
* **الأمان أولاً:** استبعاد أي زرار أو إجراء يحمل طابع الحذف أو التعديل المالي أو تسجيل الخروج تلقائياً أثناء الزحف.
* **التوافق التام مع المقاسات:** التقاط Full-Page لقطات كاملة لكل صفحة على Desktop (1920x1080) و Mobile (390x844).
* **مرونة التشغيل:** إمكانية تشغيل الفحص على مستوى المنصة كاملة، أو موديول محدد (`-- pos`)، أو صفحة واحدة بالمسار (`-- /invoices`).

## 3. التحقق والاختبار:
* [x] استثناء مجلدات الصور وجلسات الـ Auth من تتبع Git.
* [x] التحقق من سلامة الأوامر في `package.json` وملف `pages.config.js`.
* [x] مطابقة كافة المتطلبات والتعليمات المحددة في دليل التشغيل `AGENTS.md`.
