#!/usr/bin/env bash
set -euo pipefail
# Zero-downtime release on the VPS (OPS-3). Runs ON THE SERVER as the app user
# (APP_USER, e.g. `sroor`), called by .github/workflows/release.yml over SSH,
# or by hand during the rehearsal (docs/07-operations/deploy-runbook.md).
#
#   deploy:   bash deploy.sh --app-root /var/www/sroor --sha <git sha> \
#                 --artifact release.tar.gz [--artifact-sha256 HEX] \
#                 --deploy-env deploy.env [--env-file production.env] \
#                 [--php-version 8.5] [--queue-mode horizon|worker] [--keep 5] \
#                 [--health-url https://host/up|none] [--allow-no-health-checks]
#                 [--skip-pre-migration-backup] [--rehearsal]
#   rollback: bash deploy.sh --app-root /var/www/sroor --rollback \
#                 [--php-version 8.5] [--queue-mode horizon|worker] [--health-url URL|none]
#
# Order (every step must succeed; no failure is ever ignored):
#   preflight (layout, artifact, check-env.sh on the candidate .env: TELESCOPE_ENABLED=false,
#   BACKUP_ARCHIVE_PASSWORD set, SMTP...) -> extract releases/<id> -> shared .env + storage
#   -> storage:link --force -> pre-migration backup (`backup:tenants`, not on the 1st
#   release) -> migrate --force (central, as the migrator account)
#   -> per-table central grants (append-only audit)
#   -> db:seed CentralPermissionsSeeder (operator roles/permissions, idempotent)
#   -> db:seed PlansAndFeaturesSeeder (SaaS catalog: adds what is missing, never overwrites)
#   -> tenants:migrate-all (OPS-25: detached, one child process per tenant with a timeout,
#      never stops at the first failing tenant, JSON status file, final report; any
#      failed/timed-out tenant stops the release BEFORE the switch, see deploy-runbook.md)
#   -> app-releases:relocate (copy + verify release binaries to the private disk;
#      the public copy stays for the old release, idempotent)
#   -> config/route/view/event cache -> smoke health:check from the new release
#   -> atomic `current` switch -> php-fpm reload -> horizon:terminate (or
#   queue:restart) -> health:check + GET /up
#   -> app-releases:relocate --prune-legacy (NON-FATAL, logged) -> prune old releases
#
# Any failure BEFORE the switch removes the new release and restores shared/.env
# (users never saw it; exit 1). Any failure AFTER the switch rolls `current` back
# to the previous release automatically (exit 3; exit 4 = rollback needs a human).
# Migrations are NOT reversed: every migration must stay backward compatible
# with the previous release (deploy-runbook.md §2).
#
# Secrets: --env-file / --deploy-env are read with a parser that never executes
# them, passwords go to MySQL through mode-600 option files in a private temp
# dir (removed on exit), nothing secret is printed. The deploy env (migrator
# password) is never copied into the release or shared/; the caller deletes it
# (release.yml removes its upload directory in every case).
#
# [CTO-2026-11-11] --rehearsal: the .env must be a rehearsal .env (RELEASE_REHEARSAL=true,
# from `render-env.sh --rehearsal`) and without the flag it must NOT be one (check-env.sh
# --mode). The relaxed keys are printed as WARN lines (names only) at preflight and after
# the release went live, and the history line says `deployed-rehearsal`.
#
# Test hooks (scripts/ops/tests/deploy-test.sh): OPS_PHP_BIN, OPS_HEALTH_DELAY,
# OPS_HEALTH_ATTEMPTS. Nothing else changes behaviour.


# Physical path: a manual --rollback started from current/ keeps using its own files.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"
# shellcheck source=lib/mysql-grants.sh
source "$SCRIPT_DIR/lib/mysql-grants.sh"

umask 027

usage() {
    sed -n '/^#   deploy:/,/^#$/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//' >&2
    exit 2
}

APP_ROOT=""
SHA=""
ARTIFACT=""
ARTIFACT_SHA256=""
DEPLOY_ENV=""
NEW_ENV=""
PHP_VERSION_ARG="8.5"
QUEUE_MODE_ARG="horizon"
KEEP=5
HEALTH_URL=""
ALLOW_NO_HEALTH_CHECKS=0
SKIP_PRE_MIGRATION_BACKUP=0
ROLLBACK_ONLY=0
TENANT_TIMEOUT=900
TENANT_MAX_WAIT=7200
RELEASE_MODE="strict"
REHEARSAL_NOTES=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root | --sha | --artifact | --artifact-sha256 | --deploy-env | --env-file | --php-version | --queue-mode | --keep | --health-url | --tenant-timeout | --tenant-migrate-max-wait)
            [[ $# -ge 2 ]] || usage
            case "$1" in
                --app-root) APP_ROOT="$2" ;;
                --sha) SHA="$2" ;;
                --artifact) ARTIFACT="$2" ;;
                --artifact-sha256) ARTIFACT_SHA256="$2" ;;
                --deploy-env) DEPLOY_ENV="$2" ;;
                --env-file) NEW_ENV="$2" ;;
                --php-version) PHP_VERSION_ARG="$2" ;;
                --queue-mode) QUEUE_MODE_ARG="$2" ;;
                --keep) KEEP="$2" ;;
                --health-url) HEALTH_URL="$2" ;;
                --tenant-timeout) TENANT_TIMEOUT="$2" ;;
                --tenant-migrate-max-wait) TENANT_MAX_WAIT="$2" ;;
            esac
            shift 2
            ;;
        --allow-no-health-checks)
            ALLOW_NO_HEALTH_CHECKS=1
            shift
            ;;
        --skip-pre-migration-backup)
            SKIP_PRE_MIGRATION_BACKUP=1
            shift
            ;;
        --rollback)
            ROLLBACK_ONLY=1
            shift
            ;;
        --rehearsal)
            RELEASE_MODE="rehearsal"
            shift
            ;;
        -h | --help) usage ;;
        *) usage ;;
    esac
done

[[ -n "$APP_ROOT" ]] || usage
[[ "$APP_ROOT" == /* && "$APP_ROOT" != "/" ]] || die "--app-root must be an absolute path other than /"
APP_ROOT="${APP_ROOT%/}"
[[ "$PHP_VERSION_ARG" =~ ^[0-9]+\.[0-9]+$ ]] || die "--php-version must look like 8.5"
[[ "$QUEUE_MODE_ARG" == "horizon" || "$QUEUE_MODE_ARG" == "worker" ]] || die "--queue-mode must be horizon or worker"
[[ "$KEEP" =~ ^[0-9]+$ && "$KEEP" -ge 2 ]] || die "--keep must be a number >= 2"
[[ "$TENANT_TIMEOUT" =~ ^[0-9]+$ && "$TENANT_TIMEOUT" -ge 10 ]] || die "--tenant-timeout must be seconds >= 10"
[[ "$TENANT_MAX_WAIT" =~ ^[0-9]+$ && "$TENANT_MAX_WAIT" -ge 60 ]] || die "--tenant-migrate-max-wait must be seconds >= 60"
if [[ "$ROLLBACK_ONLY" -eq 0 ]]; then
    [[ "$SHA" =~ ^[0-9a-f]{7,40}$ ]] || die "--sha must be a lowercase hex git sha"
    [[ -n "$ARTIFACT" ]] || die "--artifact is required"
    [[ -n "$DEPLOY_ENV" ]] || die "--deploy-env is required (migrator credentials)"
    [[ -z "$ARTIFACT_SHA256" || "$ARTIFACT_SHA256" =~ ^[0-9a-f]{64}$ ]] || die "--artifact-sha256 must be 64 hex chars"
fi

PHP_BIN="${OPS_PHP_BIN:-php}"
# Health gate: every artisan call of this script runs with SROOR_HEALTH_DEPLOY_GATE=1,
# so `health:check` only runs the release-level checks (central DB, cache, Redis, disk,
# archive password) and never answers "warning" (App\Providers\HealthServiceProvider).
export SROOR_HEALTH_DEPLOY_GATE=1
HEALTH_DELAY="${OPS_HEALTH_DELAY:-3}"
HEALTH_ATTEMPTS="${OPS_HEALTH_ATTEMPTS:-10}"
RELEASES="$APP_ROOT/releases"
SHARED="$APP_ROOT/shared"
CURRENT="$APP_ROOT/current"
LOCK_DIR="$APP_ROOT/.deploy.lock"
PREVIOUS_FILE="$APP_ROOT/.previous_release"
HISTORY="$SHARED/deploy-history.log"

# ---- state used by the failure handler ---------------------------------------
LOCKED=0
WORK=""
RELEASE_ID=""
RELEASE_DIR=""
RELEASE_CREATED=0
ENV_REPLACED=0
SWITCHED=0
PREVIOUS_ID=""
STAGE="start"

current_release_id() {
    if [[ -L "$CURRENT" ]]; then
        basename "$(readlink "$CURRENT")"
    fi
}

record_history() {
    printf '%s %s %s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${RELEASE_ID:-rollback}" "${SHA:--}" "$1" >>"$HISTORY"
}

artisan() {
    local dir="$1"
    shift
    (cd "$dir" && "$PHP_BIN" artisan "$@" --no-interaction)
}

# Atomic switch: a new symlink is renamed over `current` (rename(2) is atomic).
# Returns non-zero instead of exiting: it is also called from the failure handler.
switch_to() {
    local id="$1"
    if [[ ! -d "$RELEASES/$id/backend" ]]; then
        warn "release not found: $id"
        return 1
    fi
    ln -sfn "releases/$id" "$APP_ROOT/current.tmp" || return 1
    mv -Tf "$APP_ROOT/current.tmp" "$CURRENT" || return 1
    info "current -> releases/$id"
}

reload_fpm() {
    # The only privileged command (sudoers-app.tmpl): flushes opcache.
    sudo -n /usr/bin/systemctl reload "php${PHP_VERSION_ARG}-fpm"
}

restart_queue() {
    local backend="$1" program status
    if [[ "$QUEUE_MODE_ARG" == "horizon" ]]; then
        # Explicit returns: rollback calls this under `if !`, where set -e is off.
        artisan "$backend" horizon:terminate || return 1
        program="sroor-horizon"
    else
        artisan "$backend" queue:restart || return 1
        program="sroor-worker:"
    fi
    # terminate/restart only stop running workers; supervisor brings them back. A program that is
    # not running at all (FATAL after provisioning, before the first release existed, or STOPPED)
    # is never restarted by supervisor, so jobs (tenant provisioning!) would queue forever.
    # Start it explicitly; `supervisorctl restart <program>` is the sudoers-allowed form.
    # supervisorctl status exits 3 when any program is not RUNNING: that is the case we handle.
    local rc=0
    status="$(sudo -n /usr/bin/supervisorctl status 2>/dev/null)" || rc=$?
    if [[ "$rc" -ne 0 && "$rc" -ne 3 ]]; then
        warn "supervisorctl status failed (exit $rc)"
        return 1
    fi
    if ! grep -E "^${program%:}[: ][^ ]*[[:space:]]+(RUNNING|STARTING)" <<<"$status" >/dev/null; then
        warn "queue program ${program%:} is not running: starting it"
        sudo -n /usr/bin/supervisorctl restart "$program" || return 1
    fi
}

# Health = spatie/laravel-health checks (`php artisan health:check`, checks
# registered by the app in PKG-1/OPS-7) + Laravel's built-in GET /up through
# nginx. No new HTTP route is added. The artisan part also runs BEFORE the
# switch, from the new release directory, as a smoke test.
# No set +e/-e toggling below: these also run inside the failure handler.
artisan_health() {
    local backend="$1" out rc=0
    out="$(artisan "$backend" health:check --no-notification --do-not-store-results --fail-command-on-failing-check 2>&1)" || rc=$?
    printf '%s\n' "$out" | sed 's/^/        health: /'
    if [[ "$rc" -ne 0 ]]; then
        warn "php artisan health:check failed (exit $rc)"
        return 1
    fi
    if ! grep -q 'Running check:' <<<"$out"; then
        if [[ "$ALLOW_NO_HEALTH_CHECKS" -eq 1 ]]; then
            warn "no spatie/laravel-health check is registered: the artisan health gate is a placeholder (--allow-no-health-checks)"
        else
            warn "no spatie/laravel-health check is registered (PKG-1/OPS-7); refusing to call the release healthy (override: --allow-no-health-checks)"
            return 1
        fi
    fi
}

health_check() {
    local backend="$1"
    artisan_health "$backend" || return 1
    if [[ "$HEALTH_URL" == "none" ]]; then
        warn "HTTP health check disabled (--health-url none)"
        return 0
    fi
    local host="${HEALTH_URL#https://}"
    host="${host%%/*}"
    host="${host%%:*}"
    local attempt
    for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
        # --resolve: hit this server's nginx, not whatever DNS says.
        if curl -fsS -o /dev/null --max-time 10 --resolve "$host:443:127.0.0.1" "$HEALTH_URL"; then
            info "GET $HEALTH_URL -> 2xx (attempt $attempt)"
            return 0
        fi
        sleep "$HEALTH_DELAY"
    done
    warn "GET $HEALTH_URL failed $HEALTH_ATTEMPTS times"
    return 1
}

restore_env() {
    if [[ "$ENV_REPLACED" -eq 1 && -f "$SHARED/.env.previous" ]]; then
        cp -p "$SHARED/.env.previous" "$SHARED/.env.restore"
        mv -f "$SHARED/.env.restore" "$SHARED/.env"
        info "restored the previous shared/.env"
    fi
}

release_lock() {
    if [[ "$LOCKED" -eq 1 ]]; then
        rm -rf "$LOCK_DIR"
        LOCKED=0
    fi
}

on_exit() {
    local rc=$?
    set +e
    trap - EXIT
    if [[ -n "$WORK" ]]; then
        rm -rf "$WORK"
    fi
    if [[ "$rc" -eq 0 ]]; then
        release_lock
        exit 0
    fi
    if [[ "$ROLLBACK_ONLY" -eq 1 || "$STAGE" == "start" ]]; then
        release_lock
        exit "$rc"
    fi

    warn "deploy FAILED at stage: $STAGE (exit $rc)"
    local final=1
    if [[ "$SWITCHED" -eq 1 ]]; then
        final=3
        if [[ -n "$PREVIOUS_ID" && -d "$RELEASES/$PREVIOUS_ID/backend" ]]; then
            warn "automatic rollback to $PREVIOUS_ID"
            restore_env
            if ! switch_to "$PREVIOUS_ID"; then final=4; fi
            if ! reload_fpm; then final=4; fi
            if ! restart_queue "$RELEASES/$PREVIOUS_ID/backend"; then final=4; fi
            if ! health_check "$RELEASES/$PREVIOUS_ID/backend"; then
                warn "the previous release is not healthy either"
                final=4
            fi
        else
            warn "no previous release to roll back to: removing the broken current link"
            rm -f "$CURRENT"
            final=4
        fi
    else
        restore_env
    fi
    if [[ "$RELEASE_CREATED" -eq 1 && -n "$RELEASE_DIR" ]]; then
        if [[ "$(current_release_id)" != "$RELEASE_ID" ]]; then
            rm -rf "$RELEASE_DIR"
            info "removed the failed release $RELEASE_ID"
        fi
    fi
    if [[ "$final" -eq 3 ]]; then
        record_history "rolled-back:$STAGE"
        warn "rolled back to $PREVIOUS_ID. Migrations were NOT reversed."
    elif [[ "$final" -eq 4 ]]; then
        record_history "ROLLBACK-FAILED:$STAGE"
        warn "MANUAL ACTION NEEDED: see docs/07-operations/deploy-runbook.md §2"
    else
        record_history "failed:$STAGE"
        warn "nothing was switched: users still run $(current_release_id)"
    fi
    release_lock
    exit "$final"
}
trap on_exit EXIT

# ---- lock --------------------------------------------------------------------
for dir in "$RELEASES" "$SHARED" "$SHARED/storage"; do
    [[ -d "$dir" ]] || die "missing $dir (run scripts/ops/steps/90-app-layout.sh first)"
done
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    die "another deploy is running ($LOCK_DIR exists). If no deploy is running, remove it and retry"
fi
LOCKED=1
WORK="$(mktemp -d)"
chmod 700 "$WORK"

# ---- manual rollback -----------------------------------------------------------
if [[ "$ROLLBACK_ONLY" -eq 1 ]]; then
    [[ -s "$PREVIOUS_FILE" ]] || die "no previous release recorded in $PREVIOUS_FILE"
    target="$(tr -d '[:space:]' <"$PREVIOUS_FILE")"
    [[ "$target" =~ ^[0-9A-Za-z._-]+$ && -d "$RELEASES/$target/backend" ]] || die "recorded previous release is missing: $target"
    from="$(current_release_id)"
    if [[ -z "$HEALTH_URL" ]]; then
        declare -A RB_ENV=()
        parse_env_file "$SHARED/.env" RB_ENV || die "cannot parse $SHARED/.env"
        HEALTH_URL="${RB_ENV[APP_URL]%/}/up"
    fi
    [[ "$HEALTH_URL" == "none" || "$HEALTH_URL" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?(/.*)?$ ]] || die "--health-url must be https://host/path or none"
    info "manual rollback: ${from:-none} -> $target"
    switch_to "$target"
    reload_fpm
    restart_queue "$RELEASES/$target/backend"
    health_check "$RELEASES/$target/backend" || die "rolled back to $target but it is NOT healthy"
    if [[ -n "$from" ]]; then
        printf '%s\n' "$from" >"$PREVIOUS_FILE"
    fi
    RELEASE_ID="$target"
    record_history "manual-rollback-from:${from:-none}"
    info "rollback done (run again to switch back to ${from:-none})"
    exit 0
fi

# ---- preflight -----------------------------------------------------------------
STAGE="preflight"
[[ -f "$ARTIFACT" ]] || die "artifact not found: $ARTIFACT"
if [[ -n "$ARTIFACT_SHA256" ]]; then
    actual_sha="$(sha256sum "$ARTIFACT" | awk '{print $1}')"
    [[ "$actual_sha" == "$ARTIFACT_SHA256" ]] || die "artifact checksum mismatch"
fi
tar -tzf "$ARTIFACT" >"$WORK/members"
grep -qxE '(\./)?backend/artisan' "$WORK/members" || die "artifact has no backend/artisan"
if grep -qE '(^/|(^|/)\.\.(/|$))' "$WORK/members"; then
    die "artifact contains absolute or ../ paths"
fi
if grep -qE '(^|/)backend/\.env$' "$WORK/members"; then
    die "artifact contains backend/.env (secrets must never be packaged)"
fi

CANDIDATE_ENV="$SHARED/.env"
if [[ -n "$NEW_ENV" ]]; then
    [[ -f "$NEW_ENV" ]] || die "env file not found: $NEW_ENV"
    chmod 600 "$NEW_ENV"
    CANDIDATE_ENV="$NEW_ENV"
fi
[[ -s "$CANDIDATE_ENV" ]] || die "no production .env: pass --env-file or fill $SHARED/.env"
info "preflight: production standards of the .env (mode: $RELEASE_MODE, values are never printed)"
bash "$SCRIPT_DIR/check-env.sh" "$CANDIDATE_ENV" --mode "$RELEASE_MODE" || die "the .env violates the $RELEASE_MODE production standards: release refused"

declare -A ENVV=()
declare -A DEPV=()
parse_env_file "$CANDIDATE_ENV" ENVV || die "cannot parse the candidate .env"
[[ -f "$DEPLOY_ENV" ]] || die "deploy env not found: $DEPLOY_ENV"
chmod 600 "$DEPLOY_ENV"
parse_env_file "$DEPLOY_ENV" DEPV || die "cannot parse the deploy env"

# [CTO-2026-11-11] Rehearsal: name every relaxation once, loudly (names only, never values).
if [[ "$RELEASE_MODE" == "rehearsal" ]]; then
    mailer_v="${ENVV[MAIL_MAILER]:-}"
    [[ "${mailer_v,,}" != "log" ]] || REHEARSAL_NOTES+=("MAIL_MAILER=log (no mail leaves the server)")
    disks_v="${ENVV[BACKUP_TENANT_DISKS]:-${ENVV[BACKUP_DISKS]:-local}}"
    disks_v=",${disks_v// /},"
    [[ "$disks_v" == *",google,"* ]] || REHEARSAL_NOTES+=("BACKUP_DISKS=local (backups stay on this server, not on Google Drive)")
    for key in TELEGRAM_BOT_TOKEN TELEGRAM_CHAT_ID BACKUP_NOTIFICATION_EMAIL; do
        [[ -n "${ENVV[$key]:-}" ]] || REHEARSAL_NOTES+=("$key empty")
    done
    warn "REHEARSAL MODE: this release runs with RELEASE_REHEARSAL=true - NOT production-ready"
    for note in "${REHEARSAL_NOTES[@]}"; do
        warn "REHEARSAL relaxed: $note"
    done
fi

for key in DB_DATABASE DB_USERNAME DB_PASSWORD DB_AUDIT_PRUNER_USERNAME DB_AUDIT_PRUNER_PASSWORD; do
    [[ -n "${ENVV[$key]:-}" ]] || die "$key missing in the .env"
done
for key in DB_MIGRATOR_USERNAME DB_MIGRATOR_PASSWORD; do
    [[ -n "${DEPV[$key]:-}" ]] || die "$key missing in the deploy env"
done
DB_HOST_V="${ENVV[DB_HOST]:-localhost}"
DB_PORT_V="${ENVV[DB_PORT]:-3306}"
write_mysql_client_file "$WORK/migrator.cnf" "${DEPV[DB_MIGRATOR_USERNAME]}" "${DEPV[DB_MIGRATOR_PASSWORD]}" "$DB_HOST_V" "$DB_PORT_V" migrator
write_mysql_client_file "$WORK/app.cnf" "${ENVV[DB_USERNAME]}" "${ENVV[DB_PASSWORD]}" "$DB_HOST_V" "$DB_PORT_V" app
write_mysql_client_file "$WORK/pruner.cnf" "${ENVV[DB_AUDIT_PRUNER_USERNAME]}" "${ENVV[DB_AUDIT_PRUNER_PASSWORD]}" "$DB_HOST_V" "$DB_PORT_V" audit_pruner

if [[ -z "$HEALTH_URL" ]]; then
    HEALTH_URL="${ENVV[APP_URL]%/}/up"
fi
[[ "$HEALTH_URL" == "none" || "$HEALTH_URL" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?(/.*)?$ ]] || die "--health-url must be https://host/path or none"

PREVIOUS_ID="$(current_release_id)"
RELEASE_ID="$(date -u +%Y%m%d%H%M%S)-${SHA:0:12}"
RELEASE_DIR="$RELEASES/$RELEASE_ID"
[[ ! -e "$RELEASE_DIR" ]] || die "release directory already exists: $RELEASE_ID"
info "deploying ${SHA:0:12} as $RELEASE_ID (previous: ${PREVIOUS_ID:-none})"

# ---- build the release directory -------------------------------------------------
STAGE="extract"
mkdir "$RELEASE_DIR"
RELEASE_CREATED=1
tar -xzf "$ARTIFACT" -C "$RELEASE_DIR" --no-same-owner
BACKEND="$RELEASE_DIR/backend"
[[ -f "$BACKEND/artisan" ]] || die "backend/artisan missing after extraction"
# Persistent state lives in shared/; build-time caches never ship.
rm -rf "$BACKEND/storage"
rm -f "$BACKEND/.env" "$BACKEND/bootstrap/cache/config.php" "$BACKEND/bootstrap/cache/events.php" "$BACKEND"/bootstrap/cache/routes-*.php
mkdir -p "$BACKEND/bootstrap/cache"

STAGE="env"
if [[ -n "$NEW_ENV" ]]; then
    if [[ -s "$SHARED/.env" ]]; then
        cp -p "$SHARED/.env" "$SHARED/.env.previous"
        chmod 600 "$SHARED/.env.previous"
    fi
    ENV_REPLACED=1
    cp "$NEW_ENV" "$SHARED/.env.next"
    chmod 600 "$SHARED/.env.next"
    mv -f "$SHARED/.env.next" "$SHARED/.env"
    info "shared/.env updated from the release workflow (mode 600)"
fi
ln -sfn "$SHARED/.env" "$BACKEND/.env"
ln -sfn "$SHARED/storage" "$BACKEND/storage"

STAGE="storage-link"
artisan "$BACKEND" storage:link --force

# Restore point before any schema change (OPS-5). Runs from the NEW release (config is
# not cached yet, it reads shared/.env) with the read-only backup account. Any failing
# database fails the release: users stay on the previous release, nothing migrated.
STAGE="pre-migration-backup"
if [[ "$SKIP_PRE_MIGRATION_BACKUP" -eq 1 ]]; then
    warn "pre-migration backup SKIPPED (--skip-pre-migration-backup): this release has no restore point"
    record_history "pre-migration-backup-skipped"
elif [[ -z "$PREVIOUS_ID" ]]; then
    info "first release on this server: no pre-migration backup (run backup:tenants once after it)"
else
    info "pre-migration backup: php artisan backup:tenants (central + every tenant DB, encrypted, verified)"
    artisan "$BACKEND" backup:tenants --no-cleanup
fi

STAGE="migrate-central"
info "php artisan migrate --force (central, as the migrator account)"
(
    cd "$BACKEND"
    # Process env wins over .env (immutable dotenv); config is not cached yet.
    DB_USERNAME="${DEPV[DB_MIGRATOR_USERNAME]}" DB_PASSWORD="${DEPV[DB_MIGRATOR_PASSWORD]}" \
        "$PHP_BIN" artisan migrate --force --no-interaction
)

STAGE="central-grants"
info "per-table central grants (append-only audit IDEN-1.15 + ledgers ENTI-1.10)"
CENTRAL_DB_NAME="${ENVV[DB_DATABASE]}" \
    MYSQL_APP_USER="${ENVV[DB_USERNAME]}" \
    MYSQL_AUDIT_PRUNER_USER="${ENVV[DB_AUDIT_PRUNER_USERNAME]}" \
    MYSQL_USER_HOST="${DEPV[MYSQL_USER_HOST]:-localhost}" \
    CENTRAL_AUDIT_TABLES="${DEPV[CENTRAL_AUDIT_TABLES]:-central_audit_logs activity_log}" \
    CENTRAL_APPEND_ONLY_TABLES="${DEPV[CENTRAL_APPEND_ONLY_TABLES]:-tenant_credit_ledger}" \
    bash "$SCRIPT_DIR/sync-central-grants.sh" --client-file "$WORK/migrator.cnf" \
    --verify-app "$WORK/app.cnf" --verify-pruner "$WORK/pruner.cnf"

# The CentralPermission enum is the only source of operator roles/permissions (super_admin,
# support). Re-sync them every release (idempotent; prunes central-guard permissions that left
# the enum) so a new console permission reaches production without a manual seed. Runs as the
# app account, after the grants, on the central connection only (the seeder refuses tenancy).
STAGE="central-permissions"
info "php artisan db:seed --class=CentralPermissionsSeeder --force (operator roles, idempotent)"
artisan "$BACKEND" db:seed --class=CentralPermissionsSeeder --force

# The approved SaaS catalog (plans, features, add-ons) lives in code. PlansAndFeaturesSeeder only
# creates what is missing and merges new feature keys; it never overwrites a price, limit, flag or
# name a super-admin edited. Running it every release ships new catalog entries without a manual seed.
STAGE="plan-catalog"
info "php artisan db:seed --class=PlansAndFeaturesSeeder --force (missing catalog entries only)"
artisan "$BACKEND" db:seed --class=PlansAndFeaturesSeeder --force

STAGE="migrate-tenants"
# OPS-25: the tenant migrations run DETACHED (setsid + nohup) so a dropped SSH session or the
# workflow timeout can never kill artisan in the middle of an ALTER. Progress is a JSON file,
# the exit code of the run lands in a separate file. A re-run of the deploy ATTACHES to a run
# that is still going instead of starting a second one. Old code keeps serving on the migrated
# schema (expand/contract), so a failed tenant stops the release BEFORE the switch.
TM_STATUS="$SHARED/tenant-migrate-status.json"
TM_RC="$SHARED/tenant-migrate.rc"
TM_PID="$SHARED/tenant-migrate.pid"
TM_LOG="$SHARED/tenant-migrate.log"
tm_pid_alive() {
    [[ -s "$TM_PID" ]] && kill -0 "$(cat "$TM_PID")" 2>/dev/null
}
if tm_pid_alive; then
    info "tenant migration run $(cat "$TM_PID") is still going (previous deploy): attaching to it"
else
    info "php artisan tenants:migrate-all (detached; per-tenant timeout ${TENANT_TIMEOUT}s; status: $TM_STATUS)"
    rm -f "$TM_RC" "$TM_STATUS"
    (
        cd "$BACKEND"
        # shellcheck disable=SC2016
        setsid nohup bash -c '"$1" artisan tenants:migrate-all --force --no-interaction --timeout="$2" --status-file="$3" >"$4" 2>&1; echo $? >"$5"' \
            _ "$PHP_BIN" "$TENANT_TIMEOUT" "$TM_STATUS" "$TM_LOG" "$TM_RC" </dev/null >/dev/null 2>&1 &
        echo $! >"$TM_PID"
    )
fi
tm_started=$SECONDS
tm_last=""
while [[ ! -s "$TM_RC" ]]; do
    if ! tm_pid_alive && [[ ! -s "$TM_RC" ]]; then
        sleep 2
        [[ -s "$TM_RC" ]] && break
        die "tenant migration process ended without an exit code (killed?). Re-run the deploy: migrations are idempotent. Log: $TM_LOG"
    fi
    if ((SECONDS - tm_started > TENANT_MAX_WAIT)); then
        # Keep the release directory: the detached run still executes from it.
        RELEASE_CREATED=0
        warn "tenant migrations still running after ${TENANT_MAX_WAIT}s: NOT killed. Re-run the deploy later (it attaches). Status: $TM_STATUS"
        die "tenant migrations exceeded --tenant-migrate-max-wait"
    fi
    if [[ -s "$TM_STATUS" ]]; then
        tm_now="$(tr -d '\n ' <"$TM_STATUS" | grep -o '"done":[0-9]*,"counts"' || true)"
        if [[ "$tm_now" != "$tm_last" ]]; then
            tm_last="$tm_now"
            info "tenant migrations progress: ${tm_now%,\"counts\"} (elapsed $((SECONDS - tm_started))s)"
        fi
    fi
    sleep 5
done
TM_EXIT="$(cat "$TM_RC")"
info "tenant migrations finished in $((SECONDS - tm_started))s (exit $TM_EXIT). Report:"
sed 's/^/        /' "$TM_LOG" >&2 || true
rm -f "$TM_PID"
[[ "$TM_EXIT" == "0" ]] || die "tenants:migrate-all reported failed/timed-out tenants (see the report above). The other tenants ARE migrated; the new release was NOT activated. Fix the tenant(s) and re-run the deploy"

# APP-3 (CTO Q9) / R1: release binaries are COPIED from the web-served public disk to the
# private release disk (same relative path, SHA-256 verified, no DB change). The public
# copy stays: the previous release (still serving, and the rollback target) only reads it.
# Idempotent. A conflict or a failed copy is a failure (users stay on the previous release).
STAGE="relocate-app-releases"
info "php artisan app-releases:relocate (copy APK/EXE binaries to the private disk)"
artisan "$BACKEND" app-releases:relocate

STAGE="caches"
artisan "$BACKEND" config:cache
chmod 600 "$BACKEND/bootstrap/cache/config.php"
artisan "$BACKEND" route:cache
artisan "$BACKEND" view:cache
artisan "$BACKEND" event:cache

STAGE="smoke"
info "pre-switch smoke test: health:check from the new release (users still on ${PREVIOUS_ID:-nothing})"
artisan_health "$BACKEND"

# ---- switch ---------------------------------------------------------------------
STAGE="switch"
SWITCHED=1
switch_to "$RELEASE_ID"
reload_fpm

STAGE="queue-restart"
restart_queue "$CURRENT/backend"

STAGE="health"
health_check "$CURRENT/backend"

# R1: the new release is live and healthy: delete the public copies whose private copy has
# the same SHA-256. NON-FATAL: a failure here never rolls back a healthy release; the
# public copies simply stay until the next deploy retries (logged for the operator).
STAGE="prune-legacy-releases"
info "php artisan app-releases:relocate --prune-legacy (verified public copies only)"
if ! artisan "$CURRENT/backend" app-releases:relocate --prune-legacy; then
    warn "app-releases:relocate --prune-legacy failed (non-fatal): public release copies kept, the next deploy retries"
fi

# ---- success ----------------------------------------------------------------------
STAGE="cleanup"
if [[ -n "$PREVIOUS_ID" ]]; then
    printf '%s\n' "$PREVIOUS_ID" >"$PREVIOUS_FILE"
fi
mapfile -t all_releases < <(find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | LC_ALL=C sort)
excess=$((${#all_releases[@]} - KEEP))
for old in "${all_releases[@]}"; do
    [[ "$excess" -gt 0 ]] || break
    if [[ "$old" == "$RELEASE_ID" || "$old" == "$PREVIOUS_ID" ]]; then
        continue
    fi
    rm -rf "${RELEASES:?}/$old"
    info "pruned old release $old"
    excess=$((excess - 1))
done

if [[ "$RELEASE_MODE" == "rehearsal" ]]; then
    record_history "deployed-rehearsal"
else
    record_history "deployed"
fi
info "release $RELEASE_ID is live (rollback: bash $SCRIPT_DIR/deploy.sh --app-root $APP_ROOT --rollback)"
if [[ "$RELEASE_MODE" == "rehearsal" ]]; then
    warn "REHEARSAL MODE: release $RELEASE_ID is live with RELEASE_REHEARSAL=true. Before the first customer: add the waived secrets/vars, delete RELEASE_REHEARSAL in GitHub and release again (docs/07-operations/deploy-runbook.md §7)"
    for note in "${REHEARSAL_NOTES[@]}"; do
        warn "REHEARSAL relaxed: $note"
    done
fi
