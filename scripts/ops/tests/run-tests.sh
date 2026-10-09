#!/usr/bin/env bash
# Offline test suite for scripts/ops (OPS-1).
#
# Runs anywhere bash 4.4+ runs (Linux CI, macOS with brew bash, Git Bash on
# Windows). It never touches a server, never needs root and never needs
# network access. It checks:
#   - syntax and strict mode of every script
#   - banned patterns (`|| true`, `curl | sh`, `set -x`, hardcoded secrets)
#   - the MySQL grants contract: the SQL the scripts generate == the grants
#     documented literally in docs/07-operations/vps-runbook.md
#   - check-env.sh accepts a compliant production .env and rejects every
#     non-compliant variant without ever printing a secret value
#   - templates render completely (no unresolved ${VARS})
#   - the safe .env parser never executes file content
#
# Optional live check (MySQL 8 only, disposable server!):
#   OPS_TEST_MYSQL_CMD="mysql -h127.0.0.1 -P3307 -uroot" bash scripts/ops/tests/run-tests.sh
# creates opstest_* users, compares SHOW GRANTS with the expected grants and
# drops the users again. Never point it at a real server.

set -euo pipefail

TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OPS_DIR="$(cd "$TESTS_DIR/.." && pwd)"
REPO_ROOT="$(cd "$OPS_DIR/../.." && pwd)"
RUNBOOK="$REPO_ROOT/docs/07-operations/vps-runbook.md"

# Git Bash on Windows rewrites /unix/paths in env vars passed to native tools
# (envsubst). Harmless no-op elsewhere.
export MSYS2_ENV_CONV_EXCL='*'
export MSYS_NO_PATHCONV=1

PASS=0
FAIL=0
FAILED_NAMES=()

WORK_DIR="$(mktemp -d)"
cleanup() { rm -rf "$WORK_DIR"; }
trap cleanup EXIT

pass() {
    PASS=$((PASS + 1))
    printf '  ok    %s\n' "$1"
}

fail() {
    FAIL=$((FAIL + 1))
    FAILED_NAMES+=("$1")
    printf '  FAIL  %s\n' "$1"
    if [[ -n "${2:-}" ]]; then
        printf '        %s\n' "$2"
    fi
}

# Runs a command, captures stdout+stderr and the exit code without tripping set -e.
run_capture() {
    local __out_var="$1"
    local __rc_var="$2"
    shift 2
    local __output __rc
    set +e
    __output="$("$@" 2>&1)"
    __rc=$?
    set -e
    printf -v "$__out_var" '%s' "$__output"
    printf -v "$__rc_var" '%s' "$__rc"
}

all_scripts() {
    find "$OPS_DIR" -type f -name '*.sh' -not -path '*/tests/fixtures/*' | sort
}

# Default (documented) non-secret settings used for the grants contract.
export_default_db_names() {
    export CENTRAL_DB_NAME="sroor_central"
    export TENANT_DB_PREFIX="tenant_"
    export MYSQL_USER_HOST="localhost"
    export MYSQL_APP_USER="sroor_app"
    export MYSQL_MIGRATOR_USER="sroor_migrator"
    export MYSQL_PROVISIONER_USER="sroor_provisioner"
    export MYSQL_BACKUP_USER="sroor_backup"
    export MYSQL_AUDIT_PRUNER_USER="sroor_audit_pruner"
    export CENTRAL_AUDIT_TABLES="central_audit_logs activity_log"
    export CENTRAL_APPEND_ONLY_TABLES="tenant_credit_ledger"
}

# Clearly fake, low-entropy fixture passwords (never real secrets).
export_fixture_passwords() {
    export MYSQL_APP_PASSWORD="FixtureAppPasswordOnly000000000000"
    export MYSQL_MIGRATOR_PASSWORD="FixtureMigratorPasswordOnly0000000"
    export MYSQL_PROVISIONER_PASSWORD="FixtureProvisionerPasswordOnly0000"
    export MYSQL_BACKUP_PASSWORD="FixtureBackupPasswordOnly000000000"
    export MYSQL_AUDIT_PRUNER_PASSWORD="FixturePrunerPasswordOnly000000000"
}

# --------------------------------------------------------------------------
echo "1. Syntax and strict mode"
# --------------------------------------------------------------------------

while IFS= read -r script; do
    rel="${script#"$OPS_DIR"/}"
    if bash -n "$script" 2>"$WORK_DIR/syntax.err"; then
        pass "bash -n $rel"
    else
        fail "bash -n $rel" "$(head -3 "$WORK_DIR/syntax.err")"
    fi
    if head -n 40 "$script" | grep -qE '^set -euo pipefail$'; then
        pass "strict mode in $rel"
    else
        fail "strict mode in $rel" "missing 'set -euo pipefail' in the first 40 lines"
    fi
done < <(all_scripts)

# --------------------------------------------------------------------------
echo "2. Banned patterns"
# --------------------------------------------------------------------------

check_banned() {
    local name="$1" pattern="$2"
    local hits
    hits="$(grep -rnE --include='*.sh' --include='*.tmpl' --include='*.conf' --include='*.ini' --include='*.cnf' \
        "$pattern" "$OPS_DIR" | grep -v '/tests/' || [[ $? -eq 1 ]])"
    if [[ -z "$hits" ]]; then
        pass "$name"
    else
        fail "$name" "$(printf '%s' "$hits" | head -3)"
    fi
}

check_banned "no '|| true' (failures must stop the run)" '\|\|[[:space:]]*true'
check_banned "no 'curl | sh' installers" '(curl|wget)[^|]*\|[[:space:]]*(sudo[[:space:]]+)?(ba)?sh'
check_banned "no 'set -x' (would leak secrets into logs)" '^[[:space:]]*set[[:space:]]+-[a-z]*x'
check_banned "no passwords on a mysql command line" 'mysql[^|]*[[:space:]]-p[^[:space:]]'
check_banned "no redis-cli -a (password in argv)" 'redis-cli[^|]*[[:space:]]-a[[:space:]]'

# No server IPs in the repo: only loopback / wildcard bind addresses are allowed.
ip_hits="$(grep -rnoE --include='*.sh' --include='*.tmpl' --include='*.conf' --include='*.ini' --include='*.cnf' --include='*.example' \
    '([0-9]{1,3}\.){3}[0-9]{1,3}' "$OPS_DIR" | grep -v '/tests/' | grep -vE ':(127\.0\.0\.1|0\.0\.0\.0)$' || [[ $? -eq 1 ]])"
if [[ -z "$ip_hits" ]]; then
    pass "no hardcoded server IP addresses"
else
    fail "no hardcoded server IP addresses" "$(printf '%s' "$ip_hits" | head -3)"
fi

# Every *_PASSWORD / *_TOKEN / *_SECRET key in the example env files is empty.
check_empty_secrets() {
    local file="$1"
    local rel="${file#"$REPO_ROOT"/}"
    local bad
    bad="$(grep -nE '^([A-Z0-9_]*_(PASSWORD|TOKEN|SECRET)|APP_KEY)=.+$' "$file" || [[ $? -eq 1 ]])"
    if [[ -z "$bad" ]]; then
        pass "secret keys are empty in $rel"
    else
        fail "secret keys are empty in $rel" "$(printf '%s' "$bad" | cut -d= -f1 | head -5)"
    fi
}

check_empty_secrets "$OPS_DIR/provision.env.example"
check_empty_secrets "$OPS_DIR/templates/production.env.example"

# --------------------------------------------------------------------------
echo "3. MySQL grants contract (scripts == runbook)"
# --------------------------------------------------------------------------

# shellcheck source=../lib/common.sh
source "$OPS_DIR/lib/common.sh"
# shellcheck source=../lib/mysql-grants.sh
source "$OPS_DIR/lib/mysql-grants.sh"

(
    export_default_db_names
    expected_grants_all >"$WORK_DIR/expected.raw"
)
normalize_grants <"$WORK_DIR/expected.raw" >"$WORK_DIR/expected.norm"

if [[ -f "$RUNBOOK" ]]; then
    awk '/<!-- ops:expected-grants:begin -->/{f=1;next} /<!-- ops:expected-grants:end -->/{f=0} f && /^GRANT /' \
        "$RUNBOOK" | tr -d '\r' >"$WORK_DIR/runbook.raw"
    normalize_grants <"$WORK_DIR/runbook.raw" >"$WORK_DIR/runbook.norm"
    if [[ -s "$WORK_DIR/runbook.norm" ]] && diff -u "$WORK_DIR/runbook.norm" "$WORK_DIR/expected.norm" >"$WORK_DIR/grants.diff"; then
        pass "runbook grants block == generated expected grants ($(wc -l <"$WORK_DIR/expected.norm" | tr -d ' ') lines)"
    else
        fail "runbook grants block == generated expected grants" "$(head -20 "$WORK_DIR/grants.diff")"
    fi
else
    fail "runbook exists" "$RUNBOOK not found"
fi

# Literal spec checks (phase-1-plan OPS-1) on the generated grants.
grep_expected() {
    local name="$1" pattern="$2"
    if grep -qF -- "$pattern" "$WORK_DIR/expected.norm"; then
        pass "$name"
    else
        fail "$name" "expected a line containing: $pattern"
    fi
}

grep_expected "app = SELECT + INSERT on central at database level" 'GRANT INSERT, SELECT ON `sroor_central`.* TO `sroor_app`@`localhost`'
if grep -E 'TO `sroor_app`@' "$WORK_DIR/expected.norm" | grep -qE '(UPDATE|DELETE|ALL PRIVILEGES)'; then
    fail "app holds no database-level UPDATE/DELETE (append-only audit, IDEN-1.15)"
else
    pass "app holds no database-level UPDATE/DELETE (append-only audit, IDEN-1.15)"
fi
grep_expected "migrator may hand out central table grants (GRANT OPTION on central only)" 'ON `sroor_central`.* TO `sroor_migrator`@`localhost` WITH GRANT OPTION'
grep_expected "audit_pruner has no static privilege" 'GRANT USAGE ON *.* TO `sroor_audit_pruner`@`localhost`'
if grep -F 'TO `sroor_audit_pruner`@' "$WORK_DIR/expected.norm" | grep -qv '^GRANT USAGE ON'; then
    fail "audit_pruner holds nothing but USAGE until audit tables exist"
else
    pass "audit_pruner holds nothing but USAGE until audit tables exist"
fi
grep_expected "provisioner may CREATE USER" 'GRANT CREATE USER ON *.* TO `sroor_provisioner`@`localhost`'
grep_expected "provisioner holds every db-level privilege on the strict tenant\\_% pattern WITH GRANT OPTION" 'GRANT ALL PRIVILEGES ON `tenant\_%`.* TO `sroor_provisioner`@`localhost` WITH GRANT OPTION'
# Fail closed: no grant may use an unescaped `_` wildcard on the tenant prefix.
# `tenant_shop_a` would otherwise also match the DB of tenant `shop-a`.
if grep -qF '`tenant_%`' "$WORK_DIR/expected.norm"; then
    fail "no account holds the unescaped tenant_% wildcard pattern" "$(grep -F '`tenant_%`' "$WORK_DIR/expected.norm")"
else
    pass "no account holds the unescaped tenant_% wildcard pattern"
fi
grep_expected "provisioner reads only mysql.user(Host, User)" 'GRANT SELECT (`Host`, `User`) ON `mysql`.`user` TO `sroor_provisioner`@`localhost`'
grep_expected "backup has PROCESS globally" 'GRANT PROCESS ON *.* TO `sroor_backup`@`localhost`'
grep_expected "backup reads central" 'GRANT EVENT, LOCK TABLES, SELECT, SHOW VIEW, TRIGGER ON `sroor_central`.* TO `sroor_backup`@`localhost`'
grep_expected "backup reads tenants" 'GRANT EVENT, LOCK TABLES, SELECT, SHOW VIEW, TRIGGER ON `tenant\_%`.* TO `sroor_backup`@`localhost`'

# The provisioner must hold every privilege stancl's PermissionControlledMySQLDatabaseManager grants.
STANCL_FILE="$REPO_ROOT/backend/vendor/stancl/tenancy/src/TenantDatabaseManagers/PermissionControlledMySQLDatabaseManager.php"
if [[ -f "$STANCL_FILE" ]]; then
    stancl_privs="$(sed -n '/\$grants = \[/,/\];/p' "$STANCL_FILE" | grep -oE "'[A-Z ]+'" | tr -d "'")"
    prov_line="$(grep -F 'TO `sroor_provisioner`@`localhost` WITH GRANT OPTION' "$WORK_DIR/expected.norm" | head -1)"
    missing_privs=""
    while IFS= read -r priv; do
        # The lib documents the stancl list; db-level ALL PRIVILEGES must be a superset of it.
        if ! grep -qE "(^|, )${priv}(,|$)" <<<"$STANCL_TENANT_PRIVILEGES"; then
            missing_privs+="[$priv] "
        fi
    done <<<"$stancl_privs"
    stancl_count="$(grep -c . <<<"$stancl_privs")"
    lib_count="$(tr ',' '\n' <<<"$STANCL_TENANT_PRIVILEGES" | grep -c .)"
    if [[ -n "$stancl_privs" && -z "$missing_privs" && "$stancl_count" -eq "$lib_count" && "$prov_line" == "GRANT ALL PRIVILEGES ON "*" WITH GRANT OPTION" ]]; then
        pass "provisioner covers every privilege in stancl \$grants"
    else
        fail "provisioner covers every privilege in stancl \$grants" "missing: $missing_privs"
    fi
else
    printf '  skip  stancl vendor file not installed (composer install) - grant coverage not checked\n'
fi

# normalize_grants must be order-insensitive and keep column lists intact.
norm_a="$(printf '%s\n' 'GRANT SELECT (`User`, `Host`), UPDATE ON `mysql`.`user` TO `x`@`localhost`' | normalize_grants)"
norm_b="$(printf '%s\n' 'GRANT UPDATE, SELECT (`Host`, `User`) ON `mysql`.`user` TO `x`@`localhost`' | normalize_grants)"
if [[ "$norm_a" == "$norm_b" ]]; then
    pass "normalize_grants ignores privilege and column order"
else
    fail "normalize_grants ignores privilege and column order" "$norm_a != $norm_b"
fi


# Per-table grants (append-only audit contract, IDEN-1.15).
printf '%s\n' activity_log central_audit_logs domains migrations tenant_credit_ledger tenants >"$WORK_DIR/tables"
(
    export_default_db_names
    expected_table_grants <"$WORK_DIR/tables" | normalize_grants >"$WORK_DIR/table-grants"
    build_table_grants_sql <"$WORK_DIR/tables" >"$WORK_DIR/table-grants.sql"
)
tg_has() {
    local name="$1" file="$2" needle="$3"
    if grep -qF -- "$needle" "$file"; then pass "$name"; else fail "$name" "missing: $needle"; fi
}
tg_has "app gets UPDATE, DELETE per normal central table" "$WORK_DIR/table-grants" 'GRANT DELETE, UPDATE ON `sroor_central`.`tenants` TO `sroor_app`@`localhost`'
tg_has "audit_pruner gets SELECT, DELETE on central_audit_logs" "$WORK_DIR/table-grants" 'GRANT DELETE, SELECT ON `sroor_central`.`central_audit_logs` TO `sroor_audit_pruner`@`localhost`'
tg_has "audit_pruner gets SELECT, DELETE on activity_log" "$WORK_DIR/table-grants" 'GRANT DELETE, SELECT ON `sroor_central`.`activity_log` TO `sroor_audit_pruner`@`localhost`'
if grep -E '`(central_audit_logs|activity_log)` TO `sroor_app`' "$WORK_DIR/table-grants" >/dev/null; then
    fail "app gets no table grant on the audit tables"
else
    pass "app gets no table grant on the audit tables"
fi
if grep -E '`(domains|migrations|tenants)` TO `sroor_audit_pruner`' "$WORK_DIR/table-grants" >/dev/null; then
    fail "audit_pruner gets nothing outside the audit tables"
else
    pass "audit_pruner gets nothing outside the audit tables"
fi
if grep -E '`tenant_credit_ledger` TO ' "$WORK_DIR/table-grants" >/dev/null; then
    fail "no account gets a table grant on the append-only ledger tenant_credit_ledger"
else
    pass "no account gets a table grant on the append-only ledger tenant_credit_ledger"
fi
tg_has "sync SQL revokes any hand-added app privilege on the ledger" "$WORK_DIR/table-grants.sql" 'REVOKE IF EXISTS ALL PRIVILEGES ON `sroor_central`.`tenant_credit_ledger` FROM `sroor_app`@`localhost`;'
tg_has "sync SQL revokes any hand-added audit_pruner privilege on the ledger" "$WORK_DIR/table-grants.sql" 'REVOKE IF EXISTS ALL PRIVILEGES ON `sroor_central`.`tenant_credit_ledger` FROM `sroor_audit_pruner`@`localhost`;'
if grep -E '^GRANT .*`tenant_credit_ledger`' "$WORK_DIR/table-grants.sql" >/dev/null; then
    fail "sync SQL never grants anything on the ledger"
else
    pass "sync SQL never grants anything on the ledger"
fi
(
    export_default_db_names
    export CENTRAL_APPEND_ONLY_TABLES="central_audit_logs"
    validate_mysql_names >/dev/null 2>&1
) && overlap_rc=0 || overlap_rc=$?
if [[ "$overlap_rc" -ne 0 ]]; then
    pass "a table listed as both audit and append-only ledger is refused"
else
    fail "a table listed as both audit and append-only ledger is refused"
fi
tg_has "sync SQL revokes any hand-added app privilege on an audit table" "$WORK_DIR/table-grants.sql" 'REVOKE IF EXISTS ALL PRIVILEGES ON `sroor_central`.`central_audit_logs` FROM `sroor_app`@`localhost`;'
if [[ "$(grep -c '^GRANT ' "$WORK_DIR/table-grants.sql")" -eq 5 ]]; then
    pass "sync SQL has one GRANT per central table except the append-only ledger"
else
    fail "sync SQL has one GRANT per central table except the append-only ledger" "$(cat "$WORK_DIR/table-grants.sql")"
fi
(
    export_default_db_names
    printf 'ok_table\nbad`table\n' | build_table_grants_sql >/dev/null 2>&1
) && badtbl_rc=0 || badtbl_rc=$?
if [[ "$badtbl_rc" -ne 0 ]]; then
    pass "a table name that is not a plain identifier is refused"
else
    fail "a table name that is not a plain identifier is refused"
fi

# --------------------------------------------------------------------------
echo "4. 41-mysql-users.sh SQL generation"
# --------------------------------------------------------------------------

USERS_SCRIPT="$OPS_DIR/steps/41-mysql-users.sh"

(
    export_default_db_names
    export_fixture_passwords
    bash "$USERS_SCRIPT" --print-sql >"$WORK_DIR/users.sql" 2>&1
) && users_rc=0 || users_rc=$?

if [[ "$users_rc" -eq 0 ]]; then
    pass "--print-sql exits 0"
else
    fail "--print-sql exits 0" "$(head -5 "$WORK_DIR/users.sql")"
fi

if grep -q 'Fixture' "$WORK_DIR/users.sql"; then
    fail "--print-sql redacts every password"
else
    pass "--print-sql redacts every password"
fi

for needle in 'CREATE USER IF NOT EXISTS `sroor_app`@`localhost`' \
    'CREATE USER IF NOT EXISTS `sroor_audit_pruner`@`localhost`' \
    'ALTER USER `sroor_provisioner`@`localhost` IDENTIFIED BY' \
    'REVOKE ALL PRIVILEGES, GRANT OPTION FROM `sroor_backup`@`localhost`' \
    'CREATE DATABASE IF NOT EXISTS `sroor_central`'; do
    if grep -qF -- "$needle" "$WORK_DIR/users.sql"; then
        pass "--print-sql contains: $needle"
    else
        fail "--print-sql contains: $needle"
    fi
done

(
    export_default_db_names
    bash "$USERS_SCRIPT" --print-expected-grants >"$WORK_DIR/expected2.raw" 2>&1
) && pe_rc=0 || pe_rc=$?
if [[ "$pe_rc" -eq 0 ]] && diff -q <(normalize_grants <"$WORK_DIR/expected2.raw") "$WORK_DIR/expected.norm" >/dev/null; then
    pass "--print-expected-grants works without any password set"
else
    fail "--print-expected-grants works without any password set" "$(head -3 "$WORK_DIR/expected2.raw")"
fi

# Weak / malformed passwords are refused and never echoed back.
(
    export_default_db_names
    export_fixture_passwords
    export MYSQL_APP_PASSWORD="short'pw"
    bash "$USERS_SCRIPT" --print-sql >"$WORK_DIR/weak.out" 2>&1
) && weak_rc=0 || weak_rc=$?
if [[ "$weak_rc" -ne 0 ]] && ! grep -q "short'pw" "$WORK_DIR/weak.out"; then
    pass "weak/unsafe password rejected without echoing it"
else
    fail "weak/unsafe password rejected without echoing it" "rc=$weak_rc"
fi

# A central DB name that matches the tenant wildcard would hand tenants' grants to central.
# `_` is a wildcard in the provisioner's pattern, so `tenantXcentral` overlaps too.
for overlap_name in tenant_central tenantxcentral; do
    (
        export_default_db_names
        export_fixture_passwords
        export CENTRAL_DB_NAME="$overlap_name"
        bash "$USERS_SCRIPT" --print-sql >"$WORK_DIR/overlap.out" 2>&1
    ) && overlap_rc=0 || overlap_rc=$?
    if [[ "$overlap_rc" -ne 0 ]]; then
        pass "central DB name overlapping the tenant pattern is rejected ($overlap_name)"
    else
        fail "central DB name overlapping the tenant pattern is rejected ($overlap_name)"
    fi
done

# Duplicate account names would merge two roles into one account.
(
    export_default_db_names
    export_fixture_passwords
    export MYSQL_BACKUP_USER="sroor_app"
    bash "$USERS_SCRIPT" --print-sql >"$WORK_DIR/dup.out" 2>&1
) && dup_rc=0 || dup_rc=$?
if [[ "$dup_rc" -ne 0 ]]; then
    pass "duplicate MySQL account names are rejected"
else
    fail "duplicate MySQL account names are rejected"
fi

# --------------------------------------------------------------------------
echo "5. check-env.sh (production .env gate)"
# --------------------------------------------------------------------------

CHECK_ENV="$OPS_DIR/check-env.sh"
# Set by run_capture through printf -v.
out=""
rc=0
FAKE_SECRET="fixture-secret-value-must-never-be-printed"

write_good_env() {
    cat >"$1" <<EOF
APP_NAME="Retail ERP"
APP_ENV=production
APP_KEY=base64:${FAKE_SECRET}
APP_DEBUG=false
APP_URL=https://example.test
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=sroor_central
DB_USERNAME=sroor_app
DB_PASSWORD=${FAKE_SECRET}
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=${FAKE_SECRET}
TELESCOPE_ENABLED=false
QUICK_LOGIN_ENABLED=false
DB_AUDIT_PRUNER_USERNAME=sroor_audit_pruner
DB_AUDIT_PRUNER_PASSWORD=${FAKE_SECRET}
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.test
MAIL_PORT=587
MAIL_USERNAME=fixture-mail-user
MAIL_PASSWORD=${FAKE_SECRET}
MAIL_FROM_ADDRESS=noreply@example.test
BACKUP_ARCHIVE_PASSWORD=${FAKE_SECRET}
SENTRY_LARAVEL_DSN=https://fixture@sentry.example.test/1
SENTRY_SEND_DEFAULT_PII=false
EOF
}

GOOD_ENV="$WORK_DIR/good.env"
write_good_env "$GOOD_ENV"
run_capture out rc bash "$CHECK_ENV" "$GOOD_ENV"
if [[ "$rc" -eq 0 ]]; then
    pass "compliant production .env passes"
else
    fail "compliant production .env passes" "$(printf '%s' "$out" | head -5)"
fi

# name | sed expression applied to the good env
BAD_CASES=(
    "APP_DEBUG=true|s/^APP_DEBUG=.*/APP_DEBUG=true/"
    "APP_ENV=local|s/^APP_ENV=.*/APP_ENV=local/"
    "TELESCOPE_ENABLED=true|s/^TELESCOPE_ENABLED=.*/TELESCOPE_ENABLED=true/"
    "TELESCOPE_ENABLED missing (config defaults to true)|/^TELESCOPE_ENABLED=/d"
    "CACHE_STORE=file|s/^CACHE_STORE=.*/CACHE_STORE=file/"
    "QUEUE_CONNECTION=sync|s/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/"
    "SESSION_SECURE_COOKIE missing|/^SESSION_SECURE_COOKIE=/d"
    "QUICK_LOGIN_ENABLED=true|s/^QUICK_LOGIN_ENABLED=.*/QUICK_LOGIN_ENABLED=true/"
    "APP_KEY empty|s/^APP_KEY=.*/APP_KEY=/"
    "APP_URL over http|s#^APP_URL=.*#APP_URL=http://example.test#"
    "DB_USERNAME=root|s/^DB_USERNAME=.*/DB_USERNAME=root/"
    "DB_PASSWORD empty|s/^DB_PASSWORD=.*/DB_PASSWORD=/"
    "REDIS_PASSWORD empty|s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=/"
    "LOG_LEVEL=debug|s/^LOG_LEVEL=.*/LOG_LEVEL=debug/"
    "APP_DEBUG quoted true|s/^APP_DEBUG=.*/APP_DEBUG=\"true\"/"
    "MAIL_MAILER=log (SMTP mandatory)|s/^MAIL_MAILER=.*/MAIL_MAILER=log/"
    "MAIL_HOST missing|/^MAIL_HOST=/d"
    "MAIL_PASSWORD empty|s/^MAIL_PASSWORD=.*/MAIL_PASSWORD=/"
    "MAIL_PORT empty|s/^MAIL_PORT=.*/MAIL_PORT=/"
    "MAIL_FROM_ADDRESS not an address|s/^MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=noreply/"
    "BACKUP_ARCHIVE_PASSWORD empty (D4)|s/^BACKUP_ARCHIVE_PASSWORD=.*/BACKUP_ARCHIVE_PASSWORD=/"
    "BACKUP_ARCHIVE_PASSWORD missing (D4)|/^BACKUP_ARCHIVE_PASSWORD=/d"
    "DB_AUDIT_PRUNER_PASSWORD empty|s/^DB_AUDIT_PRUNER_PASSWORD=.*/DB_AUDIT_PRUNER_PASSWORD=/"
    "DB_AUDIT_PRUNER_USERNAME == DB_USERNAME|s/^DB_AUDIT_PRUNER_USERNAME=.*/DB_AUDIT_PRUNER_USERNAME=sroor_app/"
    "SENTRY_SEND_DEFAULT_PII=true|s/^SENTRY_SEND_DEFAULT_PII=.*/SENTRY_SEND_DEFAULT_PII=true/"
)

for case in "${BAD_CASES[@]}"; do
    name="${case%%|*}"
    expr="${case#*|}"
    bad_env="$WORK_DIR/bad.env"
    sed -e "$expr" "$GOOD_ENV" >"$bad_env"
    run_capture out rc bash "$CHECK_ENV" "$bad_env"
    if [[ "$rc" -ne 0 ]]; then
        pass "rejects: $name"
    else
        fail "rejects: $name" "check-env.sh exited 0"
    fi
    if [[ "$out" == *"$FAKE_SECRET"* ]]; then
        fail "never prints secrets ($name)"
    fi
done

# Empty Sentry DSN is a warning, not a failure (OPS-7 lands before the cutover).
sed -e 's/^SENTRY_LARAVEL_DSN=.*/SENTRY_LARAVEL_DSN=/' "$GOOD_ENV" >"$WORK_DIR/nosentry.env"
run_capture out rc bash "$CHECK_ENV" "$WORK_DIR/nosentry.env"
if [[ "$rc" -eq 0 && "$out" == *"WARN  SENTRY_LARAVEL_DSN is empty"* ]]; then
    pass "empty SENTRY_LARAVEL_DSN only warns"
else
    fail "empty SENTRY_LARAVEL_DSN only warns" "$(printf '%s' "$out" | head -5)"
fi

# Values with quotes, CRLF line endings and comments are parsed like Laravel/phpdotenv.
crlf_env="$WORK_DIR/crlf.env"
sed -e 's/^APP_DEBUG=.*/APP_DEBUG="false"   # quoted/' "$GOOD_ENV" | sed -e 's/$/\r/' >"$crlf_env"
run_capture out rc bash "$CHECK_ENV" "$crlf_env"
if [[ "$rc" -eq 0 ]]; then
    pass "CRLF + quoted values + inline comments are parsed"
else
    fail "CRLF + quoted values + inline comments are parsed" "$(printf '%s' "$out" | head -3)"
fi

run_capture out rc bash "$CHECK_ENV" "$WORK_DIR/does-not-exist.env"
if [[ "$rc" -ne 0 ]]; then
    pass "missing file is an error"
else
    fail "missing file is an error"
fi

# The production template itself is compliant once the secrets are filled in.
tmpl_env="$WORK_DIR/template.env"
sed -e "s|^APP_KEY=\$|APP_KEY=base64:${FAKE_SECRET}|" \
    -e "s|^DB_PASSWORD=\$|DB_PASSWORD=${FAKE_SECRET}|" \
    -e "s|^REDIS_PASSWORD=\$|REDIS_PASSWORD=${FAKE_SECRET}|" \
    -e "s|^APP_URL=\$|APP_URL=https://example.test|" \
    -e "s|^DB_AUDIT_PRUNER_PASSWORD=\$|DB_AUDIT_PRUNER_PASSWORD=${FAKE_SECRET}|" \
    -e "s|^MAIL_HOST=\$|MAIL_HOST=smtp.example.test|" \
    -e "s|^MAIL_PORT=\$|MAIL_PORT=587|" \
    -e "s|^MAIL_USERNAME=\$|MAIL_USERNAME=fixture-mail-user|" \
    -e "s|^MAIL_PASSWORD=\$|MAIL_PASSWORD=${FAKE_SECRET}|" \
    -e "s|^MAIL_FROM_ADDRESS=\$|MAIL_FROM_ADDRESS=noreply@example.test|" \
    -e "s|^BACKUP_ARCHIVE_PASSWORD=\$|BACKUP_ARCHIVE_PASSWORD=${FAKE_SECRET}|" \
    "$OPS_DIR/templates/production.env.example" >"$tmpl_env"
run_capture out rc bash "$CHECK_ENV" "$tmpl_env"
if [[ "$rc" -eq 0 ]]; then
    pass "templates/production.env.example is production-compliant"
else
    fail "templates/production.env.example is production-compliant" "$(printf '%s' "$out" | head -5)"
fi

# --------------------------------------------------------------------------
echo "6. Safe .env parsing (no code execution)"
# --------------------------------------------------------------------------

marker="$WORK_DIR/pwned"
evil_env="$WORK_DIR/evil.env"
cat >"$evil_env" <<EOF
SAFE_KEY=plain
CMD_SUB=\$(touch $marker)
BACKTICK=\`touch $marker\`
EOF
(
    load_env_file "$evil_env"
    [[ "$SAFE_KEY" == "plain" ]]
    [[ "$CMD_SUB" == "\$(touch $marker)" ]]
) && parse_rc=0 || parse_rc=$?
if [[ "$parse_rc" -eq 0 && ! -e "$marker" ]]; then
    pass "load_env_file stores values literally and never executes them"
else
    fail "load_env_file stores values literally and never executes them" "rc=$parse_rc marker_exists=$([[ -e $marker ]] && echo yes || echo no)"
fi

invalid_env="$WORK_DIR/invalid.env"
printf 'GOOD=1\nthis is not an assignment\n' >"$invalid_env"
(load_env_file "$invalid_env") >/dev/null 2>&1 && inv_rc=0 || inv_rc=$?
if [[ "$inv_rc" -ne 0 ]]; then
    pass "load_env_file rejects malformed lines"
else
    fail "load_env_file rejects malformed lines"
fi

# --------------------------------------------------------------------------
echo "7. Templates render completely"
# --------------------------------------------------------------------------

(
    export PLATFORM_DOMAIN="example.test"
    export APP_ROOT="/var/www/sroor"
    export APP_USER="sroor"
    export ADMIN_USER="opsadmin"
    export SSH_PORT="22"
    export PHP_VERSION="8.4"
    export PHP_FPM_MAX_CHILDREN="20"
    export HSTS_MAX_AGE="31536000"
    export REDIS_PASSWORD="FixtureRedisPasswordOnly0000000000"
    export REDIS_MAXMEMORY="1gb"
    export QUEUE_WORKER_PROCESSES="2"
    export MYSQL_INNODB_BUFFER_POOL="2G"
    for tmpl in "$OPS_DIR"/templates/*.tmpl; do
        out_file="$WORK_DIR/$(basename "$tmpl" .tmpl)"
        render_template "$tmpl" "$out_file"
        if grep -qE '\$\{[A-Z_][A-Z0-9_]*\}' "$out_file"; then
            echo "UNRESOLVED $(basename "$tmpl")"
        fi
    done
) >"$WORK_DIR/render.out" 2>&1 && render_rc=0 || render_rc=$?

if [[ "$render_rc" -eq 0 ]] && ! grep -q UNRESOLVED "$WORK_DIR/render.out"; then
    pass "every templates/*.tmpl renders with no unresolved \${VAR}"
else
    fail "every templates/*.tmpl renders with no unresolved \${VAR}" "$(head -5 "$WORK_DIR/render.out")"
fi

assert_file_contains() {
    local name="$1" file="$2" needle="$3"
    if [[ -f "$file" ]] && grep -qF -- "$needle" "$file"; then
        pass "$name"
    else
        fail "$name" "missing: $needle"
    fi
}

assert_file_lacks() {
    local name="$1" file="$2" needle="$3"
    if [[ -f "$file" ]] && ! grep -qF -- "$needle" "$file"; then
        pass "$name"
    else
        fail "$name" "unexpected: $needle"
    fi
}

assert_file_contains "nginx serves apex + wildcard" "$WORK_DIR/nginx-site.conf" 'server_name example.test *.example.test;'
assert_file_contains "nginx uses the wildcard certificate" "$WORK_DIR/nginx-site.conf" '/etc/letsencrypt/live/example.test/fullchain.pem'
assert_file_contains "nginx serves the current release only" "$WORK_DIR/nginx-site.conf" 'root /var/www/sroor/current/backend/public;'
assert_file_contains "nginx resolves the release symlink for PHP" "$WORK_DIR/nginx-site.conf" '$realpath_root$fastcgi_script_name'
assert_file_contains "nginx keeps nginx runtime variables" "$WORK_DIR/nginx-site.conf" 'return 301 https://$host$request_uri;'
assert_file_contains "nginx rejects unknown hosts on 443" "$WORK_DIR/nginx-site.conf" 'ssl_reject_handshake on;'
assert_file_contains "nginx executes only index.php" "$WORK_DIR/nginx-site.conf" 'location = /index.php'
assert_file_contains "php-fpm pool runs as the app user" "$WORK_DIR/php-fpm-pool.conf" 'user = sroor'
assert_file_contains "supervisor runs Horizon from the current release" "$WORK_DIR/supervisor-horizon.conf" 'command=/usr/bin/php /var/www/sroor/current/backend/artisan horizon'
assert_file_contains "supervisor lets Horizon finish jobs on stop" "$WORK_DIR/supervisor-horizon.conf" 'stopwaitsecs=3600'
assert_file_contains "fallback worker uses redis" "$WORK_DIR/supervisor-worker.conf" 'artisan queue:work redis'
assert_file_contains "scheduler runs every minute as the app user" "$WORK_DIR/cron-scheduler" '* * * * * sroor cd /var/www/sroor/current/backend && /usr/bin/php artisan schedule:run'
assert_file_contains "redis binds to loopback" "$WORK_DIR/redis-sroor.conf" 'bind 127.0.0.1 -::1'
assert_file_contains "redis never evicts queued jobs" "$WORK_DIR/redis-sroor.conf" 'maxmemory-policy noeviction'
assert_file_contains "mysql binds to loopback" "$WORK_DIR/mysql-sroor.cnf" 'bind-address = 127.0.0.1'
assert_file_contains "mysql disables LOCAL INFILE" "$WORK_DIR/mysql-sroor.cnf" 'local_infile = 0'
assert_file_contains "sudoers only allows php-fpm reload" "$WORK_DIR/sudoers-app" 'NOPASSWD: /usr/bin/systemctl reload php8.4-fpm'
assert_file_lacks "sudoers never grants ALL commands" "$WORK_DIR/sudoers-app" 'NOPASSWD: ALL'
assert_file_contains "sshd: password login disabled" "$WORK_DIR/sshd-hardening.conf" 'PasswordAuthentication no'
assert_file_contains "sshd: keyboard-interactive disabled" "$WORK_DIR/sshd-hardening.conf" 'KbdInteractiveAuthentication no'
assert_file_contains "sshd: root login disabled" "$WORK_DIR/sshd-hardening.conf" 'PermitRootLogin no'
assert_file_contains "sshd: only the two service accounts" "$WORK_DIR/sshd-hardening.conf" 'AllowUsers opsadmin sroor'
assert_file_contains "fail2ban protects sshd on the configured port" "$WORK_DIR/fail2ban-jail.local" 'port = 22'

# Upload limits must let the super-admin publish an APK (StoreAppVersionRequest
# `apk_file` max:<KB>) while every other route keeps the small default body cap.
# size_to_mb 25m|160M|1g -> whole megabytes.
size_to_mb() {
    local v="${1,,}"
    case "$v" in
        *g) printf '%s' "$((${v%g} * 1024))" ;;
        *m) printf '%s' "${v%m}" ;;
        *k) printf '%s' "$((${v%k} / 1024))" ;;
        *) printf '%s' "$((v / 1048576))" ;;
    esac
}
APK_REQUEST="$REPO_ROOT/backend/app/Http/Requests/AppVersions/StoreAppVersionRequest.php"
apk_kb="$(grep -E "'apk_file'" "$APK_REQUEST" 2>/dev/null | grep -oE "max:[0-9]+" | head -1 | cut -d: -f2 || [[ $? -eq 1 ]])"
nginx_conf="$WORK_DIR/nginx-site.conf"
php_ini="$WORK_DIR/php-sroor.ini"
if [[ -z "$apk_kb" ]]; then
    fail "APK upload limit found in StoreAppVersionRequest" "no 'apk_file' max:<KB> rule"
else
    apk_mb=$(((apk_kb + 1023) / 1024))
    # The dedicated location block (from its opening line to the first closing brace).
    apk_block="$(awk '/location = \/api\/v1\/super-admin\/app-versions[[:space:]]*\{/{f=1} f{print} f&&/^[[:space:]]*\}/{exit}' "$nginx_conf")"
    apk_nginx="$(grep -oE 'client_max_body_size[[:space:]]+[0-9]+[kKmMgG]?' <<<"$apk_block" | awk '{print $2}' | head -1 || [[ $? -eq 1 ]])"
    global_nginx="$(grep -E '^    client_max_body_size' "$nginx_conf" | grep -oE '[0-9]+[kKmMgG]?' | head -1 || [[ $? -eq 1 ]])"
    php_upload="$(grep -E '^upload_max_filesize' "$php_ini" | grep -oE '[0-9]+[kKmMgG]?' | head -1 || [[ $? -eq 1 ]])"
    php_post="$(grep -E '^post_max_size' "$php_ini" | grep -oE '[0-9]+[kKmMgG]?' | head -1 || [[ $? -eq 1 ]])"
    # Multipart framing + the other form fields need headroom above the file itself.
    if [[ -n "$apk_nginx" && "$(size_to_mb "$apk_nginx")" -gt "$apk_mb" ]] && grep -qF 'fastcgi_pass unix:/run/php/sroor-fpm.sock;' <<<"$apk_block"; then
        pass "nginx: app-versions upload location allows > ${apk_mb}MB and runs PHP directly"
    else
        fail "nginx: app-versions upload location allows > ${apk_mb}MB and runs PHP directly" "got client_max_body_size='${apk_nginx}'"
    fi
    if [[ -n "$global_nginx" && "$(size_to_mb "$global_nginx")" -le 25 ]]; then
        pass "nginx: every other route keeps a <= 25MB body cap"
    else
        fail "nginx: every other route keeps a <= 25MB body cap" "got '${global_nginx}'"
    fi
    if [[ -n "$php_upload" && -n "$php_post" && "$(size_to_mb "$php_upload")" -ge "$apk_mb" && "$(size_to_mb "$php_post")" -gt "$(size_to_mb "$php_upload")" && "$(size_to_mb "$php_post")" -ge "$(size_to_mb "${apk_nginx:-0}")" ]]; then
        pass "php: upload_max_filesize >= ${apk_mb}MB and post_max_size covers the nginx APK cap"
    else
        fail "php: upload_max_filesize >= ${apk_mb}MB and post_max_size covers the nginx APK cap" "upload=${php_upload} post=${php_post}"
    fi
fi

# render_template must refuse a template that references an unset variable.
(
    unset PLATFORM_DOMAIN
    printf 'server_name ${PLATFORM_DOMAIN};\n' >"$WORK_DIR/unset.tmpl"
    render_template "$WORK_DIR/unset.tmpl" "$WORK_DIR/unset.out"
) >/dev/null 2>&1 && unset_rc=0 || unset_rc=$?
if [[ "$unset_rc" -ne 0 ]]; then
    pass "render_template fails on an unset variable"
else
    fail "render_template fails on an unset variable"
fi

# Versions decided by the CTO (W1 Q6): PHP 8.4 + MySQL 8.4 LTS from their repos.
assert_file_contains "provisioning defaults to PHP 8.4" "$OPS_DIR/provision.env.example" 'PHP_VERSION=8.4'
assert_file_contains "lib default PHP_VERSION is 8.4" "$OPS_DIR/lib/common.sh" ': "${PHP_VERSION:=8.4}"'
assert_file_contains "step 30 refuses anything but PHP 8.4" "$OPS_DIR/steps/30-php.sh" '[[ "$PHP_VERSION" == "8.4" ]]'
assert_file_contains "step 30 installs from the ondrej/php PPA" "$OPS_DIR/steps/30-php.sh" 'add-apt-repository -y ppa:ondrej/php'
assert_file_contains "step 40 uses the MySQL 8.4 LTS repo component" "$OPS_DIR/steps/40-mysql.sh" 'MYSQL_APT_COMPONENT="mysql-8.4-lts"'
assert_file_contains "step 40 checks the repo key fingerprint before trusting it" "$OPS_DIR/steps/40-mysql.sh" 'MySQL APT key fingerprint mismatch'
assert_file_contains "step 40 refuses a server that is not 8.4" "$OPS_DIR/steps/40-mysql.sh" '[[ "$version" == 8.4.* ]]'
assert_file_contains "step 41 re-applies the per-table audit grants" "$OPS_DIR/steps/41-mysql-users.sh" 'sync-central-grants.sh" --as-root'
assert_file_contains "verify.sh checks the append-only audit grants" "$OPS_DIR/verify.sh" 'sync-central-grants.sh" --as-root --check-only'

# --------------------------------------------------------------------------
echo "8. Orchestrator"
# --------------------------------------------------------------------------

run_capture out rc bash "$OPS_DIR/provision.sh" --list
expected_order="00-base 10-ssh-users 20-firewall 30-php 40-mysql 41-mysql-users 50-redis 60-tls 70-nginx 80-queue 85-scheduler 90-app-layout"
actual_order="$(printf '%s\n' "$out" | awk '/^[0-9][0-9]-/{print $1}' | tr '\n' ' ' | sed 's/ $//')"
if [[ "$rc" -eq 0 && "$actual_order" == "$expected_order" ]]; then
    pass "provision.sh --list shows every step in order"
else
    fail "provision.sh --list shows every step in order" "got: $actual_order"
fi

for step in $expected_order; do
    if [[ -f "$OPS_DIR/steps/$step.sh" ]]; then
        pass "step script exists: $step"
    else
        fail "step script exists: $step"
    fi
done

run_capture out rc bash "$OPS_DIR/provision.sh" --only 99-nope --env-file "$WORK_DIR/none.env"
if [[ "$rc" -ne 0 ]]; then
    pass "provision.sh refuses unknown steps / missing env file"
else
    fail "provision.sh refuses unknown steps / missing env file"
fi

# Every variable a step requires is documented in provision.env.example.
missing_doc=""
while IFS= read -r var; do
    if ! grep -qE "^${var}=" "$OPS_DIR/provision.env.example"; then
        missing_doc+="$var "
    fi
done < <(grep -hoE 'require_(vars|secret)[[:space:]]+[A-Z_ ]+' "$OPS_DIR"/steps/*.sh "$OPS_DIR"/provision.sh "$OPS_DIR"/verify.sh |
    sed -E 's/require_(vars|secret)//' | tr ' ' '\n' | sed '/^$/d' | sort -u)
if [[ -z "$missing_doc" ]]; then
    pass "every required variable is documented in provision.env.example"
else
    fail "every required variable is documented in provision.env.example" "missing: $missing_doc"
fi

# --------------------------------------------------------------------------
echo "9. Optional live MySQL grants check"
# --------------------------------------------------------------------------

if [[ -n "${OPS_TEST_MYSQL_CMD:-}" ]]; then
    read -r -a mysql_cmd <<<"$OPS_TEST_MYSQL_CMD"
    live_names() {
        export CENTRAL_DB_NAME="opstest_central"
        export TENANT_DB_PREFIX="opstest_tenant_"
        # Docker/CI clients do not connect as 'localhost': allow an override.
        export MYSQL_USER_HOST="${OPS_TEST_MYSQL_USER_HOST:-localhost}"
        export MYSQL_APP_USER="opstest_app"
        export MYSQL_MIGRATOR_USER="opstest_migrator"
        export MYSQL_PROVISIONER_USER="opstest_provisioner"
        export MYSQL_BACKUP_USER="opstest_backup"
        export MYSQL_AUDIT_PRUNER_USER="opstest_pruner"
        export CENTRAL_AUDIT_TABLES="central_audit_logs activity_log"
        export CENTRAL_APPEND_ONLY_TABLES="tenant_credit_ledger"
    }
    # as_user USER PASSWORD SQL -> runs SQL as an application account (password via env, not argv).
    # Output is captured first and CRs stripped (native Windows mysql.exe under
    # Git Bash can drop piped output and prints CRLF).
    as_user() {
        local out
        out="$(MYSQL_PWD="$2" "${mysql_cmd[@]}" -u"$1" -N -B -e "$3")" || return 1
        if [[ -n "$out" ]]; then
            printf '%s\n' "$out" | tr -d '\r'
        fi
    }
    expect_denied() {
        local label="$1"
        shift
        if as_user "$@" >/dev/null 2>&1; then
            echo "NOT DENIED: $label"
            return 1
        fi
    }
    live_cleanup() {
        local user
        for user in "$MYSQL_APP_USER" "$MYSQL_MIGRATOR_USER" "$MYSQL_PROVISIONER_USER" "$MYSQL_BACKUP_USER" "$MYSQL_AUDIT_PRUNER_USER"; do
            "${mysql_cmd[@]}" -e "DROP USER IF EXISTS \`$user\`@\`$MYSQL_USER_HOST\`"
        done
        "${mysql_cmd[@]}" -e "DROP USER IF EXISTS \`opstest_tenant_user\`@\`%\`"
        "${mysql_cmd[@]}" -e "DROP DATABASE IF EXISTS \`opstest_tenant_probe\`"
        "${mysql_cmd[@]}" -e "DROP DATABASE IF EXISTS \`opstest_tenant-probe\`"
        "${mysql_cmd[@]}" -e "DROP DATABASE IF EXISTS \`$CENTRAL_DB_NAME\`"
    }
    # Not `( ... ) && ok || ko`: bash ignores `set -e` inside a subshell that is
    # part of an && / || list, so a failing check in the middle would be missed.
    set +e
    (
        set -e
        live_names
        export_fixture_passwords
        trap live_cleanup EXIT
        build_users_sql apply | "${mysql_cmd[@]}"
        # Applying twice must be idempotent.
        build_users_sql apply | "${mysql_cmd[@]}"
        expected_grants_all | normalize_grants >"$WORK_DIR/live.expected"
        live_grants "${mysql_cmd[@]}" >"$WORK_DIR/live.actual"
        diff -u "$WORK_DIR/live.expected" "$WORK_DIR/live.actual"
        echo "grants: match"

        # stancl's PermissionControlledMySQLDatabaseManager sequence as the provisioner,
        # with the GRANT target escaped the way the OPS-2 manager must do it.
        P="$MYSQL_PROVISIONER_USER"
        PP="$MYSQL_PROVISIONER_PASSWORD"
        as_user "$P" "$PP" "CREATE DATABASE \`opstest_tenant_probe\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        as_user "$P" "$PP" "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'opstest_tenant_probe'" | grep -qx opstest_tenant_probe
        as_user "$P" "$PP" "SELECT count(*) FROM mysql.user WHERE user = 'opstest_tenant_user'" | grep -qx 0
        as_user "$P" "$PP" "CREATE USER \`opstest_tenant_user\`@\`%\` IDENTIFIED BY 'FixtureTenantPasswordOnly0000000'"
        TENANT_PRIVS="ALTER, ALTER ROUTINE, CREATE, CREATE ROUTINE, CREATE TEMPORARY TABLES, CREATE VIEW, DELETE, DROP, EVENT, EXECUTE, INDEX, INSERT, LOCK TABLES, REFERENCES, SELECT, SHOW VIEW, TRIGGER, UPDATE"
        # Fail closed: stock stancl's unescaped GRANT target is a wildcard
        # pattern (`_` = any char) and must be refused (ERROR 1044).
        expect_denied "provisioner grants an unescaped (wildcard) tenant target" "$P" "$PP" "GRANT $TENANT_PRIVS ON \`opstest_tenant_probe\`.* TO \`opstest_tenant_user\`@\`%\`"
        # What the OPS-2 escaping manager must run: `_` and `%` escaped.
        as_user "$P" "$PP" "GRANT $TENANT_PRIVS ON \`opstest\\_tenant\\_probe\`.* TO \`opstest_tenant_user\`@\`%\`"
        as_user "$P" "$PP" "SELECT count(*) FROM mysql.user WHERE user = 'opstest_tenant_user'" | grep -qx 1
        tenant_user_grants="$("${mysql_cmd[@]}" -N -B -r -e "SHOW GRANTS FOR \`opstest_tenant_user\`@\`%\`")"
        grep -qF 'ON `opstest\_tenant\_probe`.*' <<<"$tenant_user_grants"
        # A sibling DB whose name differs only by `_` vs `-` (slug `probe` vs a
        # look-alike) stays out of the tenant user's reach.
        "${mysql_cmd[@]}" -e "CREATE DATABASE \`opstest_tenant-probe\`"
        expect_denied "tenant user reads a look-alike sibling tenant DB" "opstest_tenant_user" "FixtureTenantPasswordOnly0000000" "SHOW TABLES FROM \`opstest_tenant-probe\`"
        echo "provisioner: escaped create sequence ok, unescaped GRANT refused, no cross-tenant match"

        # Least privilege: everything outside each role is refused.
        expect_denied "provisioner reads password hashes" "$P" "$PP" "SELECT authentication_string FROM mysql.user LIMIT 1"
        expect_denied "provisioner writes central" "$P" "$PP" "CREATE TABLE \`$CENTRAL_DB_NAME\`.probe (id INT)"
        expect_denied "app creates a database" "$MYSQL_APP_USER" "$MYSQL_APP_PASSWORD" "CREATE DATABASE \`opstest_tenant_nope\`"
        expect_denied "app changes central schema" "$MYSQL_APP_USER" "$MYSQL_APP_PASSWORD" "CREATE TABLE \`$CENTRAL_DB_NAME\`.probe (id INT)"
        expect_denied "app reads a tenant DB" "$MYSQL_APP_USER" "$MYSQL_APP_PASSWORD" "SHOW TABLES FROM \`opstest_tenant_probe\`"
        expect_denied "backup writes a tenant DB" "$MYSQL_BACKUP_USER" "$MYSQL_BACKUP_PASSWORD" "CREATE TABLE \`opstest_tenant_probe\`.probe (id INT)"
        expect_denied "migrator touches a tenant DB" "$MYSQL_MIGRATOR_USER" "$MYSQL_MIGRATOR_PASSWORD" "SHOW TABLES FROM \`opstest_tenant_probe\`"
        as_user "$MYSQL_MIGRATOR_USER" "$MYSQL_MIGRATOR_PASSWORD" "CREATE TABLE \`$CENTRAL_DB_NAME\`.probe (id INT); DROP TABLE \`$CENTRAL_DB_NAME\`.probe"
        as_user "$MYSQL_BACKUP_USER" "$MYSQL_BACKUP_PASSWORD" "SHOW TABLES FROM \`opstest_tenant_probe\`" >/dev/null
        expect_denied "audit_pruner reads anything before the audit tables exist" "$MYSQL_AUDIT_PRUNER_USER" "$MYSQL_AUDIT_PRUNER_PASSWORD" "SHOW TABLES FROM \`$CENTRAL_DB_NAME\`"
        echo "least privilege: ok"

        # Append-only audit (IDEN-1.15) through the deploy path: the migrator
        # (no MySQL root) syncs the per-table grants, each account verifies its own.
        C="\`$CENTRAL_DB_NAME\`"
        "${mysql_cmd[@]}" -e "CREATE TABLE $C.items (id INT PRIMARY KEY, v INT); CREATE TABLE $C.central_audit_logs (id INT PRIMARY KEY, created_at INT); CREATE TABLE $C.activity_log (id INT PRIMARY KEY, created_at INT); CREATE TABLE $C.tenant_credit_ledger (id INT PRIMARY KEY, created_at INT); CREATE TABLE $C.dropped_later (id INT)"
        live_host="127.0.0.1"
        live_port="3306"
        for arg in "${mysql_cmd[@]}"; do
            case "$arg" in
                -h?*) live_host="${arg#-h}" ;;
                -P?*) live_port="${arg#-P}" ;;
            esac
        done
        cnf_dir="$WORK_DIR/cnf"
        mkdir -p "$cnf_dir"
        write_mysql_client_file "$cnf_dir/migrator.cnf" "$MYSQL_MIGRATOR_USER" "$MYSQL_MIGRATOR_PASSWORD" "$live_host" "$live_port" migrator
        write_mysql_client_file "$cnf_dir/app.cnf" "$MYSQL_APP_USER" "$MYSQL_APP_PASSWORD" "$live_host" "$live_port" app
        write_mysql_client_file "$cnf_dir/pruner.cnf" "$MYSQL_AUDIT_PRUNER_USER" "$MYSQL_AUDIT_PRUNER_PASSWORD" "$live_host" "$live_port" audit_pruner
        sync_client() {
            OPS_MYSQL_BIN="${mysql_cmd[0]}" bash "$OPS_DIR/sync-central-grants.sh" --client-file "$cnf_dir/migrator.cnf" \
                --verify-app "$cnf_dir/app.cnf" --verify-pruner "$cnf_dir/pruner.cnf"
        }
        sync_client
        sync_client
        echo "sync-central-grants (client mode): applied twice, contract verified"
        A="$MYSQL_APP_PASSWORD"
        R="$MYSQL_AUDIT_PRUNER_PASSWORD"
        as_user "$MYSQL_APP_USER" "$A" "INSERT INTO $C.items VALUES (1,1); UPDATE $C.items SET v=2 WHERE id=1; DELETE FROM $C.items WHERE id=1"
        as_user "$MYSQL_APP_USER" "$A" "INSERT INTO $C.central_audit_logs VALUES (1,1); INSERT INTO $C.activity_log VALUES (1,1); SELECT COUNT(*) FROM $C.central_audit_logs" >/dev/null
        expect_denied "app updates central_audit_logs" "$MYSQL_APP_USER" "$A" "UPDATE $C.central_audit_logs SET created_at=5"
        expect_denied "app deletes central_audit_logs" "$MYSQL_APP_USER" "$A" "DELETE FROM $C.central_audit_logs"
        expect_denied "app updates activity_log" "$MYSQL_APP_USER" "$A" "UPDATE $C.activity_log SET created_at=5"
        expect_denied "app deletes activity_log" "$MYSQL_APP_USER" "$A" "DELETE FROM $C.activity_log"
        expect_denied "app truncates central_audit_logs" "$MYSQL_APP_USER" "$A" "TRUNCATE $C.central_audit_logs"
        as_user "$MYSQL_AUDIT_PRUNER_USER" "$R" "DELETE FROM $C.central_audit_logs WHERE created_at < 2; DELETE FROM $C.activity_log WHERE created_at < 2"
        expect_denied "audit_pruner updates an audit row" "$MYSQL_AUDIT_PRUNER_USER" "$R" "UPDATE $C.central_audit_logs SET created_at=9"
        expect_denied "audit_pruner inserts an audit row" "$MYSQL_AUDIT_PRUNER_USER" "$R" "INSERT INTO $C.central_audit_logs VALUES (7,7)"
        expect_denied "audit_pruner reads another central table" "$MYSQL_AUDIT_PRUNER_USER" "$R" "SELECT * FROM $C.items"
        # Append-only ledger (ENTI-1.10): app INSERT + SELECT only, the pruner never touches it.
        as_user "$MYSQL_APP_USER" "$A" "INSERT INTO $C.tenant_credit_ledger VALUES (1,1); SELECT COUNT(*) FROM $C.tenant_credit_ledger" >/dev/null
        expect_denied "app updates tenant_credit_ledger" "$MYSQL_APP_USER" "$A" "UPDATE $C.tenant_credit_ledger SET created_at=5"
        expect_denied "app deletes tenant_credit_ledger" "$MYSQL_APP_USER" "$A" "DELETE FROM $C.tenant_credit_ledger"
        expect_denied "audit_pruner deletes from tenant_credit_ledger" "$MYSQL_AUDIT_PRUNER_USER" "$R" "DELETE FROM $C.tenant_credit_ledger WHERE created_at < 2"
        # Drift repair: a hand-added UPDATE on an audit table and the stale grant
        # MySQL keeps after DROP TABLE both disappear on the next deploy sync.
        "${mysql_cmd[@]}" -e "GRANT UPDATE ON $C.central_audit_logs TO \`$MYSQL_APP_USER\`@\`$MYSQL_USER_HOST\`; DROP TABLE $C.dropped_later"
        sync_client
        expect_denied "app updates central_audit_logs after the drift repair" "$MYSQL_APP_USER" "$A" "UPDATE $C.central_audit_logs SET created_at=5"
        app_grants="$("${mysql_cmd[@]}" -N -B -r -e "SHOW GRANTS FOR \`$MYSQL_APP_USER\`@\`$MYSQL_USER_HOST\`")"
        if grep -q dropped_later <<<"$app_grants"; then
            echo "stale grant on a dropped table survived the sync"
            exit 1
        fi
        # IDEN-1.15 acceptance: SHOW GRANTS for app has no UPDATE/DELETE on the audit tables.
        if grep -E '(UPDATE|DELETE).*(central_audit_logs|activity_log|tenant_credit_ledger)' <<<"$app_grants"; then
            echo "app holds UPDATE/DELETE on an audit table"
            exit 1
        fi
        echo "append-only audit: app INSERT-only on audit tables, pruner SELECT+DELETE only, drift repaired"

        # stancl deleteUser / deleteDatabase.
        as_user "$P" "$PP" "DROP USER IF EXISTS 'opstest_tenant_user'"
        as_user "$P" "$PP" "DROP DATABASE \`opstest_tenant_probe\`"
        echo "provisioner: stancl delete sequence ok"
    ) >"$WORK_DIR/live.out" 2>&1
    live_rc=$?
    set -e
    if [[ "$live_rc" -eq 0 ]]; then
        pass "live: SHOW GRANTS == expected (applied twice), stancl create/delete as provisioner, least-privilege denials, append-only audit grants via the migrator"
    else
        fail "live MySQL checks" "$(tail -40 "$WORK_DIR/live.out")"
    fi
else
    printf '  skip  set OPS_TEST_MYSQL_CMD to run against a disposable MySQL 8\n'
fi

# --------------------------------------------------------------------------
echo
echo "passed: $PASS  failed: $FAIL"
if [[ "$FAIL" -gt 0 ]]; then
    printf 'failed tests:\n'
    printf '  - %s\n' "${FAILED_NAMES[@]}"
    exit 1
fi
