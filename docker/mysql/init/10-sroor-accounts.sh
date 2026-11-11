#!/usr/bin/env bash
# LOCAL DEVELOPMENT ONLY. Creates the central database and the five least-privilege MySQL
# accounts of production (app, migrator, provisioner, backup, audit_pruner) with EXACTLY the
# production grants, by reusing scripts/ops/lib/mysql-grants.sh (build_users_sql), the single
# source of truth that provision.sh/verify.sh use on the VPS. Only the host part differs:
# `%` instead of `localhost`, because the app reaches MySQL over the compose network.
#
# Runs automatically ONCE, when the mysql volume is empty (docker-entrypoint-initdb.d, sourced:
# keep this file non-executable so docker_process_sql is available). Re-run it any time
# (idempotent: CREATE IF NOT EXISTS + ALTER USER + REVOKE ALL + GRANT) with
#   docker/bin/dev.sh db-accounts
# which also re-applies the per-table central grants afterwards (REVOKE ALL drops them).
#
# Account names and DEV-ONLY passwords come from backend/.env.docker (mounted read-only at
# /sroor/env), the same file the app reads. Nothing is printed except account names.

# /sroor/env = backend/.env.docker (0600, host user): readable by root (manual re-run).
# /tmp/sroor-env = the container-private copy the compose entrypoint makes for the `mysql` user.
if [[ -z "${SROOR_ENV_FILE:-}" ]]; then
    if [[ -r /sroor/env ]]; then SROOR_ENV_FILE=/sroor/env; else SROOR_ENV_FILE=/tmp/sroor-env; fi
fi
SROOR_OPS_LIB="${SROOR_OPS_LIB:-/sroor/ops-lib}"

sroor_env_value() {
    local key="$1" line
    line="$(grep -E "^${key}=" "$SROOR_ENV_FILE" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%$'\r'}"
    line="${line%\"}"
    line="${line#\"}"
    printf '%s' "$line"
}

sroor_create_accounts() {
    set -euo pipefail
    [[ -r "$SROOR_ENV_FILE" ]] || {
        echo "[sroor-init] ERROR $SROOR_ENV_FILE is missing (copy backend/.env.docker.example to backend/.env.docker)" >&2
        return 1
    }
    # shellcheck source=../../../scripts/ops/lib/mysql-grants.sh
    source "$SROOR_OPS_LIB/mysql-grants.sh"

    CENTRAL_DB_NAME="$(sroor_env_value DB_DATABASE)"
    TENANT_DB_PREFIX="$(sroor_env_value TENANT_DB_PREFIX)"
    MYSQL_USER_HOST='%'
    MYSQL_APP_USER="$(sroor_env_value DB_USERNAME)"
    MYSQL_APP_PASSWORD="$(sroor_env_value DB_PASSWORD)"
    MYSQL_MIGRATOR_USER="$(sroor_env_value DB_MIGRATOR_USERNAME)"
    MYSQL_MIGRATOR_PASSWORD="$(sroor_env_value DB_MIGRATOR_PASSWORD)"
    MYSQL_PROVISIONER_USER="$(sroor_env_value DB_PROVISIONER_USERNAME)"
    MYSQL_PROVISIONER_PASSWORD="$(sroor_env_value DB_PROVISIONER_PASSWORD)"
    MYSQL_BACKUP_USER="$(sroor_env_value DB_BACKUP_USERNAME)"
    MYSQL_BACKUP_PASSWORD="$(sroor_env_value DB_BACKUP_PASSWORD)"
    MYSQL_AUDIT_PRUNER_USER="$(sroor_env_value DB_AUDIT_PRUNER_USERNAME)"
    MYSQL_AUDIT_PRUNER_PASSWORD="$(sroor_env_value DB_AUDIT_PRUNER_PASSWORD)"
    : "${TENANT_DB_PREFIX:=tenant_}"

    local sql
    sql="$(build_users_sql apply)" || return 1
    # The ONE dev-only deviation from the production contract: with APP_ENV=local, stancl's
    # DatabaseTenancyBootstrapper first checks that the tenant DB exists through the CENTRAL
    # (app) connection (information_schema.SCHEMATA). The least-privilege app account cannot
    # see tenant schemas, so every tenant request would fail with "Database tenant_x does not
    # exist". SHOW DATABASES only reveals schema NAMES (no data, no tables). Production never
    # runs that check (APP_ENV=production) and never gets this grant.
    sql+=$'\n'"GRANT SHOW DATABASES ON *.* TO $(_acct "$MYSQL_APP_USER");"

    if declare -F docker_process_sql >/dev/null; then
        # First start: sourced by the image entrypoint (root over the init socket). The image's
        # helper is not `set -u` clean.
        set +u
        docker_process_sql --database=mysql <<<"$sql"
        set -u
    else
        # Manual re-run (docker compose exec mysql bash /docker-entrypoint-initdb.d/...).
        mysql --defaults-extra-file=<(printf '[client]\nuser=root\npassword=%s\n' "$(sroor_env_value SROOR_DEV_MYSQL_ROOT_PASSWORD)") \
            --protocol=socket <<<"$sql"
    fi
    echo "[sroor-init] central DB '$CENTRAL_DB_NAME' + accounts $MYSQL_APP_USER, $MYSQL_MIGRATOR_USER, $MYSQL_PROVISIONER_USER, $MYSQL_BACKUP_USER, $MYSQL_AUDIT_PRUNER_USER @'%' ready (tenant pattern $(tenant_db_pattern))"
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    sroor_create_accounts
else
    # Sourced by the entrypoint: run in a subshell so `set -e` and the sourced library
    # cannot leak into (or abort) the rest of the image's init sequence unexpectedly.
    ( sroor_create_accounts )
fi
