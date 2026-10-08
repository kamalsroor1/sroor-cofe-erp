# سجل تعديل: إعداد ESLint + Prettier للـ SPA وتطبيق Electron
* **التاريخ والوقت:** 2026-10-08 02:10
* **الدور المفعل:** frontend-vue
* **الهدف:** إضافة أدوات lint/format للواجهة (`backend/resources/js`) ولتطبيق سطح المكتب (`desktop/`) **بدون** إعادة تنسيق أي ملف موجود، لأن workflow آخر (Phase 0 security hotfixes) يعدّل نفس الملفات حالياً.

## 1. الملفات المعدلة
* `[NEW]` backend/eslint.config.js — flat config: `@eslint/js` recommended + `eslint-plugin-vue` `flat/recommended` + browser globals، وnode globals لملفات الـ config و`scripts/` و`e2e/`، و`eslint-config-prettier` في الآخر.
* `[NEW]` backend/.prettierrc.json — خيارات Prettier (انظر القرارات).
* `[NEW]` backend/.prettierignore — يستثني `public/build`، `vendor`، `node_modules`، `android`، `storage`، `defaultTranslations.*` (مولّد)، `resources/js/version.json` (يتغير مع الـ build)، الـ lockfiles، و`*.php`.
* `[NEW]` backend/scripts/run-on-changed.mjs — سكربت Node يعمل على Windows وLinux، يشغّل eslint/prettier على الملفات المتغيرة فقط (`git diff HEAD` + الملفات untracked) داخل الحزمة الحالية، على دفعات من 100 ملف.
* `[MODIFIED]` backend/package.json + package-lock.json — devDependencies: `eslint@^10.12.0`، `@eslint/js@^10.0.1`، `eslint-plugin-vue@^10.11.1`، `vue-eslint-parser@^10.4.1`، `globals@^17.13.0`، `prettier@^3.9.9`، `eslint-config-prettier@^10.1.8`. Scripts: `lint`، `lint:fix`، `lint:dirty`، `format`، `format:check`، `format:dirty`، `format:dirty:check`.
* `[MODIFIED]` backend/.editorconfig — إضافة `[*.vue] indent_size = 2` لتتطابق المحررات مع Prettier.
* `[NEW]` desktop/eslint.config.mjs — recommended + node globals + `sourceType: commonjs` (الكود CommonJS)، و`preload.js` يأخذ browser globals كمان.
* `[NEW]` desktop/.prettierrc.json، desktop/.prettierignore — نفس خيارات الـ backend (JSON بمسافتين ليطابق `package.json` الحالي).
* `[MODIFIED]` desktop/package.json + package-lock.json — `eslint`، `@eslint/js`، `globals`، `prettier`، `eslint-config-prettier` ونفس الـ scripts (الـ dirty helpers تستدعي `../backend/scripts/run-on-changed.mjs`). تمت إعادة مصفوفات `arch` لشكلها الأصلي في سطر واحد بعد ما `npm install` فكّها.
* `[MODIFIED]` CLAUDE.md — 3 أسطر في بلوك Commands.
* `[MODIFIED]` .claude/rules/frontend-vue.md — "Done means" تشمل `lint:dirty` و`format:dirty`.
* `[MODIFIED]` AGENTS.md — سطر مرآة في بند بوابات الجودة.

## 2. القرارات التقنية
* **ESLint 10 بدل 9:** وقت التنفيذ كان `latest = 10.12.0` و9.x متعلّم `maintenance` على npm. الإصدار 10 flat-config فقط، و`eslint-plugin-vue@10.11` بيدعم `^9 || ^10`.
* **خيارات Prettier مأخوذة من قياس الكود الحالي:** 342 ملف `.js`/`.vue` في `resources/js`:
  * المسافات: ملفات `.js` بأربع مسافات (56 مقابل 2)، وملفات `.vue` أغلبها مسافتين (template 229 مقابل 53، script 126 مقابل 54) → `tabWidth: 4` + override `*.vue → 2`.
  * `semi: true` (≈97% من الأسطر)، `singleQuote: true` (5901 مقابل 50)، `trailingComma: "es5"` (884 كائن/مصفوفة بفاصلة أخيرة مقابل 153)، `arrowParens: "always"` (348 مقابل 114)، `vueIndentScriptAndStyle: false` (كل الـ 282 script block بدون إزاحة)، `endOfLine: lf` (مطابق لـ `.gitattributes`).
  * `htmlWhitespaceSensitivity: "css"` (الافتراضي) لأنه لا يغيّر الـ rendering؛ `ignore` تقريباً نفس حجم الـ diff وفيه خطر تغيير المسافات بين العناصر inline.
  * `printWidth: 120`.
* **قياس حجم الـ diff لكل اختيار** (تنسيق نسخة في scratchpad خارج الريبو، `resources/js` فقط):
  | الإعداد | ملفات | إضافات | حذف |
  |---|---|---|---|
  | **المختار (js=4, vue=2, 120, es5)** | 318 | 11,523 | 7,983 |
  | 4 مسافات للكل | 326 | 27,989 | 23,232 |
  | مسافتين للكل | 333 | 18,382 | 14,865 |
  | printWidth 100 | 319 | 14,655 | 8,300 |
  | printWidth 140 | 315 | 9,462 | 8,482 |
  | trailingComma all | 318 | 11,533 | 7,992 |
  | trailingComma none | 339 | 12,212 | 8,672 |
  | htmlWhitespaceSensitivity ignore | 318 | 11,591 | 8,002 |
  * 140 أصغر بحوالي 8% لكن أصعب في القراءة على شاشات اللابتوب؛ اخترنا 120.
* **لم نضف `prettier-plugin-tailwindcss`** لأنه كان هيعيد ترتيب الـ classes في كل template.
* **`vue/flat/recommended` وليس `essential`:** بعد الضبط بقيت الضوضاء قليلة (119 مشكلة)، وrecommended بيكشف أخطاء حقيقية زي `no-mutating-props` و`no-template-shadow`.
* **ضبط القواعد:** القراءة الأولى أعطت 148 error و1429 warning؛ منها 1404 من `vue/attributes-order` (ترتيب attributes، شكلي بحت، والـ autofix كان هيعدّل كل template) → `off`. كمان `vue/first-attribute-linebreak` (شكلي) → `off`. `vue/multi-word-component-names` → `off` (`Pagination` و`Skeleton` أسماء ثابتة وتغييرها يكسر الـ imports). `no-unused-vars` بـ `caughtErrors: none` و`^_`، و`no-empty` بـ `allowEmptyCatch` لأن `catch (e) {}` مقصودة في bridges الـ native/Electron.
* لا يوجد أي أثر مالي/مخزني/tenancy — tooling للتطوير فقط، ولا تغيير في الـ bundle.

## 3. التحقق والاختبار
* `npm install` في `backend/` و`desktop/` — تم وحُدّثت الـ lockfiles.
* **backend `npm run lint`:** 345 ملف، 41 ملف فيه مشاكل → **108 errors، 11 warnings**:
  * `vue/no-mutating-props` 53 (في 10 مكونات: BrandingTab 11، ItemFormModal 9، StoreFormModal 7، CategoryFormModal 5، POSCartItem 5، …)
  * `no-unused-vars` 50، `vue/no-template-shadow` 6، `vue/no-use-v-if-with-v-for` 2 (DataTable)، `vue/no-v-html` 2 (Pagination)، `vue/attribute-hyphenation` 2، `no-useless-assignment` 2، `vue/no-unused-vars` 1، `vue/component-definition-name-casing` 1.
* **backend `npm run format:check`:** **318 ملف** من حوالي 346 ملف تم فحصها هيتغيروا في أول تنسيق.
* **desktop `npm run lint`:** 14 ملف → **1 error** (`main.js` بارامتر `res` غير مستخدم)، 0 warnings.
* **desktop `npm run format:check`:** **12 من 15 ملف** (+384 / −357 سطر).
* `lint:dirty` و`format:dirty:check` اتجربوا في وضع القراءة فقط (9 ملفات متغيرة وقت التجربة).
* **لم يتم تشغيل** أي `--fix` أو `--write` على كود موجود (الاستثناء الوحيد: ملفات الإعداد الجديدة اللي أنشأناها). **لم يتم تشغيل** `npm run build` لأنه بيعيد توليد `defaultTranslations.*` و`public/build` بينما workflow تاني شغال على نفس الشجرة؛ ولا يوجد تغيير في `vite.config.js` أو أي كود بيدخل الـ bundle.

## 4. ملاحظات / ديون تقنية
* الـ 108 errors الحالية ديون قديمة (أغلبها mutating props واستيرادات غير مستخدمة)، ولذلك `npm run lint` على الشجرة كلها بيرجع exit 1. لم يُضف lint للـ CI بعد؛ نقترح البدء بـ `lint:dirty` في الـ CI أو baseline.
* ملف legacy موجود: `resources/js/views/POS/PosView.legacy.grid.vue` (مخالف لقاعدة "لا نسخ `*.legacy.*`") وهو أكبر ملف في الـ diff (1030 سطر) — يُحذف قبل التنسيق الشامل.
* **خطة التنسيق الشامل لمرة واحدة:** بعد merge كل الـ branches/workflows المفتوحة (Phase 0 بالذات) وفي وقت ما فيش فيه أي workflow شغال: commit منفصل `style: apply prettier to frontend and desktop` فيه `npm run format` فقط في `backend/` و`desktop/`، ثم `npm run build` و`npm run e2e:desktop` للتأكد، ثم إضافة hash الـ commit في `.git-blame-ignore-revs`.
