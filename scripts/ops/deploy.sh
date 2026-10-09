#!/usr/bin/env bash
# Zero-downtime release on the VPS (OPS-3). Runs ON THE SERVER as the app user
# (APP_USER, e.g. `sroor`), called by .github/workflows/release.yml over SSH,
# or by hand during the rehearsal (docs/07-operations/deploy-runbook.md).
#
#   deploy:   bash deploy.sh --app-root /var/www/sroor --sha <git sha> \
#                 --artifact release.tar.gz [--artifact-sha256 HEX] \
#                 --deploy-env deploy.env [--env-file production.env] \
#                 [--php-version 8.4] [--queue-mode horizon|worker] [--keep 5] \
#                 [--health-url https://host/up|none] [--allow-no-health-checks]
#   rollback: bash deploy.sh --app-root /var/www/sroor --rollback \
#                 [--php-version 8.4] [--queue-mode horizon|worker] [--health-url URL|none]
#
# Order (every step must succeed; no failure is ever ignored):
#   preflight (layout, artifact, check-env.sh on the candidate .env: TELESCOPE_ENABLED=false,
#   BACKUP_ARCHIVE_PASSWORD set, SMTP...) -> extract releases/<id> -> shared .env + storage
#   -> storage:link --force -> migrate --force (central, as the migrator account)
#   -> per-table central grants (append-only audit) -> tenants:migrate --force
#   -> config/route/view/event cache -> smoke health:check from the new release
#   -> atomic `current` switch -> php-fpm reload -> horizon:terminate (or
#   queue:restart) -> health:check + GET /up -> prune old releases
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
# Test hooks (scripts/ops/tests/deploy-test.sh): OPS_PHP_BIN, OPS_HEALTH_DELAY,
# OPS_HEALTH_ATTEMPTS. Nothing else changes behaviour.

set -euo pipefail

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
PHP_VERSION_ARG="8.4"
QUEUE_MODE_ARG="horizon"
KEEP=5
HEALTH_URL=""
ALLOW_NO_HEALTH_CHECKS=0
ROLLBACK_ONLY=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root | --sha | --artifact | --artifact-sha256 | --deploy-env | --env-file | --php-version | --queue-mode | --keep | --health-url)
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
            esac
            shift 2
            ;;
        --allow-no-health-checks)
            ALLOW_NO_HEALTH_CHECKS=1
            shift
            ;;
        --rollback)
            ROLLBACK_ONLY=1
            shift
            ;;
        -h | --help) usage ;;
        *) usage ;;
    esac
done

[[ -n "$APP_ROOT" ]] || usage
[[ "$APP_ROOT" == /* && "$APP_ROOT" != "/" ]] || die "--app-root must be an absolute path other than /"
APP_ROOT="${APP_ROOT%/}"
[[ "$PHP_VERSION_ARG" =~ ^[0-9]+\.[0-9]+$ ]] || die "--php-version must look like 8.4"
[[ "$QUEUE_MODE_ARG" == "horizon" || "$QUEUE_MODE_ARG" == "worker" ]] || die "--queue-mode must be horizon or worker"
[[ "$KEEP" =~ ^[0-9]+$ && "$KEEP" -ge 2 ]] || die "--keep must be a number >= 2"
if [[ "$ROLLBACK_ONLY" -eq 0 ]]; then
    [[ "$SHA" =~ ^[0-9a-f]{7,40}$ ]] || die "--sha must be a lowercase hex git sha"
    [[ -n "$ARTIFACT" ]] || die "--artifact is required"
    [[ -n "$DEPLOY_ENV" ]] || die "--deploy-env is required (migrator credentials)"
    [[ -z "$ARTIFACT_SHA256" || "$ARTIFACT_SHA256" =~ ^[0-9a-f]{64}$ ]] || die "--artifact-sha256 must be 64 hex chars"
fi

PHP_BIN="${OPS_PHP_BIN:-php}"
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
    local backend="$1"
    if [[ "$QUEUE_MODE_ARG" == "horizon" ]]; then
        artisan "$backend" horizon:terminate
    else
        artisan "$backend" queue:restart
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
info "preflight: production standards of the .env (values are never printed)"
bash "$SCRIPT_DIR/check-env.sh" "$CANDIDATE_ENV" || die "the .env violates the production standards: release refused"

declare -A ENVV=()
declare -A DEPV=()
parse_env_file "$CANDIDATE_ENV" ENVV || die "cannot parse the candidate .env"
[[ -f "$DEPLOY_ENV" ]] || die "deploy env not found: $DEPLOY_ENV"
chmod 600 "$DEPLOY_ENV"
parse_env_file "$DEPLOY_ENV" DEPV || die "cannot parse the deploy env"

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

STAGE="migrate-tenants"
info "php artisan tenants:migrate --force (any failing tenant stops the release)"
artisan "$BACKEND" tenants:migrate --force

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

record_history "deployed"
info "release $RELEASE_ID is live (rollback: bash $SCRIPT_DIR/deploy.sh --app-root $APP_ROOT --rollback)"
