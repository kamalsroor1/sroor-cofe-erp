#!/usr/bin/env bash
# Step 41: least-privilege MySQL accounts + central database. Idempotent:
# re-running rotates the passwords to the env values and repairs drifted grants.
#
#   bash steps/41-mysql-users.sh                         apply (root, on the server)
#   bash steps/41-mysql-users.sh --print-expected-grants show the grants (no secrets needed)
#   bash steps/41-mysql-users.sh --print-sql             show the SQL with passwords redacted
#
# The grants are documented literally in docs/07-operations/vps-runbook.md §5.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"
source "$OPS_DIR/lib/mysql-grants.sh"

apply_defaults
MODE="${1:-apply}"

case "$MODE" in
    --print-expected-grants)
        expected_grants_all
        exit 0
        ;;
    --print-sql)
        build_users_sql redact
        exit 0
        ;;
    apply) ;;
    *) die "usage: $(basename "$0") [--print-expected-grants|--print-sql]" ;;
esac

require_root
require_vars CENTRAL_DB_NAME TENANT_DB_PREFIX MYSQL_USER_HOST MYSQL_APP_USER MYSQL_MIGRATOR_USER MYSQL_PROVISIONER_USER MYSQL_BACKUP_USER
require_secret MYSQL_APP_PASSWORD MYSQL_MIGRATOR_PASSWORD MYSQL_PROVISIONER_PASSWORD MYSQL_BACKUP_PASSWORD

MYSQL_ROOT=(mysql --protocol=socket -uroot -N -B)

info "applying accounts and grants (SQL goes through stdin, never argv or logs)"
build_users_sql apply | "${MYSQL_ROOT[@]}"

info "verifying SHOW GRANTS against the documented grants"
expected="$(expected_grants_all | normalize_grants)"
actual="$(live_grants "${MYSQL_ROOT[@]}")"
if [[ "$expected" != "$actual" ]]; then
    diff <(printf '%s\n' "$expected") <(printf '%s\n' "$actual") >&2 || [[ $? -eq 1 ]]
    die "SHOW GRANTS does not match the runbook"
fi

info "mysql users OK (4 accounts, grants match the runbook)"
