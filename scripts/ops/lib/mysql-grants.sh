#!/usr/bin/env bash
# MySQL least-privilege accounts for the SaaS (OPS-1).
#
# Single source of truth for the grants. docs/07-operations/vps-runbook.md
# contains the same grants literally; scripts/ops/tests/run-tests.sh fails if
# the two ever drift, and verify.sh compares them with live SHOW GRANTS.
#
# Accounts (all @MYSQL_USER_HOST, default 'localhost' = unix socket):
#   app          runtime web/queue user: DML on the central DB only. Tenant DBs
#                are reached with the per-tenant users stancl creates (OPS-2).
#   migrator     central schema migrations during deploy (OPS-3) only.
#   provisioner  creates tenant DBs + per-tenant users (stancl
#                PermissionControlledMySQLDatabaseManager, OPS-2). Needs every
#                privilege it hands out WITH GRANT OPTION on the strict
#                tenant pattern, CREATE USER globally, and read access to
#                mysql.user(Host, User) for stancl's userExists().
#   backup       read-only dumps (mysqldump --single-transaction) of central +
#                every tenant DB (OPS-5).

set -euo pipefail

# Privileges stancl grants to each tenant user (PermissionControlledMySQLDatabaseManager::$grants).
# They are exactly every database-level privilege MySQL 8 has, so MySQL stores
# and reports them as `ALL PRIVILEGES` at database level (verified on 8.4).
# The provisioner therefore holds `ALL PRIVILEGES ... WITH GRANT OPTION` on the
# tenant wildcard only - never on the central DB and never globally.
#
# FAIL CLOSED - every account uses the strict escaped pattern `tenant\_%`.
# In a GRANT target `_` is a one-character wildcard. Stock stancl runs
# `GRANT ... ON \`tenant_<id>\`.*` unescaped, and the tenant id is an
# alpha_dash slug, so tenant `shop_a`'s user would also match the DB of tenant
# `shop-a` (`tenant_shop-a`): full cross-tenant read/write/DROP. With the strict
# provisioner pattern MySQL refuses that unescaped GRANT (ERROR 1044, verified
# on MySQL 8.4) and accepts the escaped one (`tenant\_shop\_a`).
# BLOCKING for OPS-2: the tenant DB manager MUST escape `_` and `%` (and `\`)
# in the GRANT target before PermissionControlledMySQLDatabaseManager is used.
# Never widen this pattern to `tenant_%` to make stock stancl work.
# shellcheck disable=SC2034 # documentation + checked by tests/run-tests.sh
STANCL_TENANT_PRIVILEGES="SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER"
MIGRATOR_PRIVILEGES="SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW, TRIGGER"
BACKUP_DB_PRIVILEGES="SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER"

# Strict pattern: `tenant_` -> `tenant\_%` (prefix matched literally).
tenant_db_pattern() {
    local prefix="${TENANT_DB_PREFIX:-tenant_}"
    prefix="${prefix//_/\\_}"
    printf '%s%%' "$prefix"
}

_acct() {
    printf '`%s`@`%s`' "$1" "$MYSQL_USER_HOST"
}

validate_mysql_names() {
    : "${CENTRAL_DB_NAME:=sroor_central}"
    : "${TENANT_DB_PREFIX:=tenant_}"
    : "${MYSQL_USER_HOST:=localhost}"
    : "${MYSQL_APP_USER:=sroor_app}"
    : "${MYSQL_MIGRATOR_USER:=sroor_migrator}"
    : "${MYSQL_PROVISIONER_USER:=sroor_provisioner}"
    : "${MYSQL_BACKUP_USER:=sroor_backup}"

    local var
    for var in CENTRAL_DB_NAME TENANT_DB_PREFIX MYSQL_APP_USER MYSQL_MIGRATOR_USER MYSQL_PROVISIONER_USER MYSQL_BACKUP_USER; do
        [[ "${!var}" =~ ^[a-z][a-z0-9_]{0,31}$ ]] || {
            printf 'ERROR %s must match ^[a-z][a-z0-9_]{0,31}$\n' "$var" >&2
            return 1
        }
    done
    [[ "$MYSQL_USER_HOST" =~ ^[A-Za-z0-9.%_-]+$ ]] || {
        printf 'ERROR MYSQL_USER_HOST has invalid characters\n' >&2
        return 1
    }
    # Defence in depth: also reject names that would only overlap the tenant
    # prefix if `_` were treated as a wildcard (`tenantXcentral`).
    local prefix_glob="${TENANT_DB_PREFIX//_/?}"
    # shellcheck disable=SC2053
    if [[ "$CENTRAL_DB_NAME" == $prefix_glob* ]]; then
        printf 'ERROR CENTRAL_DB_NAME must not match the TENANT_DB_PREFIX pattern (tenant grants would cover the central DB)\n' >&2
        return 1
    fi
    case "$CENTRAL_DB_NAME" in
        mysql | sys | information_schema | performance_schema)
            printf 'ERROR CENTRAL_DB_NAME must not be a MySQL system schema\n' >&2
            return 1
            ;;
    esac
    local dupes
    dupes="$(printf '%s\n' "$MYSQL_APP_USER" "$MYSQL_MIGRATOR_USER" "$MYSQL_PROVISIONER_USER" "$MYSQL_BACKUP_USER" | sort | uniq -d)"
    if [[ -n "$dupes" ]]; then
        printf 'ERROR the four MySQL account names must be distinct\n' >&2
        return 1
    fi
    if [[ " $MYSQL_APP_USER $MYSQL_MIGRATOR_USER $MYSQL_PROVISIONER_USER $MYSQL_BACKUP_USER " == *" root "* ]]; then
        printf 'ERROR root must not be one of the application accounts\n' >&2
        return 1
    fi
}

# Prints the grants exactly as `SHOW GRANTS` reports them on MySQL 8
# (read them with `mysql -N -B -r`: without -r batch mode doubles backslashes).
expected_grants_all() {
    validate_mysql_names
    local central="\`$CENTRAL_DB_NAME\`.*"
    local tenants
    tenants="\`$(tenant_db_pattern)\`.*"
    local app migrator provisioner backup
    app="$(_acct "$MYSQL_APP_USER")"
    migrator="$(_acct "$MYSQL_MIGRATOR_USER")"
    provisioner="$(_acct "$MYSQL_PROVISIONER_USER")"
    backup="$(_acct "$MYSQL_BACKUP_USER")"

    printf '%s\n' \
        "GRANT USAGE ON *.* TO $app" \
        "GRANT SELECT, INSERT, UPDATE, DELETE ON $central TO $app" \
        "GRANT USAGE ON *.* TO $migrator" \
        "GRANT $MIGRATOR_PRIVILEGES ON $central TO $migrator" \
        "GRANT CREATE USER ON *.* TO $provisioner" \
        "GRANT ALL PRIVILEGES ON $tenants TO $provisioner WITH GRANT OPTION" \
        "GRANT SELECT (\`Host\`, \`User\`) ON \`mysql\`.\`user\` TO $provisioner" \
        "GRANT PROCESS ON *.* TO $backup" \
        "GRANT $BACKUP_DB_PRIVILEGES ON $central TO $backup" \
        "GRANT $BACKUP_DB_PRIVILEGES ON $tenants TO $backup"
}

# Canonical form for comparisons: privileges (and column lists) sorted, CR and
# trailing ';' removed, lines sorted. Reads GRANT lines on stdin.
normalize_grants() {
    tr -d '\r' | awk '
        function trim(s) { sub(/^[ \t]+/, "", s); sub(/[ \t;]+$/, "", s); return s }
        function sort_list(s, sep,    n, arr, i, j, t, out) {
            n = split(s, arr, sep)
            for (i = 1; i <= n; i++) arr[i] = trim(arr[i])
            for (i = 2; i <= n; i++) { t = arr[i]; j = i - 1; while (j > 0 && arr[j] > t) { arr[j + 1] = arr[j]; j-- } arr[j + 1] = t }
            out = arr[1]
            for (i = 2; i <= n; i++) out = out sep arr[i]
            return out
        }
        /^GRANT / {
            line = trim($0)
            wgo = ""
            if (line ~ / WITH GRANT OPTION$/) { wgo = " WITH GRANT OPTION"; sub(/ WITH GRANT OPTION$/, "", line) }
            on = index(line, " ON ")
            privs = substr(line, 7, on - 7)
            rest = substr(line, on)
            # split privileges on commas outside parentheses
            n = 0; depth = 0; cur = ""
            for (i = 1; i <= length(privs); i++) {
                c = substr(privs, i, 1)
                if (c == "(") depth++
                if (c == ")") depth--
                if (c == "," && depth == 0) { items[++n] = trim(cur); cur = ""; continue }
                cur = cur c
            }
            items[++n] = trim(cur)
            for (k = 1; k <= n; k++) {
                p = items[k]
                lp = index(p, "(")
                if (lp > 0) {
                    cols = substr(p, lp + 1, length(p) - lp - 1)
                    p = trim(substr(p, 1, lp - 1)) " (" sort_list(cols, ", ") ")"
                }
                items[k] = p
            }
            for (a = 2; a <= n; a++) { t = items[a]; b = a - 1; while (b > 0 && items[b] > t) { items[b + 1] = items[b]; b-- } items[b + 1] = t }
            out = items[1]
            for (k = 2; k <= n; k++) out = out ", " items[k]
            print "GRANT " out rest wgo
            delete items
        }
    ' | LC_ALL=C sort
}

# build_users_sql apply|redact -> SQL that (re)creates the four accounts with
# exactly the expected grants. Idempotent: safe to run again to rotate
# passwords or repair drifted grants. In `redact` mode passwords are replaced.
build_users_sql() {
    local mode="${1:-redact}"
    validate_mysql_names

    local user_var pass_var user password acct
    if [[ "$mode" == "apply" || "$mode" == "redact" ]]; then
        for pass_var in MYSQL_APP_PASSWORD MYSQL_MIGRATOR_PASSWORD MYSQL_PROVISIONER_PASSWORD MYSQL_BACKUP_PASSWORD; do
            local value="${!pass_var:-}"
            if [[ -z "$value" || ${#value} -lt 24 || ! "$value" =~ ^[A-Za-z0-9]+$ ]]; then
                printf 'ERROR %s must be at least 24 characters of [A-Za-z0-9] (openssl rand -hex 32)\n' "$pass_var" >&2
                return 1
            fi
        done
    else
        printf 'ERROR unknown mode: %s\n' "$mode" >&2
        return 1
    fi

    printf -- '-- generated by scripts/ops (OPS-1); passwords are never logged\n'
    printf 'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n' "$CENTRAL_DB_NAME"

    for user_var in MYSQL_APP_USER MYSQL_MIGRATOR_USER MYSQL_PROVISIONER_USER MYSQL_BACKUP_USER; do
        pass_var="${user_var%_USER}_PASSWORD"
        user="${!user_var}"
        if [[ "$mode" == "apply" ]]; then
            password="${!pass_var}"
        else
            password="<redacted:$pass_var>"
        fi
        acct="$(_acct "$user")"
        printf "CREATE USER IF NOT EXISTS %s IDENTIFIED BY '%s';\n" "$acct" "$password"
        printf "ALTER USER %s IDENTIFIED BY '%s';\n" "$acct" "$password"
        printf 'REVOKE ALL PRIVILEGES, GRANT OPTION FROM %s;\n' "$acct"
    done

    # The expected grants double as the GRANT statements (USAGE lines are implicit).
    expected_grants_all | grep -v '^GRANT USAGE ON ' | sed 's/$/;/'
}

# live_grants MYSQL_CMD... -> SHOW GRANTS of the four accounts, normalized.
live_grants() {
    validate_mysql_names
    local user
    for user in "$MYSQL_APP_USER" "$MYSQL_MIGRATOR_USER" "$MYSQL_PROVISIONER_USER" "$MYSQL_BACKUP_USER"; do
        "$@" -N -B -r -e "SHOW GRANTS FOR \`$user\`@\`$MYSQL_USER_HOST\`"
    done | normalize_grants
}
