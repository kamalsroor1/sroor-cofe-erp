# Deploy runbook — الـ release على الـ VPS (OPS-3)

> الحالة: **تصميم معتمد، لم يُنفَّذ بعد** (`.github/workflows/release.yml` و`scripts/ops/deploy.sh` من مهمة OPS-3 في `docs/05-planning/phase-1-plan.md`). هذا الملف هو ترتيب الخطوات الذي يجب أن يلتزم به الـ pipeline، وهو أيضًا مرجع الـ deploy اليدوي في بروفة الـ rehearsal.
>
> - لا يحتوي أي سر ولا عنوان خادم. الأسرار في GitHub Environment secrets و`shared/.env` على الخادم فقط (`docs/07-operations/secrets.md`).
> - **لا علاقة له بـ `deploy.yml`** (ينشر `main` على الخادم المشترك الذي عليه المحل الحي) ولا بسكريبتات الجذر `deploy_*.py`. لا تُلمس.
> - تخطيط المجلدات من `scripts/ops/steps/90-app-layout.sh` (OPS-1): `releases/<sha>/`، `current -> releases/<sha>`، `shared/.env`، `shared/storage/`.

---

## 1. الترتيب

كل الأوامر من `releases/<sha>/backend` كمستخدم التطبيق، داخل `set -euo pipefail`، **بدون `|| true`**: أي خطوة تفشل توقف الـ release قبل تبديل الـ symlink (أو تُشغّل الـ rollback إن كانت بعده).

| # | الخطوة | ملاحظات |
|---|---|---|
| 1 | CI أخضر (`php`، `mysql`، `frontend`، `desktop`، `gitleaks`) | الـ release يعمل على tag أو يدويًا فقط |
| 2 | build الـ artifact | `composer install --no-dev --optimize-autoloader`، `npm ci && npm run build` (يشغّل `lang:export`). لا `node_modules` في الـ artifact |
| 3 | رفع الـ artifact بمفتاح SSH من الـ Secrets ← `releases/<sha>/` | مفتاح الـ deploy فقط، بلا كلمات مرور |
| 4 | ربط المشترك | `ln -sfn $APP_ROOT/shared/.env backend/.env`، و`backend/storage` ← `$APP_ROOT/shared/storage` (احذف `storage/` القادم مع الـ artifact أولًا) |
| 5 | **`php artisan storage:link --force`** | يعيد إنشاء روابط `config/filesystems.php` → `links` داخل `public/` الخاص بهذا الـ release: `public/storage` ← `storage/app/public` و`public/central-assets` ← `storage/app/central/public`. لازم في **كل** release لأن `public/` جزء من الـ release وليس من `shared/`. `--force` يستبدل رابطًا قديمًا. الأهداف تُنشأ مسبقًا في `90-app-layout.sh`. ملفات المستأجرين العامة تُخدم عبر مسار stancl للأصول وليس عبر هذا الرابط |
| 6 | فحص بيئة الإنتاج | يفشل الـ release إن كان `APP_DEBUG` ليس `false` أو `TELESCOPE_ENABLED` ليس `false` (`scripts/ops/check-env.sh`) |
| 7 | `php artisan migrate --force` | المركزي فقط، بمستخدم `sroor_migrator` |
| 8 | `php artisan tenants:migrate --force` | أي مستأجر يفشل يوقف الـ release |
| 9 | `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache` | بعد ربط `.env` (الخطوة 4)، وإلا تُخبَّأ قيم فارغة |
| 10 | تبديل ذري: `ln -sfn releases/<sha> current.tmp && mv -Tf current.tmp current` | ثم `systemctl reload php8.3-fpm` لتفريغ الـ opcache |
| 11 | `php artisan horizon:terminate` (أو `queue:restart` مع `QUEUE_MODE=worker`) | supervisor يعيد تشغيل الـ workers على الكود الجديد |
| 12 | health check | endpoint `spatie/laravel-health` المحمي (DB المركزي، Redis، الـ queue، المساحة). يفشل ← rollback |
| 13 | تنظيف | إبقاء آخر 5 releases؛ لا يُحذف `current` ولا السابق له |

---

## 2. الـ rollback

1. `ln -sfn releases/<previous-sha> current.tmp && mv -Tf current.tmp current`، ثم `systemctl reload php8.3-fpm` و`php artisan horizon:terminate`.
2. الـ release السابق جاهز بروابطه (`storage:link` يخص مجلده)، فلا حاجة لإعادة الخطوة 5.
3. **الـ migrations لا تُعكس تلقائيًا.** أي migration في الـ release يجب أن تكون متوافقة للخلف (الكود السابق يعمل على الـ schema الجديد). إن لم يكن ذلك ممكنًا فالـ release يُعلَّم «بلا rollback آلي» ويحتاج backup حديث قبل النشر.
4. سجّل السبب، وأعد الـ deploy بعد الإصلاح.

---

## 3. checklist بروفة الـ rehearsal (يكمل `vps-runbook.md` §10)

- [ ] deploy أول release ← `current` يشير إليه، `curl` على `/up` و health check = 200.
- [ ] `ls -l releases/<sha>/backend/public/storage releases/<sha>/backend/public/central-assets` ← الرابطان يشيران داخل `shared/storage`؛ شعار مرفوع من الـ super-admin يظهر.
- [ ] release ثانٍ ← الروابط موجودة فيه أيضًا (الخطوة 5 تعمل في كل release).
- [ ] rollback ← الـ release السابق يعمل، والملفات المرفوعة بعد الـ release الثاني ما زالت ظاهرة (لأنها في `shared/storage`).
- [ ] release بـ migration فاشلة عمدًا على مستأجر تجريبي ← يتوقف قبل تبديل `current`.
