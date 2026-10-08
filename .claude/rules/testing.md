---
paths:
  - "backend/tests/**"
  - "e2e/**"
  - "backend/e2e/**"
  - "playwright.config.js"
  - "backend/phpunit.xml"
---

# Testing rules

## Backend — PHPUnit 12 (`backend/tests/`)
- Runs on sqlite `:memory:` (see `phpunit.xml`). `Tests\TestCase::setUp()` applies `database/migrations/tenant` so tenant tables exist. Use `RefreshDatabase`.
- Location & naming: `tests/Feature/Api/<Resource>ApiTest.php` — one per API controller. Domain/service behaviour → `tests/Feature/<Thing>Test.php`. Pure calculations → `tests/Unit/`.
- PHPUnit 12 style: `declare(strict_types=1);`, methods `test_snake_case_describes_behaviour(): void`, **attributes not docblock annotations** (`#[DataProvider('…')]`, `#[Test]`).
- Standard fixture (copy the shape from `CustomersApiTest`): seed `PermissionsSeeder`, create a main `Store`, an admin user with `assignRole('admin')`, an unprivileged user, Sanctum tokens via `createToken()->plainTextToken`, call with `Authorization: Bearer` + `X-Store-Id` where relevant.

### Every API controller test covers
1. **Happy path** for each endpoint — assert status, JSON structure, **and** database state (`assertDatabaseHas`, stock/balance values).
2. **Validation 422** — missing required, wrong types, negative/zero where forbidden, over-precision decimals, overlong strings.
3. **AuthN 401** (no token) and **AuthZ 403** (token without the permission).
4. **Isolation** — records from another store / context are not listed, shown, updated, or deleted.
5. **Edge cases** — 404 on missing id, soft-deleted records, empty lists, pagination bounds, Arabic text input, fractional quantities like `0.250`.

### Money & stock tests additionally
- Assert exact decimal strings (`'125.500'`), never `assertEqualsWithDelta` to paper over float math.
- **Rollback test**: force a failure mid-operation and assert stock, balances, and document tables are unchanged.
- **Reversal test**: cancel/edit/delete then restore returns stock and balances to the exact prior values.
- **Concurrency**: follow `tests/Feature/ConcurrencyTest.php` for double-sell scenarios.

### Discipline
- A failing test is information. **Never** weaken an assertion, delete a test, add `markTestSkipped`, or special-case `app()->environment('testing')` in app code to get green. If the app is wrong, report it; the fix belongs to the backend change, not the test.
- Tests are independent and order-free. No reliance on ids being `1`, no sleeping, no real network (fake `Http`, `Queue`, `Notification`, Telegram).
- Write the test **before** refactoring a controller (characterization first), and re-run after each refactor step.
- Run narrow first (`php artisan test --filter=CustomersApiTest`), full suite before declaring done.

## E2E — Playwright (`e2e/`, config at repo root)
- Projects: `desktop`, `tablet`, `mobile`; locale `ar-EG`, timezone `Africa/Cairo`; base URL `http://127.0.0.1:8000` (auto-starts `artisan serve`). Run from `backend/`: `npm run e2e:desktop`, `npm run e2e:flow`, `npm run e2e:page "<title>"`.
- Specs: `e2e/flows/<page>-full-page-audit.spec.js` for page audits, `<name>-flow.spec.js` for user journeys. Reuse helpers in `e2e/utils/` and auth state from `e2e/auth/`.
- Selectors: prefer roles/labels/`data-testid`. **Never select by translated text** unless the test is about the text — it breaks on copy changes and locale.
- A page audit asserts: loads without console errors, skeleton → content, RTL, dark+light toggle, the 5 viewport widths, main CRUD flow, empty state, permission-hidden actions.
- No `waitForTimeout` — wait on locators/responses. Tests create their own data and clean up; **E2E never runs against production URLs**.
- Artifacts (`e2e/screenshots`, `test-results`, reports) are git-ignored — don't commit them.
