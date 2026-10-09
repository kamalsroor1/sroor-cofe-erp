#!/usr/bin/env bash
# Keeps the per-table grants on the CENTRAL database in line with the
# append-only audit contract (IDEN-1.15, vps-runbook.md §5.1):
#
#   app           UPDATE, DELETE on every central table EXCEPT the audit tables
#                 and the append-only ledgers (SELECT, INSERT come from the
#                 database-level grant)
#   audit_pruner  SELECT, DELETE on the audit tables only (never on a ledger)
#
# MySQL table grants need the table to exist, so this runs after every central
# migration. Idempotent; prints table names and grants only, never a secret.
#
#   As root on the server (step 41, manual repair):
#     bash sync-central-grants.sh --as-root [--check-only]
#   From the deploy (no MySQL root; the migrator holds GRANT OPTION on central):
#     bash sync-central-grants.sh --client-file MIGRATOR.cnf \
#         --verify-app APP.cnf --verify-pruner PRUNER.cnf
#
# The *.cnf files are MySQL [client] option files (mode 600) written by
# deploy.sh, so no password is ever in argv. With --verify-app/--verify-pruner
# each account's own `SHOW GRANTS` must equal the contract exactly, otherwise
# the script fails (and the deploy stops before the release switch).
#
# Settings (environment, defaults in lib/common.sh): CENTRAL_DB_NAME,
# MYSQL_USER_HOST, MYSQL_APP_USER, MYSQL_AUDIT_PRUNER_USER, CENTRAL_AUDIT_TABLES,
# CENTRAL_APPEND_ONLY_TABLES (financial ledgers, ENTI-1.10: tenant_credit_ledger).
# Exit: 0 in sync, 1 drift / error, 2 usage.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"
# shellcheck source=lib/mysql-grants.sh
source "$SCRIPT_DIR/lib/mysql-grants.sh"

usage() {
    cat >&2 <<'EOF'
usage: sync-central-grants.sh --as-root [--check-only]
       sync-central-grants.sh --client-file FILE [--verify-app FILE] [--verify-pruner FILE] [--check-only]
EOF
    exit 2
}

MODE=""
CLIENT_FILE=""
APP_FILE=""
PRUNER_FILE=""
CHECK_ONLY=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --as-root) MODE="root" ;;
        --client-file)
            [[ $# -ge 2 ]] || usage
            MODE="client"
            CLIENT_FILE="$2"
            shift
            ;;
        --verify-app)
            [[ $# -ge 2 ]] || usage
            APP_FILE="$2"
            shift
            ;;
        --verify-pruner)
            [[ $# -ge 2 ]] || usage
            PRUNER_FILE="$2"
            shift
            ;;
        --check-only) CHECK_ONLY=1 ;;
        *) usage ;;
    esac
    shift
done
[[ -n "$MODE" ]] || usage

apply_defaults
validate_mysql_names || exit 1

MYSQL_BIN="${OPS_MYSQL_BIN:-mysql}"

# Option-file path as the mysql client sees it. Only matters for local test
# runs under Git Bash with a native Windows mysql.exe; a no-op on Linux.
client_path() {
    if command -v cygpath >/dev/null 2>&1; then
        cygpath -m "$1"
    else
        printf '%s' "$1"
    fi
}

if [[ "$MODE" == "root" ]]; then
    require_root
    ADMIN=("$MYSQL_BIN" --protocol=socket -uroot -N -B -r)
else
    for f in "$CLIENT_FILE" ${APP_FILE:+"$APP_FILE"} ${PRUNER_FILE:+"$PRUNER_FILE"}; do
        [[ -f "$f" ]] || die "option file not found: $f"
    done
    ADMIN=("$MYSQL_BIN" "--defaults-extra-file=$(client_path "$CLIENT_FILE")" -N -B -r)
fi

# query SQL -> rows, captured first (a native Windows mysql.exe can drop
# output written straight into a pipe; harmless elsewhere).
query() {
    local out
    out="$("${ADMIN[@]}" -e "$1")" || return 1
    printf '%s\n' "$out" | tr -d '\r'
}

# grants_of USER OPTION_FILE -> raw grant lines of one account. Root reads
# them directly; otherwise the account reads its OWN grants with its own
# credentials (the migrator cannot read mysql.user).
grants_of() {
    local user="$1" own_file="$2" out
    if [[ "$MODE" == "root" ]]; then
        query "SHOW GRANTS FOR \`$user\`@\`$MYSQL_USER_HOST\`"
    else
        out="$("$MYSQL_BIN" "--defaults-extra-file=$(client_path "$own_file")" -N -B -r -e "SHOW GRANTS")" || return 1
        printf '%s\n' "$out" | tr -d '\r'
    fi
}

# expected_for USER -> the exact contract for one account, normalized.
expected_for() {
    local user="$1" acct
    acct="$(_acct "$user")"
    {
        expected_grants_all | { grep -F -- " TO $acct" || [[ $? -eq 1 ]]; }
        expected_table_grants <"$TABLES_FILE" | { grep -F -- " TO $acct" || [[ $? -eq 1 ]]; }
    } | normalize_grants
}

# stale_revokes USER RAW_GRANTS_FILE -> REVOKE statements for per-table grants
# on central tables that no longer exist (MySQL keeps them after DROP TABLE).
stale_revokes() {
    local user="$1" raw="$2" line table
    local re="ON \`${CENTRAL_DB_NAME}\`\\.\`([A-Za-z0-9_]+)\` TO "
    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"
        [[ "$line" =~ $re ]] || continue
        table="${BASH_REMATCH[1]}"
        if ! grep -qxF -- "$table" "$TABLES_FILE"; then
            printf 'REVOKE IF EXISTS ALL PRIVILEGES ON `%s`.`%s` FROM %s;\n' "$CENTRAL_DB_NAME" "$table" "$(_acct "$user")"
        fi
    done <"$raw"
}

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
TABLES_FILE="$WORK/tables"

query "$(central_tables_sql)" | { grep -v '^$' || [[ $? -eq 1 ]]; } >"$TABLES_FILE"
read_table_names <"$TABLES_FILE"
table_count=${#CENTRAL_TABLES[@]}
audit_present=0
for t in "${CENTRAL_TABLES[@]}"; do
    if is_audit_table "$t"; then
        audit_present=$((audit_present + 1))
    fi
done
info "central DB $CENTRAL_DB_NAME: $table_count table(s), $audit_present audit table(s) present"

# Accounts this run can see: root sees both; the client mode only those whose
# own option file was given.
ACCOUNTS=()
declare -A OWN_FILE=()
if [[ "$MODE" == "root" || -n "$APP_FILE" ]]; then
    ACCOUNTS+=("$MYSQL_APP_USER")
    OWN_FILE["$MYSQL_APP_USER"]="$APP_FILE"
fi
if [[ "$MODE" == "root" || -n "$PRUNER_FILE" ]]; then
    ACCOUNTS+=("$MYSQL_AUDIT_PRUNER_USER")
    OWN_FILE["$MYSQL_AUDIT_PRUNER_USER"]="$PRUNER_FILE"
fi

if [[ "$CHECK_ONLY" -eq 0 ]]; then
    {
        for user in ${ACCOUNTS[@]+"${ACCOUNTS[@]}"}; do
            grants_of "$user" "${OWN_FILE[$user]}" >"$WORK/raw-$user"
            stale_revokes "$user" "$WORK/raw-$user"
        done
        build_table_grants_sql <"$TABLES_FILE"
    } >"$WORK/sync.sql"
    "${ADMIN[@]}" <"$WORK/sync.sql"
    info "per-table grants applied ($(grep -c '^GRANT ' "$WORK/sync.sql") GRANT, $(grep -c '^REVOKE ' "$WORK/sync.sql") REVOKE statements)"
fi

if [[ ${#ACCOUNTS[@]} -eq 0 ]]; then
    warn "no account verified (pass --verify-app / --verify-pruner)"
    exit 0
fi

DRIFT=0
for user in "${ACCOUNTS[@]}"; do
    expected_for "$user" >"$WORK/expected-$user"
    grants_of "$user" "${OWN_FILE[$user]}" | normalize_grants >"$WORK/actual-$user"
    if diff -u "$WORK/expected-$user" "$WORK/actual-$user" >"$WORK/diff-$user"; then
        info "grants of $user match the contract"
    else
        DRIFT=1
        warn "grants of $user differ from the contract (- expected, + actual):"
        grep -E '^[-+]GRANT ' "$WORK/diff-$user" >&2 || [[ $? -eq 1 ]]
    fi
done

# The two literal acceptance checks of IDEN-1.15.
app_actual="$WORK/actual-$MYSQL_APP_USER"
if [[ -f "$app_actual" ]]; then
    for t in $CENTRAL_AUDIT_TABLES; do
        if grep -E "(UPDATE|DELETE|ALL PRIVILEGES).* ON \`${CENTRAL_DB_NAME}\`\\.\`${t}\` " "$app_actual" >/dev/null; then
            DRIFT=1
            warn "app account can UPDATE/DELETE the audit table $t"
        fi
    done
    for t in $CENTRAL_APPEND_ONLY_TABLES; do
        if grep -E "(UPDATE|DELETE|ALL PRIVILEGES).* ON \`${CENTRAL_DB_NAME}\`\\.\`${t}\` " "$app_actual" >/dev/null; then
            DRIFT=1
            warn "app account can UPDATE/DELETE the append-only ledger $t"
        fi
    done
    if grep -E "(UPDATE|DELETE|ALL PRIVILEGES).* ON \`${CENTRAL_DB_NAME}\`\\.\\* " "$app_actual" >/dev/null; then
        DRIFT=1
        warn "app account holds UPDATE/DELETE on the whole central DB (audit tables included)"
    fi
fi

if [[ "$DRIFT" -ne 0 ]]; then
    die "central grants are NOT append-only compliant (fix: bash scripts/ops/steps/41-mysql-users.sh as root)"
fi
info "central grants OK: app has no UPDATE/DELETE on: $CENTRAL_AUDIT_TABLES $CENTRAL_APPEND_ONLY_TABLES"
