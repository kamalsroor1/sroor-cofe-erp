#!/usr/bin/env bash
# Offline tests for scripts/ops/deploy.sh (OPS-3) and the deploy side of
# scripts/ops/sync-central-grants.sh.
#
# Every run happens in a throwaway APP_ROOT under mktemp, with stub `php`,
# `mysql`, `sudo` and `curl` first on PATH, so no server, database, network or
# root is touched. The stubs record what deploy.sh asked for (command order,
# working directory, which DB account) - never a password.
#
#   bash scripts/ops/tests/deploy-test.sh
#
# Covers: first deploy, second deploy, automatic rollback (health check after
# the switch fails), failures before the switch (central migrate, tenants
# migrate, smoke health, grants drift), preflight refusals (TELESCOPE_ENABLED,
# BACKUP_ARCHIVE_PASSWORD, SMTP, packaged .env, checksum), the deploy lock,
# manual rollback, pruning old releases without touching shared/, and that no
# secret ever reaches the output.

set -euo pipefail

TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OPS_DIR="$(cd "$TESTS_DIR/.." && pwd)"
DEPLOY="$OPS_DIR/deploy.sh"

# Git Bash on Windows: real symlinks (deploy.sh relies on them) and no path
# rewriting. Both are no-ops on Linux.
export MSYS="winsymlinks:nativestrict"
export MSYS2_ENV_CONV_EXCL='*'
export MSYS_NO_PATHCONV=1

PASS=0
FAIL=0
FAILED_NAMES=()
pass() {
    PASS=$((PASS + 1))
    printf '  ok    %s\n' "$1"
}
fail() {
    FAIL=$((FAIL + 1))
    FAILED_NAMES+=("$1")
    printf '  FAIL  %s\n' "$1"
    if [[ -n "${2:-}" ]]; then
        printf '%s\n' "$2" | head -25 | sed 's/^/        /'
    fi
}
check() {
    local name="$1"
    shift
    if "$@"; then pass "$name"; else fail "$name" "${DETAIL:-}"; fi
    DETAIL=""
}
DETAIL=""

WORK="$(mktemp -d)"
cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

# Clearly fake fixture secrets (gitleaks ignores the `Fixture` prefix).
APP_PW="FixtureAppPasswordOnly000000000000"
MIGRATOR_PW="FixtureMigratorPasswordOnly0000000"
PRUNER_PW="FixturePrunerPasswordOnly000000000"
OTHER_SECRET="fixture-secret-value-must-never-be-printed"
SECRETS=("$APP_PW" "$MIGRATOR_PW" "$PRUNER_PW" "$OTHER_SECRET")

# ---- stubs -----------------------------------------------------------------------
STUBS="$WORK/stubs"
mkdir -p "$STUBS"

cat >"$STUBS/php" <<'EOF'
#!/usr/bin/env bash
# Stub php: only `php artisan <command> ...` is expected.
set -euo pipefail
[[ "${1:-}" == "artisan" ]] || { echo "stub php: unexpected call: $*" >&2; exit 97; }
cmd="${2:-}"
where="$(basename "$(dirname "$PWD")")"
[[ "$PWD" == */current/backend ]] && where="current"
printf 'php %s @%s dbuser=%s\n' "$cmd" "$where" "${DB_USERNAME:-}" >>"$FAKE_LOG"
for f in ${FAKE_FAIL:-}; do
    if [[ "$f" == "$cmd" ]]; then echo "stub: $cmd failed" >&2; exit 1; fi
done
case "$cmd" in
    config:cache) mkdir -p bootstrap/cache && echo '<?php return ["stub" => true];' >bootstrap/cache/config.php ;;
    health:check)
        echo "Running checks..."
        if [[ -n "${FAKE_HEALTH_NONE:-}" ]]; then echo "All done!"; exit 0; fi
        echo "Running check: Database..."
        if [[ -n "${FAKE_HEALTH_FAIL_CURRENT:-}" && "$where" == "current" ]]; then echo "Failed: stub"; exit 1; fi
        echo "Ok"
        echo "All done!"
        ;;
esac
exit 0
EOF

cat >"$STUBS/mysql" <<'EOF'
#!/usr/bin/env bash
# Stub mysql: answers the table list and SHOW GRANTS from fixtures, records
# applied SQL. Logs argv (which must never contain a password).
set -euo pipefail
printf 'mysql %s\n' "$*" >>"$FAKE_LOG"
user=""
sql=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --defaults-extra-file=*) user="$(sed -n 's/^user=//p' "${1#*=}")" ;;
        -e) sql="$2"; shift ;;
    esac
    shift
done
if [[ -z "$sql" ]]; then
    cat >>"$FAKE_SQL"
    exit 0
fi
case "$sql" in
    "SELECT TABLE_NAME"*) cat "$FAKE_TABLES" ;;
    "SHOW GRANTS") cat "$FAKE_GRANTS_DIR/$user" ;;
    *) echo "stub mysql: unexpected SQL" >&2; exit 98 ;;
esac
EOF

cat >"$STUBS/sudo" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
printf 'sudo %s\n' "$*" >>"$FAKE_LOG"
exit "${FAKE_SUDO_RC:-0}"
EOF

cat >"$STUBS/curl" <<'EOF'
#!/usr/bin/env bash
# Fails the first FAKE_CURL_FAIL_FIRST calls (counter file), then succeeds.
set -euo pipefail
printf 'curl %s\n' "$*" >>"$FAKE_LOG"
n=0
[[ -f "$FAKE_CURL_COUNT" ]] && n="$(cat "$FAKE_CURL_COUNT")"
n=$((n + 1))
echo "$n" >"$FAKE_CURL_COUNT"
if [[ "$n" -le "${FAKE_CURL_FAIL_FIRST:-0}" ]]; then exit 22; fi
exit 0
EOF
chmod +x "$STUBS"/*
export PATH="$STUBS:$PATH"
export OPS_HEALTH_DELAY=0
export OPS_HEALTH_ATTEMPTS=2

# ---- fixtures ----------------------------------------------------------------------
write_env() {
    local file="$1" marker="${2:-one}"
    cat >"$file" <<EOF
APP_NAME="Retail ERP $marker"
APP_ENV=production
APP_KEY=base64:${OTHER_SECRET}
APP_DEBUG=false
APP_URL=https://example.test
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=sroor_central
DB_USERNAME=sroor_app
DB_PASSWORD=${APP_PW}
DB_AUDIT_PRUNER_USERNAME=sroor_audit_pruner
DB_AUDIT_PRUNER_PASSWORD=${PRUNER_PW}
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_PASSWORD=${OTHER_SECRET}
TELESCOPE_ENABLED=false
QUICK_LOGIN_ENABLED=false
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.test
MAIL_PORT=587
MAIL_USERNAME=fixture-mail-user
MAIL_PASSWORD=${OTHER_SECRET}
MAIL_FROM_ADDRESS=noreply@example.test
BACKUP_ARCHIVE_PASSWORD=${OTHER_SECRET}
SENTRY_LARAVEL_DSN=
SENTRY_SEND_DEFAULT_PII=false
EOF
}

write_deploy_env() {
    cat >"$1" <<EOF
DB_MIGRATOR_USERNAME=sroor_migrator
DB_MIGRATOR_PASSWORD=${MIGRATOR_PW}
MYSQL_USER_HOST=localhost
CENTRAL_AUDIT_TABLES="central_audit_logs activity_log"
EOF
}

# make_artifact NAME [extra-file-in-backend...] -> $WORK/NAME.tar.gz
make_artifact() {
    local name="$1"
    shift
    local src="$WORK/src-$name"
    rm -rf "$src"
    mkdir -p "$src/backend/public" "$src/backend/storage/logs" "$src/backend/bootstrap/cache" "$src/scripts/ops"
    printf '<?php // stub artisan\n' >"$src/backend/artisan"
    printf '<?php // %s\n' "$name" >"$src/backend/public/index.php"
    printf 'stale build-time cache\n' >"$src/backend/bootstrap/cache/config.php"
    printf 'must not survive\n' >"$src/backend/storage/logs/from-artifact.log"
    local extra
    for extra in "$@"; do
        printf 'x\n' >"$src/backend/$extra"
    done
    tar -czf "$WORK/$name.tar.gz" -C "$src" backend scripts
}

# Central tables + per-account grants exactly as the contract wants them.
GRANTS_GOOD="$WORK/grants-good"
GRANTS_DRIFT="$WORK/grants-drift"
TABLES="$WORK/tables"
printf '%s\n' activity_log central_audit_logs domains migrations tenants >"$TABLES"
(
    # shellcheck source=../lib/common.sh
    source "$OPS_DIR/lib/common.sh"
    # shellcheck source=../lib/mysql-grants.sh
    source "$OPS_DIR/lib/mysql-grants.sh"
    apply_defaults
    mkdir -p "$GRANTS_GOOD" "$GRANTS_DRIFT"
    for user in sroor_app sroor_audit_pruner; do
        {
            expected_grants_all | grep -F "TO \`$user\`@"
            expected_table_grants <"$TABLES" | { grep -F "TO \`$user\`@" || [[ $? -eq 1 ]]; }
        } >"$GRANTS_GOOD/$user"
        cp "$GRANTS_GOOD/$user" "$GRANTS_DRIFT/$user"
    done
    # Drift: someone granted UPDATE on the audit table to the app account.
    printf 'GRANT UPDATE ON `sroor_central`.`central_audit_logs` TO `sroor_app`@`localhost`\n' >>"$GRANTS_DRIFT/sroor_app"
)

export FAKE_TABLES="$TABLES"

# new_root -> fresh APP_ROOT (layout of steps/90-app-layout.sh) in $ROOT
new_root() {
    ROOT="$WORK/root-$1"
    rm -rf "$ROOT"
    mkdir -p "$ROOT/releases" "$ROOT/shared/storage/app/public" "$ROOT/shared/storage/logs"
    : >"$ROOT/shared/.env"
    printf 'keep me\n' >"$ROOT/shared/storage/app/public/sentinel.txt"
    export FAKE_LOG="$ROOT/calls.log"
    export FAKE_SQL="$ROOT/applied.sql"
    export FAKE_CURL_COUNT="$ROOT/curl.count"
    export FAKE_GRANTS_DIR="$GRANTS_GOOD"
    : >"$FAKE_LOG"
}

# run_deploy SHA ARTIFACT [env-file|-] [extra args...] -> RC, OUT; resets per-run state
run_deploy() {
    local sha="$1" artifact="$2" env="${3:--}"
    shift 3
    local dep="$WORK/deploy.env"
    write_deploy_env "$dep"
    local args=(--app-root "$ROOT" --sha "$sha" --artifact "$artifact" --deploy-env "$dep" --php-version 8.4)
    if [[ "$env" != "-" ]]; then args+=(--env-file "$env"); fi
    : >"$FAKE_LOG"
    rm -f "$FAKE_CURL_COUNT"
    RC=0
    OUT="$(bash "$DEPLOY" "${args[@]}" "$@" 2>&1)" || RC=$?
    ALL_OUT+="$OUT"$'\n'"$(cat "$FAKE_LOG")"$'\n'
}
ALL_OUT=""

current_id() { basename "$(readlink "$ROOT/current")"; }
release_count() { find "$ROOT/releases" -mindepth 1 -maxdepth 1 -type d | wc -l | tr -d ' '; }
log_has() { grep -qF -- "$1" "$FAKE_LOG"; }
# line number of the first log line containing $1 (0 if absent)
log_line() {
    local n
    n="$(grep -nF -- "$1" "$FAKE_LOG" | head -1 | cut -d: -f1)"
    printf '%s' "${n:-0}"
}
in_order() {
    local prev=0 item n
    for item in "$@"; do
        n="$(log_line "$item")"
        if [[ "$n" -eq 0 || "$n" -le "$prev" ]]; then
            DETAIL="out of order or missing: $item"$'\n'"$(cat "$FAKE_LOG")"
            return 1
        fi
        prev="$n"
    done
}

make_artifact good1
make_artifact good2
make_artifact good3
make_artifact withenv .env
write_env "$WORK/env-one" one
write_env "$WORK/env-two" two

SHA1=1111111111111111111111111111111111111111
SHA2=2222222222222222222222222222222222222222
SHA3=3333333333333333333333333333333333333333

# ------------------------------------------------------------------------------------
echo "1. First deploy"
# ------------------------------------------------------------------------------------
new_root main
run_deploy "$SHA1" "$WORK/good1.tar.gz" "$WORK/env-one"
DETAIL="$OUT"
check "first deploy exits 0" test "$RC" -eq 0
ID1="$(current_id)"
check "current -> releases/<timestamp>-<sha12>" bash -c "[[ '$ID1' =~ ^[0-9]{14}-111111111111$ ]]"
check "release has the artifact code" grep -q good1 "$ROOT/releases/$ID1/backend/public/index.php"
check "backend/.env is a symlink to shared/.env" bash -c "[[ -L '$ROOT/releases/$ID1/backend/.env' && \$(readlink '$ROOT/releases/$ID1/backend/.env') == '$ROOT/shared/.env' ]]"
check "backend/storage is a symlink to shared/storage" bash -c "[[ -L '$ROOT/releases/$ID1/backend/storage' && \$(readlink '$ROOT/releases/$ID1/backend/storage') == '$ROOT/shared/storage' ]]"
check "storage from the artifact is not used" test ! -e "$ROOT/shared/storage/logs/from-artifact.log"
check "shared/.env installed from --env-file" grep -q 'Retail ERP one' "$ROOT/shared/.env"
check "stale build-time config cache replaced by config:cache" grep -q stub "$ROOT/releases/$ID1/backend/bootstrap/cache/config.php"
DETAIL="$(cat "$FAKE_LOG")"
check "migrate ran with the migrator account" log_has "php migrate @$ID1 dbuser=sroor_migrator"
check "tenants:migrate ran with the app account" log_has "php tenants:migrate @$ID1 dbuser="
check "storage:link --force ran in the new release" log_has "php storage:link @$ID1"
check "order: storage:link > migrate > grants > tenants:migrate > caches > smoke > switch > queue > health > /up" \
    in_order "php storage:link" "php migrate" "mysql --defaults-extra-file=" "php tenants:migrate" "php config:cache" \
    "php route:cache" "php view:cache" "php event:cache" "php health:check @$ID1" "sudo -n /usr/bin/systemctl reload php8.4-fpm" \
    "php horizon:terminate @current" "php health:check @current" "curl "
check "per-table grants applied (app: no UPDATE/DELETE on audit tables)" grep -qF 'REVOKE IF EXISTS ALL PRIVILEGES ON `sroor_central`.`central_audit_logs` FROM `sroor_app`@`localhost`' "$FAKE_SQL"
check "app gets UPDATE, DELETE on a normal table" grep -qF 'GRANT UPDATE, DELETE ON `sroor_central`.`tenants` TO `sroor_app`@`localhost`' "$FAKE_SQL"
check "/up is checked against the local nginx" log_has "--resolve example.test:443:127.0.0.1 https://example.test/up"
check "lock released" test ! -e "$ROOT/.deploy.lock"
check "no previous release recorded on the first deploy" test ! -e "$ROOT/.previous_release"
check "history records the deploy" grep -q "$ID1 $SHA1 deployed" "$ROOT/shared/deploy-history.log"

# ------------------------------------------------------------------------------------
echo "2. Second deploy"
# ------------------------------------------------------------------------------------
run_deploy "$SHA2" "$WORK/good2.tar.gz" "$WORK/env-two"
DETAIL="$OUT"
check "second deploy exits 0" test "$RC" -eq 0
ID2="$(current_id)"
check "current switched to the new release" bash -c "[[ '$ID2' == *-222222222222 ]]"
check "previous release recorded" grep -qx "$ID1" "$ROOT/.previous_release"
check "first release kept for rollback" test -d "$ROOT/releases/$ID1/backend"
check "shared/.env updated" grep -q 'Retail ERP two' "$ROOT/shared/.env"
check "previous shared/.env kept as .env.previous" grep -q 'Retail ERP one' "$ROOT/shared/.env.previous"

# ------------------------------------------------------------------------------------
echo "3. Failure before the switch: nothing changes for users"
# ------------------------------------------------------------------------------------
for failing in "migrate" "tenants:migrate" "config:cache"; do
    FAKE_FAIL="$failing" run_deploy "$SHA3" "$WORK/good3.tar.gz" "$WORK/env-one"
    DETAIL="$OUT"
    check "[$failing fails] exit 1" test "$RC" -eq 1
    check "[$failing fails] current unchanged" test "$(current_id)" = "$ID2"
    check "[$failing fails] failed release removed" test "$(release_count)" -eq 2
    check "[$failing fails] shared/.env restored" grep -q 'Retail ERP two' "$ROOT/shared/.env"
    DETAIL="$(cat "$FAKE_LOG")"
    check "[$failing fails] no php-fpm reload, no queue restart" bash -c "! grep -qE 'sudo|horizon:terminate' '$FAKE_LOG'"
    check "[$failing fails] lock released" test ! -e "$ROOT/.deploy.lock"
done
DETAIL="$(cat "$FAKE_LOG")"
FAKE_FAIL="migrate" run_deploy "$SHA3" "$WORK/good3.tar.gz" "$WORK/env-one"
check "central migrate failure: tenants:migrate never runs" bash -c "! grep -q 'tenants:migrate' '$FAKE_LOG'"
FAKE_FAIL="tenants:migrate" run_deploy "$SHA3" "$WORK/good3.tar.gz" "$WORK/env-one"
check "tenants:migrate failure is recorded" bash -c "tail -1 '$ROOT/shared/deploy-history.log' | grep -q 'failed:migrate-tenants'"

FAKE_HEALTH_NONE=1 run_deploy "$SHA3" "$WORK/good3.tar.gz" -
DETAIL="$OUT"
check "no registered health check: refused before the switch" bash -c "[[ $RC -eq 1 && \$(basename \$(readlink '$ROOT/current')) == '$ID2' ]]"
check "no registered health check: message names the override" grep -q 'allow-no-health-checks' <<<"$OUT"

FAKE_GRANTS_DIR="$GRANTS_DRIFT" run_deploy "$SHA3" "$WORK/good3.tar.gz" -
DETAIL="$OUT"
check "app with UPDATE on an audit table: release refused" test "$RC" -eq 1
check "grants drift is reported" grep -q 'can UPDATE/DELETE the audit table central_audit_logs' <<<"$OUT"
check "grants drift: current unchanged" test "$(current_id)" = "$ID2"

# ------------------------------------------------------------------------------------
echo "4. Failure after the switch: automatic rollback"
# ------------------------------------------------------------------------------------
FAKE_HEALTH_FAIL_CURRENT=1 run_deploy "$SHA3" "$WORK/good3.tar.gz" "$WORK/env-one"
DETAIL="$OUT"
check "health failure after the switch exits 3 (rolled back)" test "$RC" -eq 3
check "current back on the previous release" test "$(current_id)" = "$ID2"
check "failed release removed" test "$(release_count)" -eq 2
check "shared/.env restored after rollback" grep -q 'Retail ERP two' "$ROOT/shared/.env"
DETAIL="$(cat "$FAKE_LOG")"
check "php-fpm reloaded twice (switch + rollback)" test "$(grep -c 'sudo -n /usr/bin/systemctl reload php8.4-fpm' "$FAKE_LOG")" -eq 2
check "queue restarted on the previous release" log_has "php horizon:terminate @$ID2"
check "rollback recorded" bash -c "tail -1 '$ROOT/shared/deploy-history.log' | grep -q 'rolled-back:health'"

FAKE_CURL_FAIL_FIRST=2 run_deploy "$SHA3" "$WORK/good3.tar.gz" -
DETAIL="$OUT"
check "GET /up failing after the switch: rolled back (exit 3)" bash -c "[[ $RC -eq 3 && \$(basename \$(readlink '$ROOT/current')) == '$ID2' ]]"

FAKE_FAIL="horizon:terminate" run_deploy "$SHA3" "$WORK/good3.tar.gz" -
DETAIL="$OUT"
check "queue restart failure after the switch: rollback attempted, needs a human (exit 4)" test "$RC" -eq 4
check "queue restart failure: current back on the previous release" test "$(current_id)" = "$ID2"

new_root first-fails
FAKE_HEALTH_FAIL_CURRENT=1 run_deploy "$SHA1" "$WORK/good1.tar.gz" "$WORK/env-one"
DETAIL="$OUT"
check "first deploy failing after the switch: exit 4 and no current link" bash -c "[[ $RC -eq 4 && ! -e '$ROOT/current' && ! -L '$ROOT/current' ]]"

# ------------------------------------------------------------------------------------
echo "5. Preflight refusals (nothing extracted, no command run)"
# ------------------------------------------------------------------------------------
new_root preflight
preflight_case() {
    local name="$1" expr="$2"
    sed -e "$expr" "$WORK/env-one" >"$WORK/env-bad"
    run_deploy "$SHA1" "$WORK/good1.tar.gz" "$WORK/env-bad"
    DETAIL="$OUT"
    check "refuses: $name" bash -c "[[ $RC -eq 1 && \$(find '$ROOT/releases' -mindepth 1 | wc -l) -eq 0 ]]"
    check "refuses before any artisan call: $name" bash -c "! grep -q '^php ' '$FAKE_LOG'"
}
preflight_case "TELESCOPE_ENABLED=true" 's/^TELESCOPE_ENABLED=.*/TELESCOPE_ENABLED=true/'
preflight_case "TELESCOPE_ENABLED missing" '/^TELESCOPE_ENABLED=/d'
preflight_case "BACKUP_ARCHIVE_PASSWORD empty (D4)" 's/^BACKUP_ARCHIVE_PASSWORD=.*/BACKUP_ARCHIVE_PASSWORD=/'
preflight_case "MAIL_HOST empty (SMTP mandatory)" 's/^MAIL_HOST=.*/MAIL_HOST=/'
preflight_case "MAIL_MAILER=log" 's/^MAIL_MAILER=.*/MAIL_MAILER=log/'
preflight_case "DB_AUDIT_PRUNER_PASSWORD empty" 's/^DB_AUDIT_PRUNER_PASSWORD=.*/DB_AUDIT_PRUNER_PASSWORD=/'
preflight_case "APP_DEBUG=true" 's/^APP_DEBUG=.*/APP_DEBUG=true/'
check "the shared .env was never touched by refused releases" test ! -s "$ROOT/shared/.env"

run_deploy "$SHA1" "$WORK/withenv.tar.gz" "$WORK/env-one"
DETAIL="$OUT"
check "refuses an artifact that packages backend/.env" bash -c "[[ $RC -eq 1 ]] && grep -q 'secrets must never be packaged' <<<'$(printf '%s' "$OUT" | tr -d "'")'"
run_deploy "$SHA1" "$WORK/good1.tar.gz" "$WORK/env-one" --artifact-sha256 "$(printf '0%.0s' {1..64})"
check "refuses an artifact whose checksum does not match" test "$RC" -eq 1
good_sum="$(sha256sum "$WORK/good1.tar.gz" | awk '{print $1}')"
run_deploy "$SHA1" "$WORK/good1.tar.gz" "$WORK/env-one" --artifact-sha256 "$good_sum"
DETAIL="$OUT"
check "accepts the artifact with the right checksum" test "$RC" -eq 0

run_deploy "NOT-A-SHA" "$WORK/good1.tar.gz" "$WORK/env-one"
check "refuses a malformed sha" test "$RC" -ne 0

mkdir "$ROOT/.deploy.lock"
run_deploy "$SHA2" "$WORK/good2.tar.gz" -
DETAIL="$OUT"
check "a second concurrent deploy is refused" bash -c "[[ $RC -ne 0 ]] && grep -q 'another deploy is running' <<<'$(printf '%s' "$OUT" | tr -d "'")'"
check "the refused run leaves the other run's lock in place" test -d "$ROOT/.deploy.lock"
rmdir "$ROOT/.deploy.lock"

# ------------------------------------------------------------------------------------
echo "6. Manual rollback"
# ------------------------------------------------------------------------------------
new_root manual
run_deploy "$SHA1" "$WORK/good1.tar.gz" "$WORK/env-one"
A="$(current_id)"
run_deploy "$SHA2" "$WORK/good2.tar.gz" -
B="$(current_id)"
: >"$FAKE_LOG"
RC=0
OUT="$(bash "$DEPLOY" --app-root "$ROOT" --rollback 2>&1)" || RC=$?
DETAIL="$OUT"
check "--rollback exits 0" test "$RC" -eq 0
check "--rollback switches current to the previous release" test "$(current_id)" = "$A"
check "--rollback records the release it left" grep -qx "$B" "$ROOT/.previous_release"
check "--rollback reloads php-fpm and restarts the queue" bash -c "grep -q 'sudo -n /usr/bin/systemctl reload php8.4-fpm' '$FAKE_LOG' && grep -q 'php horizon:terminate @$A' '$FAKE_LOG'"
OUT="$(bash "$DEPLOY" --app-root "$ROOT" --rollback 2>&1)" || RC=$?
check "a second --rollback switches back" test "$(current_id)" = "$B"
RC=0
OUT="$(bash "$DEPLOY" --app-root "$ROOT" --rollback --queue-mode worker 2>&1)" || RC=$?
check "--queue-mode worker uses queue:restart" bash -c "grep -q 'php queue:restart' '$FAKE_LOG'"

# ------------------------------------------------------------------------------------
echo "7. Pruning old releases"
# ------------------------------------------------------------------------------------
new_root prune
for i in 1 2 3 4 5 6; do
    sha="$(printf 'a%039d' "$i")"
    run_deploy "$sha" "$WORK/good1.tar.gz" "$WORK/env-one" --keep 3
    [[ "$RC" -eq 0 ]] || break
    sleep 1
done
DETAIL="$OUT"
check "six deploys succeed" test "$RC" -eq 0
check "--keep 3 keeps three releases" test "$(release_count)" -eq 3
check "current and previous survive pruning" bash -c "[[ -d '$ROOT/releases/$(current_id)' && -d '$ROOT/releases/$(cat "$ROOT/.previous_release")' ]]"
check "pruning never follows the storage symlink" test -f "$ROOT/shared/storage/app/public/sentinel.txt"
check "pruning never follows the .env symlink" grep -q 'Retail ERP one' "$ROOT/shared/.env"

# ------------------------------------------------------------------------------------
echo "8. Secrets"
# ------------------------------------------------------------------------------------
leaked=""
for secret in "${SECRETS[@]}"; do
    if [[ "$ALL_OUT" == *"$secret"* ]]; then
        leaked+="[${secret:0:12}...] "
    fi
done
DETAIL="$leaked"
check "no password or secret in any output or stub argv log" test -z "$leaked"
check "migrator credentials never land in a release or shared/" bash -c "! grep -rqF '$MIGRATOR_PW' '$WORK'/root-*/releases '$WORK'/root-*/shared"

echo
echo "passed: $PASS  failed: $FAIL"
if [[ "$FAIL" -gt 0 ]]; then
    printf 'failed tests:\n'
    printf '  - %s\n' "${FAILED_NAMES[@]}"
    exit 1
fi
