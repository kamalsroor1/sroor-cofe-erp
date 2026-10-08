---
name: larastan-fixing
description: How to fix Larastan/PHPStan (level 5) errors in Sroor ERP's Laravel 13 code correctly — Eloquent models, relations, collections, DTOs, Actions — and how the baseline is managed. Use when `composer analyse` reports errors, when CI's analyse step fails, or when shrinking phpstan-baseline.neon.
---

# Fixing Larastan errors

Config: `backend/phpstan.neon` (level 5, Larastan extension) with `backend/phpstan-baseline.neon`, which holds legacy errors only.

## Workflow
1. Reproduce on just the file: `./vendor/bin/phpstan analyse path/to/File.php --error-format=table`.
2. Read the **identifier** in the error, such as `property.notFound`, `method.notFound`, `argument.type`, `return.type` or `larastan.noUnnecessaryCollectionCall`. Fix the cause, not the symptom.
3. Re-run until clean, then run the relevant tests. A type fix can change behaviour.

## Common causes in this codebase and the right fix

| Error | Usual cause | Correct fix |
|---|---|---|
| `Access to an undefined property App\Models\X::$foo` | Column not declared | Add `@property` docblocks on the model (types matching the casts: money and quantities are `string`, because they are `decimal:3`) |
| `Call to an undefined method ...Builder::active()` | Local scope not visible | Type the scope: `public function scopeActive(Builder $q): Builder`, and add `@method static Builder<static> active()` if needed |
| Relation returns `mixed` | Untyped relation | `public function items(): HasMany` with `@return HasMany<InvoiceItem, $this>` |
| `argument.type` with string and float on money | Real bug: float arithmetic on money | Use `bcadd/bcsub/bcmul/bcdiv` on strings, scale 3. Never cast money to `float` to silence it |
| Nullable access (`Cannot call method on X\|null`) | `find()` or `first()` can return null | `findOrFail()` / `firstOrFail()`, or an explicit null check that returns or throws a localized error |
| Collection generics | `collect()` of mixed | Annotate with `@var Collection<int, Item> $items` or type the producing method |
| `tenancy()` / `tenant()` helpers | stancl helpers are loosely typed | Narrow with an `instanceof Tenant` check. Don't ignore |
| DTO `fromArray()` | Array shape unknown | Add `@param array{name: string, price: string, ...} $data` |

## Never
- Add new baseline entries, or `@phpstan-ignore-line` without a reason comment.
- Lower the level in `phpstan.neon`, or add paths to `excludePaths` to hide errors.
- Change runtime behaviour just to satisfy the analyser. If a "fix" changes logic, it needs a test.

## Shrinking the baseline (dedicated cleanup tasks only)
1. Fix a group of errors with the same identifier in one area (one model, one domain).
2. Run `composer analyse:baseline` to regenerate. The diff of `phpstan-baseline.neon` must only **remove** lines.
3. Commit as `refactor(types): ...` with the before/after error count in the message body and in the history log.
