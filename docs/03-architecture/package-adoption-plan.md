# خطة اعتماد الباكدجات (Package Adoption Plan)

> **الحالة:** معتمدة من الـ CTO في 2026-10-08 · **البرنش:** `feature/multi-tenant`
> **مرتبطة بـ:** [product-overview.md](../01-overview/product-overview.md) · [phase-1-plan.md](../05-planning/phase-1-plan.md) · [pricing-model-recommendation-2026-10.md](pricing-model-recommendation-2026-10.md)

## القاعدة قبل تثبيت أي باكدج
1. نتأكد إنها بتدعم **Laravel 13 / PHP 8.3+**، وإن آخر release ليها حديث وفيه حد بيطورها.
2. نتأكد إنها متوافقة مع **stancl/tenancy**، يعني الداتا والكاش والملفات بتاعة كل عميل تفضل جوه الـ tenant بتاعه.
3. تتثبت جوه المهمة اللي محتاجاها، ومعاها test، ويتكتب السبب في سجل الشغل.

## الموجود حاليًا
`laravel/sanctum` · `laravel/pulse` · `laravel/telescope` · `laravel/tinker` · `laravel/pail` · `laravel/pint` · `larastan/larastan` · `spatie/laravel-permission` · `stancl/tenancy`، وفي الواجهة: ESLint + Prettier.

## Phase 1: أساس الـ SaaS
| الباكدج | الاستخدام | ملاحظات tenancy |
|---|---|---|
| `laravel/pennant` | مميزات الباقات والـ add-ons لكل عميل (`scope = Tenant`)، والـ API بتاع إخفاء المميزات في الواجهة، وإطلاق أي ميزة جديدة لعملاء معينين الأول | **[Q-E1، 2026-10-08] طبقة API بس**: مصدر الحقيقة الوحيد هو `TenantEntitlementService` (الباقة + الـ add-ons + الـ overrides). Pennant بيستخدم store من نوع `array` وبيسأل الـ service، ومش بيخزّن حالة لوحده، علشان ميبقاش فيه مصدرين بيقولوا حاجتين مختلفين |
| `laravel/fortify` (headless) | **2FA إجباري للسوبر أدمن**، واسترجاع كلمة السر، وتأكيد الإيميل | للـ guard المركزي (`CentralUser`) الأول، وبعد كده لمستخدمين العملاء |
| `laravel/horizon` | مراقبة وإدارة الـ queue على Redis | الـ VPS بس، والدخول عليه للسوبر أدمن المركزي بس |
| `spatie/laravel-backup` | Backup يومي متشفّر لكل DB عميل وللـ DB المركزي على Google Drive (OAuth)، والاحتفاظ 7/4/3 | الـ DBs كلها تمر عن طريق `tenancy()->runForMultiple` |
| `spatie/laravel-activitylog` | ميزة **سجل الرقابة** (`audit.logs`)، وتسجيل إلغاء الفواتير وتفعيل الاشتراكات والـ impersonation | جدول الـ log جوه **DB العميل** لعمليات العميل، وفي **الـ DB المركزي** لعمليات السوبر أدمن |
| `spatie/laravel-medialibrary` | لوجو المحل، وإيصالات تحويل الاشتراك، وصور الأصناف، ولوجو المنصة | **disk ومسار لكل عميل**، والتحقق من نوع الملف وحجمه، وأسماء الملفات بيولّدها السيرفر |
| `sentry/sentry-laravel` **أو** Laravel Nightwatch | تتبع الأخطاء على production | نضيف `tenant_id` في الـ context، ومن غير أي بيانات شخصية. **[PKG-1، Q-O2] اتحسم: Sentry** (التفاصيل في «نتيجة الـ spike» تحت) |
| `spatie/laravel-health` | فحص صحة السيرفر بعد الـ deploy (DB وRedis والـ queue والمساحة والـ backups)، ولو فشل الـ deploy يرجع للنسخة اللي قبلها | endpoint محمي |
| `@vueuse/core` | `useOnline` لشريط الـ offline ومنع البيع، وcomposables عامة | — |

## Phase 2: سلامة مالية واختبارات، والـ offline في آخرها
| الباكدج | الاستخدام |
|---|---|
| `laravel/precognition` (`laravel-precognition-vue`) | Validation مباشر في الفورمز الكبيرة (الـ POS والفواتير والمشتريات) بنفس الـ Form Requests |
| `dexie` | IndexedDB للبيع offline: كتالوج الأصناف على الجهاز، وطابور الفواتير اللي ليها `client_uuid` |
| `vite-plugin-pwa` | Service worker (Workbox) لتشغيل الواجهة offline وتحديثها |
| `maatwebsite/excel` | خدمة نقل بيانات العميل، و**نقل المحل الحالي من `main`**، وتصدير التقارير (`reports.export`) |

## Phase 3: النمو
| الباكدج | الاستخدام |
|---|---|
| `laravel/scout` | بحث سريع في الأصناف للعملاء الكبار. نبدأ بالـ database driver، وبعدين Meilisearch |
| `laravel/reverb` | Real-time: المخزون بين الكاشيرات، وإشعارات السوبر أدمن، وحالة المزامنة offline |
| `laravel/socialite` | تسجيل الدخول بـ Google في التسجيل الذاتي |

## وقت الحاجة
- `laravel/prompts`: أوامر artisan تفاعلية لإنشاء العملاء وإدارتهم، مع تأكيد قبل أي عملية خطيرة.

## مرفوضة
| الباكدج | السبب |
|---|---|
| Cashier (Stripe / Paddle) | مش متاحين بشكل عملي في مصر. هنستخدم Paymob وFawry من خلال `PaymentGateway` |
| Octane | الـ state ممكن يتسرب بين الطلبات والعملاء مع stancl/tenancy، ومش محتاجينها في الحجم الحالي |
| Passport | Sanctum كفاية |
| Dusk | عندنا Playwright |
| Envoy | الـ deploy هيبقى من GitHub Actions |
| Folio / Mix / Head / Homestead / Valet / Sail | خاصة بـ Blade أو ببيئات التطوير، ومش مناسبة لـ Vue SPA |

## نتيجة الـ spike (PKG-1، 2026-10-08)

> اتعمل على `feature/multi-tenant` (Laravel 13.35، والإنتاج مثبّت على PHP 8.3 عن طريق `config.platform.php = 8.3.30`). التست اللي بيقفل القرارات دي: `backend/tests/Feature/Platform/PackageAdoptionTest.php`.

### جدول القرار
| الباكدج | الإصدار المثبّت (القيد) | L13 / PHP 8.3 | القرار | الـ migrations | ملاحظات / الـ fallback |
|---|---|---|---|---|---|
| `laravel/pennant` | 1.26.0 (`^1.26`) | ✔ / ✔ | **اعتماد** | ولا جدول. `config/pennant.php` مثبّت على store `array` (من غير env)، فمفيش جدول `features` | طبقة API بس فوق `TenantEntitlementService` (Q-E1). الـ fallback: الخدمة لوحدها |
| `laravel/fortify` | 1.41.0 (`^1.41`) | ✔ / ✔ | **اعتماد، بس مطفي** | central بس (أعمدة 2FA على `central_users`، وبيعملها IDEN-1.12 في رينج `1000xx`) | متحطوط في `extra.laravel.dont-discover`، ومعاه تابعه `laravel/passkeys` 0.2.1، علشان ميسجلوش routes زي `/login` و`/register` و`/passkeys/login` على الـ guard `web` في أي host. IDEN-1.12 هو اللي بيسجل الـ provider بإيده على guard `central` ومعاه `Fortify::ignoreRoutes()`. الـ fallback: TOTP مخصص (`pragmarx/google2fa` 9.x، ودي أصلًا dependency لـ Fortify) |
| `laravel/horizon` | 5.50.0 (`^5.50`) | ✔ / ✔ | **اعتماد** (قرار ب) | ولا جدول (Redis) | بيشتغل بس لما يكون `QUEUE_CONNECTION=redis` على الـ VPS. Hostinger والتطوير بيفضلوا على الـ `database` queue زي ما هم. الباكدج محتاج `ext-pcntl`/`ext-posix`، ودول مش موجودين على Windows، فاتضافوا كـ stubs في `config.platform`. يعني composer مش هيتأكد منهم على السيرفر، فالـ VPS لازم يكون عليه `php8.3-cli` (فيه pcntl وposix). اللوحة `/horizon` بترجع 403 في أي بيئة غير `local` لحد ما IDEN-1.7 يربطها بالـ gate المركزي. `horizon:terminate` موجود لـ OPS-3. الـ fallback: `queue:work` تحت supervisor |
| `spatie/laravel-backup` | 10.3.3 (`^10.3`) | ✔ / ✔ | **اعتماد** | ولا جدول | محتاج `ext-zip`. الإعدادات (`config/backup.php`) بتاعة OPS-5 ومش منشورة لسه، فالأمر `backup:run` موجود بس لسه مش متجدول. الـ fallback: `mysqldump` وتشفير في أمر مخصص |
| `spatie/laravel-activitylog` | **4.12.3** (`^4.12`) | ✔ / ✔ | **اعتماد** (قرار أ تحت) | مش بتتحمّل أوتوماتيك. لو احتجناها: رينج `0500xx` | الإصدار 5.x محتاج **PHP 8.4**، فمش هينفع طول ما الإنتاج على 8.3. نرقّي لما المنصة تتنقل لـ 8.4 |
| `spatie/laravel-medialibrary` | 11.23.9 (`^11.23`) | ✔ / ✔ | **اعتماد** | الجدول `media` بيعمله PKG-2 في رينج `0500xx` (central وtenant) | محتاج `ext-exif` و`ext-fileinfo`. الـ migration مش بتتحمّل أوتوماتيك. الـ fallback: `UploadedAsset` بسيط |
| `spatie/laravel-health` | 1.40.2 (`^1.40`) | ✔ / ✔ | **اعتماد** | الـ result store الافتراضي `EloquentHealthResultStore` محتاج جدول. OPS-3 بينشر `config/health.php`، وبعدين يا يختار `CacheHealthResultStore` (من غير جدول)، يا يعمل الجدول في الـ central (`0500xx`) | من غير أي route عام. الـ endpoint المحمي والفحوصات بيعملهم OPS-3 وOPS-7. الـ fallback: endpoint مخصص |
| `sentry/sentry-laravel` | 4.28.0 (`^4.28`) | ✔ / ✔ | **اعتماد، وده اللي اتحسم بيه Q-O2** | ولا جدول | `config/sentry.php` فيه `send_default_pii = false` ثابت (من غير env). لو الـ DSN فاضي يبقى مطفي. ربطه بالـ exceptions (`Integration::handles` في `bootstrap/app.php`) وإضافة `tenant_id` في الـ scope شغل OPS-7 |
| `laravel/nightwatch` | — | — | **مرفوض في Phase 1** | — | محتاج agent شغال طول الوقت (`nightwatch:agent`)، وده مش هينفع على Hostinger المشترك. كمان مربوط بخدمة Laravel المدفوعة. Sentry بيبعت HTTP بس وبيشتغل في البيئتين |
| `@vueuse/core` | 15.0.0 (`^15.0.0`) | Vue ^3.5 ✔ | **اعتماد** | — | `@vuepic/vue-datepicker` جايب معاه نسخة 14.x كـ dependency داخلية، والاتنين متقبلين لأن الـ tree-shaking بيشيل اللي مش مستخدم |

### القرارات اللي PKG-1 كان مطلوب منه يحسمها
- **(أ) activitylog في جانب المستأجر:** `ActivityLogService` **بيفضل مخصص (الـ fallback)** على جدول `activity_logs` اللي في الـ tenant. السبب إن الجدول ده فيه `store_id` و`module`، وعليه بيانات حقيقية وشاشة تقارير، وبيستخدمه 12 ملف. لو نقلناه لـ `activity()` هنحتاج جدول تاني ونقل للبيانات، وده برّه نطاق 1a. IDEN-2.8 وPOSB-4 وPOSB-8 بيكلموا `ActivityLogService` بس. في جانب المنصة، `CentralAuditLog` (IDEN-1.5) شغال دلوقتي بالتصميم المخصص، وأعمدته شبه أعمدة activitylog، فيقدر يبقى `extends Spatie\Activitylog\Models\Activity` بعدين من غير ما ننقل بيانات (متابعة على مسار IDEN).
- **(ب) Horizon:** اتثبت دلوقتي، وبيشتغل بس مع Redis على الـ VPS، ومفيش أي تغيير على Hostinger أو التطوير.
- **(ج) Pennant:** صف الجدول اللي فوق متصحح أصلًا (طبقة API، ومش مصدر الحقيقة)، والـ config بقى مطابق ليه (`array`).
- **(د) Q-O2:** اتحسم لـ **Sentry**، وهو بس اللي اتثبت.

### قواعد لكل اللي هيستخدم الباكدجات دي
- مفيش باكدج بيحمّل migrations لوحده. أي جدول لباكدج بيتعمل يدويًا في `database/migrations/` أو `database/migrations/tenant/` في رينج `2026_10_10_050000`–`059999`.
- مفيش route لأي باكدج يتفتح من غير gate (والتست بيتأكد من Fortify وPasskeys وHealth وHorizon).
- متطلبات السيرفر: `ext-exif` و`ext-zip` و`ext-fileinfo`، وعلى الـ VPS كمان `ext-pcntl` و`ext-posix` و`ext-redis`.

### التحديثات الأمنية اللي اتعملت في نفس المهمة
- `composer audit`: اترقّى `laravel/framework` من 13.25.0 لـ 13.35.0 (XSS في صفحة الـ debug)، و`league/commonmark` من 2.10.0 لـ 2.10.3 (DoS وتخطي DisallowedRawHtml)، و`league/flysystem` من 3.35.2 لـ 3.36.0 (تخطي فحص الـ path). كل ده جوه القيود الحالية. بعدها `composer audit` طلع 0.
- `npm audit --omit=dev` في `backend/`: اتعمل `npm audit fix` من غير `--force`، فاترقّى axios لـ 1.20.0، و`@capacitor/android` لـ 8.5.3 (ثغرة critical)، وvue لـ 3.5.43، و`@xmldom/xmldom` و`brace-expansion` و`source-map-js`. فاضل **3 ثغرات moderate** في `uuid` جاية من `xcode` جوه `@capacitor/cli` (أداة build خاصة بـ iOS). الحل الوحيد ليها downgrade breaking، فاتسابت.
- `npm audit --omit=dev` في `desktop/`: 0 ثغرات.
