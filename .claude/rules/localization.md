---
paths:
  - "backend/lang/**"
  - "backend/resources/js/**"
  - "backend/app/**/*.php"
---

# Localization rules (Arabic + English, single source of truth)

## The gate
**No user-visible literal text anywhere** — not in Vue templates, not in `<script setup>`, not in PHP responses, exceptions, validation messages, notifications, exports, or print layouts. Arabic literals are just as forbidden as English ones.

Allowed literals: log messages for developers, test fixtures, seed/demo data, code comments, translation files themselves.

## Source of truth
- `backend/lang/ar/<file>.php` and `backend/lang/en/<file>.php` — nested PHP arrays. **Both languages, same keys, same commit.**
- The frontend bundle is **generated**: `php artisan lang:export` writes `resources/js/helpers/defaultTranslations.json` and `.js`. It runs automatically as `prebuild` of `npm run build`.
- **Never hand-edit** `defaultTranslations.js` / `defaultTranslations.json`. If they're out of date, run the export.

## How to call
```php
__('customers.created_successfully')            // PHP
__('invoices.insufficient_stock', ['item' => $item->name])
```
```vue
<span>{{ $t('dashboard.today_sales') }}</span>          <!-- template -->
<script setup>
import { useTrans } from '@/Composables/useTrans';
const { t } = useTrans();
const title = computed(() => t('customers.title'));     // keep reactive to locale change
</script>
```
- **No fallback strings**: `$t('x.y') || 'نص'` and `t('x.y', 'Default')` are forbidden — they hide missing keys.
- Placeholders use Laravel syntax `:name` on both sides; never concatenate translated fragments to build a sentence (word order differs between ar and en).
- Pluralization/counts: separate keys or `trans_choice`, not string surgery.

## Key naming
- `file.key` or `file.group.key`, `snake_case`, English words: `customers.debt_status.debtor`.
- File = feature (`customers`, `invoices`, `pos`, `purchases`, `treasury`, `reports`, `settings`, `super`…). Shared verbs/nouns (`save`, `cancel`, `delete`, `search`, `actions`, `loading`, `no_data`) live in `common.php` — check there before adding a duplicate.
- Navigation labels → `nav.php`. Validation attribute names/messages → `validation.php`. Enum/status labels → the feature file under a `statuses` / `types` group.
- Don't rename or delete an existing key without grepping both `backend/app` and `backend/resources/js` for it.

## Workflow when adding text
1. Pick/confirm the key (grep `lang/ar` first — it may already exist).
2. Add to `lang/ar/<file>.php` **and** `lang/en/<file>.php` in the same position.
3. Use it via `__()` / `$t()` / `t()`.
4. `php artisan lang:export` (or `npm run build`).
5. Verify the screen in both locales; Arabic is the default and primary.

## Quality
- Arabic copy is natural Egyptian-market business Arabic (فاتورة، مرتجع، خزينة، وردية، مخزن، عميل، مورد). Keep terminology consistent with `docs/01-overview/glossary.md`.
- English copy is concise UI English, sentence case.
- Numbers, currency, and dates are formatted by the formatters, not embedded in translation strings.
