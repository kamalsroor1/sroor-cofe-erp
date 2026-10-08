---
name: quality-gate
description: Run Sroor ERP's code-quality gate (Pint, Larastan, ESLint, Prettier, PHPUnit) on the files you changed, read the results, and fix them in the right order before reporting or committing. Use after any PHP/Vue/JS change, before every commit, and whenever CI's php/frontend/desktop job fails.
---

# Quality gate

The same checks run in `.github/workflows/ci.yml` on `feature/multi-tenant`. Passing them locally first means CI stays green.

## 1. List what you changed
Work from the repo root. Include untracked new files:

```bash
git diff --name-only HEAD; git ls-files --others --exclude-standard
```

Split the list into three groups: PHP under `backend/`, `.vue/.js/.css` under `backend/resources/`, and `.js` under `desktop/`. Ignore root-level `*.py` scratch scripts, `backups/`, and anything generated (`public/build`, `defaultTranslations.*`).

## 2. Run the checks, cheapest first

| Order | Check | Command (from `backend/` unless noted) | Must be |
|---|---|---|---|
| 1 | PHP syntax | `php -l <file>` | clean |
| 2 | Pint (format) | `./vendor/bin/pint --dirty` (this writes), then `./vendor/bin/pint --test` (whole codebase, same as CI) | 0 files |
| 3 | Larastan level 5 | `composer analyse`, or `./vendor/bin/phpstan analyse <files>` for speed | 0 new errors |
| 4 | ESLint | `npm run lint:dirty` (changed files; `desktop/` has the same script) | 0 errors |
| 5 | Prettier | `npm run format:dirty`, then `npm run format:check` (whole codebase, same as CI; `desktop/` has the same scripts) | clean |
| 6 | Tests | `php artisan test --filter=<Relevant>`, and the full suite if you touched shared Services, middleware, tenancy or auth | green |
| 7 | Build (frontend changes only) | `npm run build` | 0 errors |
| 8 | Desktop (desktop changes only) | `cd desktop && npx eslint <files>` | 0 errors |

The one-time full format pass has landed, so the whole codebase is Pint- and Prettier-clean and CI checks the whole codebase (`./vendor/bin/pint --test`, `npm run format:check`). Write only to the files you changed (`pint --dirty`, `format:dirty`); a whole-codebase check that fails means one of your changes is unformatted. ESLint stays changed-files-only (`lint:dirty`) until the legacy lint errors are fixed.

## 3. Fix in this order
1. Syntax and test failures. These are real bugs.
2. Larastan errors in code you wrote. Fix the types; see the `larastan-fixing` skill.
3. ESLint errors. Prettier owns formatting, ESLint owns correctness.
4. Formatting, by re-running the writers.

## Hard rules
- **Never add a new entry to `phpstan-baseline.neon` to make an error go away.** The baseline only shrinks. Regenerate it only after a dedicated cleanup and say so in the history log.
- No `@phpstan-ignore`, `eslint-disable` or `// prettier-ignore` without a one-line reason in the comment and a mention in your report.
- Never weaken or skip a test to get green.
- Report the real output: counts of errors and failures, not "looks fine". If a check could not run, for example because a dependency is missing, say so.
