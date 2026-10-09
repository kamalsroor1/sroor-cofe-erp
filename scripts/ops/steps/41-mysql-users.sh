#!/usr/bin/env bash
# Step 41: least-privilege MySQL accounts + central database. Idempotent:
# re-running rotates the passwords to the env values and repairs drifted grants
# (static grants + the per-table append-only audit grants, see
# sync-central-grants.sh). Run it while no deploy is in progress.
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
require_vars CENTRAL_DB_NAME TENANT_DB_PREFIX MYSQL_USER_HOST MYSQL_APP_USER MYSQL_MIGRATOR_USER MYSQL_PROVISIONER_USER MYSQL_BACKUP_USER MYSQL_AUDIT_PRUNER_USER CENTRAL_AUDIT_TABLES
require_secret MYSQL_APP_PASSWORD MYSQL_MIGRATOR_PASSWORD MYSQL_PROVISIONER_PASSWORD MYSQL_BACKUP_PASSWORD MYSQL_AUDIT_PRUNER_PASSWORD

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

# REVOKE ALL above also dropped the per-table grants on central (append-only
# audit contract, IDEN-1.15): re-apply them for the tables that exist now.
# Before the first deploy the central DB is empty and this is a no-op.
info "per-table central grants (app: no UPDATE/DELETE on $CENTRAL_AUDIT_TABLES $CENTRAL_APPEND_ONLY_TABLES)"
bash "$OPS_DIR/sync-central-grants.sh" --as-root

info "mysql users OK (5 accounts, grants match the runbook)"
