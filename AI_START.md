# 🚀 دليل البدء السريع (AI & Developer Quick Start)

> **مشروع: سرور كوفي ERP** — نظام ERP + POS متعدد المستأجرين والفروع.
> Laravel 13 + stancl/tenancy · Pure Vue 3 SPA · Capacitor (Android) · Electron (Desktop)
>
> هذا الملف للتشغيل السريع فقط. القواعد الكاملة: [`CLAUDE.md`](CLAUDE.md) ← [`.claude/rules/`](.claude/rules/) ← [`AGENTS.md`](AGENTS.md).

---

## 1. هيكل المشروع

```text
sroor/
├── backend/                     # تطبيق Laravel بالكامل (API + SPA) — معظم العمل هنا
│   ├── app/
│   │   ├── Actions/<Domain>/    # عملية واحدة لكل كلاس — execute()
│   │   ├── DTOs/<Domain>/       # كائنات نقل بيانات readonly
│   │   ├── Http/Controllers/Api # متحكمات نحيفة — /api/v1/*
│   │   ├── Http/Requests        # كل التحقق Validation
│   │   ├── Http/Resources       # تشكيل الاستجابات
│   │   ├── Http/Middleware      # ResolveApiTenancy, StoreScope, StoreAccess
│   │   ├── Filters/<Domain>/    # فلاتر Pipeline
│   │   ├── Services/            # المحركات المشتركة (Stock, Invoice, Treasury, Profit…)
│   │   └── Models/ Policies/ Observers/ Jobs/ Console/
│   ├── database/migrations/         # قاعدة البيانات المركزية (tenants, plans…)
│   ├── database/migrations/tenant/  # قاعدة بيانات المستأجر (items, invoices, stock…)
│   ├── lang/{ar,en}/*.php       # المصدر الوحيد للترجمة
│   ├── resources/js/            # views · Components · Composables · stores · Services · Layouts · router
│   ├── routes/                  # api.php · tenant.php · web.php
│   ├── tests/Feature/Api/       # اختبار لكل Controller
│   ├── android/ + capacitor.config.json   # غلاف الأندرويد
│   └── build-apk.bat
├── desktop/                     # غلاف Electron
├── e2e/ + playwright.config.js  # اختبارات Playwright (desktop / tablet / mobile)
├── docs/                        # التوثيق + docs/history/YYYY-MM-DD/
├── .claude/rules · .claude/agents   # قواعد ووكلاء الذكاء الاصطناعي
├── CLAUDE.md · AGENTS.md · AI_START.md
└── *.py (جذر المشروع)           # ⚠️ اسكريبتات نشر/سيرفر حي — لا تُشغَّل إلا بطلب صريح
```

---

## 2. أوامر التشغيل (من داخل `backend/`)

```bash
composer dev                                  # السيرفر + queue + logs + vite معاً
php artisan serve --host=0.0.0.0 --port=8000  # السيرفر فقط
npm run dev                                   # Vite فقط
npm run build                                 # lang:export ثم بناء الإنتاج
```

### الاختبارات
```bash
php artisan test                              # كل اختبارات PHPUnit
php artisan test --filter=CustomersApiTest    # اختبار محدد
npm run e2e:desktop                           # Playwright (أيضاً e2e:mobile / e2e:tablet / e2e:flow)
./vendor/bin/pint --dirty                     # تنسيق ملفات PHP المعدلة
```

### الترجمة
```bash
php artisan lang:export                       # توليد ترجمات الواجهة من lang/*.php
```

### الموبايل والديسكتوب
```bash
npm run cap:build          # vite build + cap sync android
npm run cap:open           # فتح Android Studio
build-apk.bat              # بناء APK
cd ../desktop && npm start # تشغيل غلاف Electron
```

---

## 3. الهوية البصرية

* **الخطوط:** `Cairo` و `Tajawal` — **الاتجاه:** RTL أولاً مع دعم كامل لـ LTR (English).
* **اللون الأساسي ديناميكي** لكل مؤسسة عبر متغيرات CSS: `var(--color-primary)`, `var(--color-primary-light)`, `var(--color-primary-border)` — ممنوع تثبيت لون العلامة بقيمة Hex داخل المكونات.
* **الافتراضي:** زمردي `#10b981` / `#059669` · ذهبي `#f59e0b` / `#d97706`
* **الوضع الداكن:** `#020617` / `#0f172a` / `#1e293b` · **الوضع الفاتح:** `#f8fafc` / `#ffffff` / `#e2e8f0`
* الأيقونات: `lucide-vue-next` فقط.

---

## 4. قبل أن تكتب أي كود

1. اقرأ [`CLAUDE.md`](CLAUDE.md) (القواعد الذهبية العشر + جدول الوكلاء).
2. اقرأ ملف القاعدة المناسب في [`.claude/rules/`](.claude/rules/).
3. اقرأ الكود الموجود للميزة التي ستعدلها وطابق أسلوبه — مع الانتباه أن بعض الكود القديم مخالف للقواعد ولا يُقلَّد.
4. بعد الانتهاء: شغّل الاختبارات/البناء فعلياً، ثم وثّق في `docs/history/YYYY-MM-DD/` إن كان العمل على مستوى ميزة.
