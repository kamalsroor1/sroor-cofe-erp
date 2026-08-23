# 📱 الدليل الشامل لمعمارية ومكونات NativePHP Mobile v4
## (NativePHP Mobile v4 Developer & Architecture Playbook)

> **توثيق رسمي وتطبيقي شامل لمشروع "سرور كوفي ERP Mobile"**
> مرجع تقني يغطي معمارية **SuperNative**، مكونات **EDGE Components**، واجهات الموبايل، والتعامل مع أجهزة Android و iOS.

---

## 1. المعمارية العامة (SuperNative Architecture)

تعتمد معمارية **NativePHP Mobile v4** على تقنية **SuperNative** التي تدمج محرك **PHP 8.3 Embedded** مباشرة داخل تطبيق النظام (Android / iOS) مع توفير طبقة تواصل فائقة السرعة مع واجهات المستخدم (Vue 3 / Inertia.js أو Blade).

```text
┌─────────────────────────────────────────────────────────────┐
│                 Native Host App (Android / iOS)             │
├──────────────────────────────┬──────────────────────────────┤
│    Embedded PHP 8.3 Engine   │  Native UI & Bridge Modules  │
│  - Laravel Micro-Kernel      │  - Dialogs, Toasts, Haptics  │
│  - Local SQLite / Offline DB │  - Safe Area Insets          │
│  - ApiService (Cloud Sync)   │  - Hardware Back & Gestures  │
├──────────────────────────────┴──────────────────────────────┤
│      Single Page Application UI Layer (Inertia + Vue 3)     │
│  - Cairo / Tajawal Fonts, Strict RTL Support               │
│  - Emerald (#10b981) & Amber (#f59e0b) Brand System          │
│  - Tailwind CSS v4 with Light & Dark Class Switching        │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. الأساسيات والواجهات الأصلية (The Basics & Native UI)

### 2.1 شاشات البداية (Splash Screens)
* **السلوك المعتمد:** تظهر شاشة البداية ذات العلامة التجارية المتحركة (`SplashScreen.vue`) **فقط عند أول فتح للتطبيق (Cold Launch)** وتستمر لمدة 3 ثوانٍ لفحص الاتصال وتهيئة بيئة النظام.
* **التنقل الداخلي:** عند التنقل بين الصفحات لا تظهر الاسبلاش، بل يظهر مؤشر تحميل مدمج وخفيف (`Page Loading Spinner Badge`) أعلى الشاشة يختفي فوريًا فور اكتمال جلب البيانات.

### 2.2 الحماية والمسارات (Routing & Safe Auth)
* مسارات الضيف: `/login` فقط.
* كافة المسارات الداخلية (`/`, `/customers`, `/suppliers`, `/statements`) محمية عبر الوسيط [`MobileApiAuth.php`](file:///i:/projects/erp-2026/mobile/app/Http/Middleware/MobileApiAuth.php) مع إعادة التوجيه الفوري لصفحة الدخول لمنع أي وصول غير مصرح به.

### 2.3 المناطق الآمنة والمحاذاة (Safe Area & Positioning)
* دعم النوتش (Notch) وحافة أزرار التنقل السفلية (Home Gesture Bar) عبر استخدام متغيرات الـ CSS:
  * `safe-pt`: `padding-top: max(0.75rem, env(safe-area-inset-top))`
  * `safe-pb`: `padding-bottom: max(1.25rem, env(safe-area-inset-bottom))`
  * `safe-mb`: `margin-bottom: max(1rem, env(safe-area-inset-bottom))`

### 2.4 الحوارات والتنبيهات (Dialogs & Feedback)
* استخدام مربعات حوار عصرية (Modals) متوافقة مع شاشات اللمس.
* تأثيرات تفاعلية فورية للأزرار (`touch-active` مع `scale(0.97)`).

---

## 3. معايير المدخلات والتاريخ (Inputs & Date Inputs Standard)

### معايير حقول الإدخال المتوافقة مع أندرويد و iOS:
1. **الارتفاع ولمس الشاشة (Touch Targets):** الحد الأدنى لارتفاع الحقل `40px` إلى `44px` (`h-10` أو `h-11`) لسهولة اللمس بالأصابع.
2. **حقول التاريخ (Date Inputs):**
   * استخدام مظهر نظام التشغيل الأصلي مع تنسيق الحقل ليتماشى مع الوضع الفاتح والداكن.
   * توفير رقاقات اختيار سريعة (Quick Chips) لأكثر الفترات طلباً:
     * 🟢 **اليوم** (Today)
     * 🟢 **آخر 7 أيام** (Last 7 Days)
     * 🟢 **هذا الشهر** (Current Month)
     * ⚪ **الكل** (All Time)
3. **التصميم اللوني في الوضعين (Dark / Light):**
   * **الوضع الفاتح (Light Mode):** خلفيات ناصعة (`bg-white` / `bg-slate-50`)، نصوص عالية التباين (`text-slate-900` / `text-slate-700`)، وحدود رمادية ناعمة (`border-slate-200` / `border-slate-300`).
   * **الوضع الداكن (Dark Mode):** خلفيات داكنة فخمة (`bg-slate-900` / `bg-slate-950`)، نصوص بيضاء وزمرية، وحدود داكنة (`border-slate-800`).

---

## 4. قائمة مكونات EDGE Components

| المكون | الوصف | الاستخدام في سرور كوفي ERP |
| :--- | :--- | :--- |
| **Top Bar** | شريط علوي ذكي ثابت مع أزرار الإجراءات | الهيدر العلوي بشعار التطبيق والمستخدم وزر الثيم |
| **Bottom Nav** | شريط تنقل سفلي ثابت 4 أيقونات | التنقل بين (الرئيسية، العملاء، الموردين، الاتصال) |
| **Splash Screen** | شاشة بدء تشغيل متوهجة | شاشة الترحيب وفحص الاتصال (3 ثوانٍ) |
| **Loading Spinner** | مؤشر تحميل عائم | شريط التنقل اللحظي بين الصفحات |
| **Modal / Bottom Sheet** | نافذة منبثقة سفلية سلسة | إضافة عميل جديد، إضافة مورد جديد |
| **Date Inputs & Chips** | حقول تاريخ ذكية مع رقاقات | تصفية كشوف حسابات العملاء والموردين |
| **Stat Cards** | بطاقات إحصائية مالية | إجمالي مديونيات العملاء ومستحقات الموردين |
| **Ledger List** | قوائم حركات مالية بتصنيف بصري | كشوف الحسابات (فواتير بالوردي، مقبوضات بالأخضر) |

---

## 5. مرجع أوامر البناء والتشغيل

* **تشغيل خادم الـ API (الباك إند):**
  ```bash
  cd backend && php artisan serve --host=0.0.0.0 --port=8000
  ```
* **تشغيل تطبيق الموبايل (SPA Preview):**
  ```bash
  cd mobile && php artisan serve --host=0.0.0.0 --port=8080
  ```
* **بناء حزم الواجهة (Vite Build):**
  ```bash
  cd mobile && npm run build
  ```
* **توليد تطبيق الأندرويد المباشر (Native APK Build):**
  ```bash
  cd mobile && build-apk.bat
  ```
