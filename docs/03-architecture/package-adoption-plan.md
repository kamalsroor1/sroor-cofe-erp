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
| `sentry/sentry-laravel` **أو** Laravel Nightwatch | تتبع الأخطاء على production | نضيف `tenant_id` في الـ context، ومن غير أي بيانات شخصية. **القرار بين الاتنين في مهمة الـ DevOps** |
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
