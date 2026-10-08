# Security & operations rules (always loaded)

## Production is off-limits by default
This repo contains many root-level Python/PHP scripts (`deploy_*.py`, `check_*live*.py`, `fix_*.py`, `run_*.py`, `restore_*_db.py`, `seed_*.py`, `sync_*.py`, `update_webhook.php`…) that SSH into the **live server**, touch the **live database**, or push to git.

- **Never run any of them, and never write a new one that connects to production, unless the user explicitly asks for that specific action in the current message.** "Fix the bug" is not permission to deploy the fix.
- `deploy_root_baraa.py` is the sanctioned deploy path, and it is heavy: bumps the version, builds, `git add .` + commit + push, then SSHes to production. Run it only on an explicit "deploy"/"انشر" request, and first tell the user what is about to be committed (`git status`) — `git add .` will sweep in every untracked scratch file.
- Never run against production: `migrate:fresh`, `db:wipe`, `tenants:migrate-fresh`, seeders, restore scripts, or raw `UPDATE`/`DELETE` without a `WHERE` reviewed by the user. Take/confirm a backup first for any live data fix.
- Pushing to `main` triggers the GitHub Actions deploy. Don't push, force-push, or merge to `main` without being asked.

## Secrets
- Several tracked scripts and the CI workflow contain **hardcoded credentials/tokens** (SSH password, deploy webhook token). Treat them as compromised-if-leaked:
  - Never print, quote, copy, summarize, or move those values — not in chat, docs, history logs, commit messages, or new files.
  - Never add new secrets to tracked files. New config goes through `.env` / `config/*.php` with `env()`; CI secrets through GitHub Actions secrets.
  - When editing such a script, leave the secret line untouched and recommend moving it to an environment variable.
- Never read `.env` aloud or commit it. `.env.example` holds keys with empty/dummy values only.

## Application security checklist (apply to every backend change)
- **AuthN**: every `/api/v1` route is behind `auth:sanctum` unless it is deliberately public (login, tenant resolver, app-update check). Public routes get rate limiting.
- **AuthZ**: permission or policy check on every action (`spatie/laravel-permission` names like `customers.manage`, `pos.access`, `invoices.create`). Admin-only operations (invoice deletion, settings, branding, users/roles) are enforced server-side, not just hidden in the UI.
- **Isolation**: tenant DB + store scoping (see `multi-tenancy.md`). Route-model binding must not let a user load a record from a store they can't access.
- **Mass assignment**: explicit `$fillable`; build models from DTO `toArray()` / validated data only — never `$request->all()`.
- **Injection**: query builder bindings only. No string-interpolated `DB::raw`, `whereRaw`, `orderByRaw` with request input; whitelist sortable/filterable columns.
- **Uploads** (logos, APKs, imports): validate mime + size, store on a tenant-scoped disk path, generate the filename server-side, never execute or include uploaded content.
- **Output**: never return password hashes, tokens, internal ids of other tenants, or stack traces. Shape responses with API Resources. `APP_DEBUG=false` assumptions hold in prod.
- **Telescope/Pulse**: super-admin only through the existing gate/bridge. Don't widen it.
- **Webhooks / tokens in URLs**: don't add more. Prefer signed requests/headers.
- **Dependencies**: don't add a composer/npm package without saying why and checking it is maintained. Prefer what is already installed.

## Git hygiene
- Work on a feature branch (current long-running branch: `feature/multi-tenant`). Conventional Commits, English, imperative, scoped: `fix(pos): …`, `feat(api): …`, `refactor(items): …`, `test(api): …`, `docs(history): …`, `chore(build): …`.
- Commit only when asked. Stage explicit paths — **never `git add .` / `git add -A`** here; the working tree is full of untracked scratch scripts, screenshots, and DB backups (`backups/`) that must not be committed.
- Never commit: `.env*` with real values, `backups/`, `*.sql` dumps, APKs, screenshots, `test-results/`, `node_modules`, `vendor`.
- One-off diagnostic scripts belong in the scratchpad/temp dir, not the repo root. Don't add more root-level `check_*.py` / `test_*.py` files.
- No `--no-verify`, no history rewrites on shared branches, no destructive git (`reset --hard`, `clean -fd`, `checkout -- .`) without confirmation — `clean` would delete the user's untracked backups.
