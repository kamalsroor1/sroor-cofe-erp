---
name: ci-pipeline
description: Maintain and debug Sroor ERP's GitHub Actions — the CI quality gates (ci.yml on feature/multi-tenant) and the auto-deploy workflow (deploy.yml on main). Use when a CI job fails, when adding/changing a CI job, when reproducing CI locally, or when setting up branch protection / deploy pipelines.
---

# CI pipeline

## Workflows
| File | Trigger | What it does | Risk |
|---|---|---|---|
| `.github/workflows/ci.yml` | push / PR on `feature/multi-tenant` | **php**: Pint `--test` + `composer analyse` + `php artisan test`. **frontend**: `npm ci`, ESLint on changed files, `npm run format:check` (whole codebase), `npm run build`. **desktop**: `npm ci` + `npm run lint` + `npm run format:check` in `desktop/` | Safe, read-only |
| `.github/workflows/deploy.yml` | push to `main` / `master` | Deploys the **single-tenant `main` app to the live shop server** | **Production.** Never edit it, trigger it or push to `main` without the CTO explicitly asking in that message |

## Reproduce a failing job locally
1. `gh run list --branch feature/multi-tenant --limit 5`, then `gh run view <id> --log-failed`. Read-only.
2. Run the same command locally from `backend/` (or `desktop/`). The commands are the ones in the `quality-gate` skill.
3. Fix the cause. Never "fix" CI by deleting a step, adding `continue-on-error`, widening ignores, or adding baseline entries.

## Changing CI
- Keep jobs parallel and cached (composer and npm caches keyed on lockfiles).
- Tests in CI use sqlite `:memory:` and a generated `APP_KEY`. **Never put real secrets in workflow files.** Anything sensitive goes in GitHub Actions Secrets, referenced as `${{ secrets.NAME }}`, and you tell the CTO which secret to create.
- The one-time full format pass has landed: Pint (`./vendor/bin/pint --test`, scoped by `backend/pint.json`) and Prettier (`npm run format:check`) check the **whole codebase**. ESLint in the frontend job is still **changed files only** because of legacy lint errors; switch it to `npm run lint` once those are fixed.
- Job names are the "required status checks" in branch protection. If you rename a job, tell the CTO to update the protection rule.

## Branch protection (the CTO does this in GitHub settings, or Claude does it only with explicit permission)
Settings → Branches → rule for `feature/multi-tenant`: require a pull request before merging, require status checks `php`, `frontend` and `desktop` to pass, require branches to be up to date, and block force pushes.

## Future deploy pipeline (Phase 1, VPS)
Planned order: CI green → build artifacts → SSH with a **key from Secrets** → release directory + symlink switch → `php artisan migrate --force` (central) + `tenants:migrate` → `config:cache` / `route:cache` → `queue:restart` → health check → automatic rollback to the previous release on failure. It replaces the root `deploy_*.py` scripts.
