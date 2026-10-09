#!/usr/bin/env bash
# MySQL least-privilege accounts for the SaaS (OPS-1).
#
# Single source of truth for the grants. docs/07-operations/vps-runbook.md
# contains the same grants literally; scripts/ops/tests/run-tests.sh fails if
# the two ever drift, and verify.sh compares them with live SHOW GRANTS.
#
# Accounts (all @MYSQL_USER_HOST, default 'localhost' = unix socket):
#   app          runtime web/queue user: SELECT + INSERT on the central DB, plus
#                table-level UPDATE + DELETE on every central table EXCEPT the
#                append-only audit tables (CENTRAL_AUDIT_TABLES, IDEN-1.15)
#                and the append-only financial ledgers
#                (CENTRAL_APPEND_ONLY_TABLES, ENTI-1.10: tenant_credit_ledger).
#                Tenant DBs are reached with the per-tenant users stancl
#                creates (OPS-2).
#   migrator     central schema migrations during deploy (OPS-3) only. Holds
#                GRANT OPTION on the central DB so the deploy can hand the
#                table-level grants of new tables to app / audit_pruner
#                (scripts/ops/sync-central-grants.sh) without MySQL root.
#   audit_pruner the 2-year retention prune of the audit tables (IDEN-1.15,
#                Laravel connection `audit_pruner`, env DB_AUDIT_PRUNER_*):
#                SELECT + DELETE on the audit tables only, nothing else.
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

# Append-only audit (IDEN-1.15, W1 Q2). MySQL has no "everything in the DB
# except table X" grant (partial revokes only work on global grants), so:
#   - app holds SELECT + INSERT at database level,
#   - and UPDATE + DELETE per table on every central table that is NOT an
#     audit table. Table grants need the table to exist, so they are synced
#     after every central migration (sync-central-grants.sh, called by
#     deploy.sh as the migrator and by step 41 as root).
#   - audit_pruner holds SELECT + DELETE on the audit tables only. SELECT is
#     required by MySQL for `DELETE ... WHERE created_at < ?` (columns read in
#     a WHERE clause need SELECT); without it the prune fails with ERROR 1143.
#   - CENTRAL_APPEND_ONLY_TABLES (financial ledgers: tenant_credit_ledger,
#     ENTI-1.10): app gets no UPDATE/DELETE either, and they are NEVER pruned:
#     audit_pruner gets no grant on them.
APP_CENTRAL_DB_PRIVILEGES="SELECT, INSERT"
APP_TABLE_PRIVILEGES="UPDATE, DELETE"
AUDIT_PRUNER_TABLE_PRIVILEGES="SELECT, DELETE"

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
    : "${MYSQL_AUDIT_PRUNER_USER:=sroor_audit_pruner}"
    : "${CENTRAL_AUDIT_TABLES:=central_audit_logs activity_log}"
    : "${CENTRAL_APPEND_ONLY_TABLES:=tenant_credit_ledger}"

    local var
    for var in CENTRAL_DB_NAME TENANT_DB_PREFIX MYSQL_APP_USER MYSQL_MIGRATOR_USER MYSQL_PROVISIONER_USER MYSQL_BACKUP_USER MYSQL_AUDIT_PRUNER_USER; do
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
    local table
    [[ -n "${CENTRAL_AUDIT_TABLES// /}" ]] || {
        printf 'ERROR CENTRAL_AUDIT_TABLES must list at least one table\n' >&2
        return 1
    }
    for table in $CENTRAL_AUDIT_TABLES; do
        [[ "$table" =~ ^[a-z][a-z0-9_]{0,63}$ ]] || {
            printf 'ERROR CENTRAL_AUDIT_TABLES entries must match ^[a-z][a-z0-9_]{0,63}$\n' >&2
            return 1
        }
    done
    for table in $CENTRAL_APPEND_ONLY_TABLES; do
        [[ "$table" =~ ^[a-z][a-z0-9_]{0,63}$ ]] || {
            printf 'ERROR CENTRAL_APPEND_ONLY_TABLES entries must match ^[a-z][a-z0-9_]{0,63}$\n' >&2
            return 1
        }
        if is_audit_table "$table"; then
            printf 'ERROR %s is listed in both CENTRAL_AUDIT_TABLES and CENTRAL_APPEND_ONLY_TABLES\n' "$table" >&2
            return 1
        fi
    done
    local dupes
    dupes="$(printf '%s\n' "$MYSQL_APP_USER" "$MYSQL_MIGRATOR_USER" "$MYSQL_PROVISIONER_USER" "$MYSQL_BACKUP_USER" "$MYSQL_AUDIT_PRUNER_USER" | sort | uniq -d)"
    if [[ -n "$dupes" ]]; then
        printf 'ERROR the five MySQL account names must be distinct\n' >&2
        return 1
    fi
    if [[ " $MYSQL_APP_USER $MYSQL_MIGRATOR_USER $MYSQL_PROVISIONER_USER $MYSQL_BACKUP_USER $MYSQL_AUDIT_PRUNER_USER " == *" root "* ]]; then
        printf 'ERROR root must not be one of the application accounts\n' >&2
        return 1
    fi
}

# Prints the static (provisioning-time) grants exactly as `SHOW GRANTS`
# reports them on MySQL 8.4 (read them with `mysql -N -B -r`: without -r batch
# mode doubles backslashes). The per-table central grants are listed by
# expected_table_grants, because they depend on the tables that exist.
expected_grants_all() {
    validate_mysql_names
    local central="\`$CENTRAL_DB_NAME\`.*"
    local tenants
    tenants="\`$(tenant_db_pattern)\`.*"
    local app migrator provisioner backup pruner
    app="$(_acct "$MYSQL_APP_USER")"
    migrator="$(_acct "$MYSQL_MIGRATOR_USER")"
    provisioner="$(_acct "$MYSQL_PROVISIONER_USER")"
    backup="$(_acct "$MYSQL_BACKUP_USER")"
    pruner="$(_acct "$MYSQL_AUDIT_PRUNER_USER")"

    printf '%s\n' \
        "GRANT USAGE ON *.* TO $app" \
        "GRANT $APP_CENTRAL_DB_PRIVILEGES ON $central TO $app" \
        "GRANT USAGE ON *.* TO $migrator" \
        "GRANT $MIGRATOR_PRIVILEGES ON $central TO $migrator WITH GRANT OPTION" \
        "GRANT CREATE USER ON *.* TO $provisioner" \
        "GRANT ALL PRIVILEGES ON $tenants TO $provisioner WITH GRANT OPTION" \
        "GRANT SELECT (\`Host\`, \`User\`) ON \`mysql\`.\`user\` TO $provisioner" \
        "GRANT PROCESS ON *.* TO $backup" \
        "GRANT $BACKUP_DB_PRIVILEGES ON $central TO $backup" \
        "GRANT $BACKUP_DB_PRIVILEGES ON $tenants TO $backup" \
        "GRANT USAGE ON *.* TO $pruner"
}

# is_audit_table NAME -> 0 when NAME is one of CENTRAL_AUDIT_TABLES.
is_audit_table() {
    local name="$1" table
    for table in $CENTRAL_AUDIT_TABLES; do
        [[ "$name" == "$table" ]] && return 0
    done
    return 1
}

# is_append_only_table NAME -> 0 when NAME is one of CENTRAL_APPEND_ONLY_TABLES
# (no UPDATE/DELETE for app, no grant at all for audit_pruner).
is_append_only_table() {
    local name="$1" table
    for table in ${CENTRAL_APPEND_ONLY_TABLES:-}; do
        [[ "$name" == "$table" ]] && return 0
    done
    return 1
}

# read_table_names -> validated central table names from stdin (one per line)
# into the array CENTRAL_TABLES. Refuses anything that is not a plain
# identifier, so a name can never break out of the backticks in the SQL.
read_table_names() {
    CENTRAL_TABLES=()
    local name
    while IFS= read -r name || [[ -n "$name" ]]; do
        name="${name%$'\r'}"
        [[ -z "$name" ]] && continue
        if [[ ! "$name" =~ ^[A-Za-z0-9_]{1,64}$ ]]; then
            printf 'ERROR central table name is not a plain identifier (only [A-Za-z0-9_] is supported)\n' >&2
            return 1
        fi
        CENTRAL_TABLES+=("$name")
    done
}

# expected_table_grants < table-names -> the per-table grants on the central DB
# as SHOW GRANTS reports them: app gets UPDATE, DELETE on every table that is
# neither an audit table nor an append-only ledger; audit_pruner gets SELECT,
# DELETE on every audit table that exists; nobody gets a table grant on an
# append-only ledger (CENTRAL_APPEND_ONLY_TABLES).
expected_table_grants() {
    validate_mysql_names || return 1
    read_table_names || return 1
    local app pruner table
    app="$(_acct "$MYSQL_APP_USER")"
    pruner="$(_acct "$MYSQL_AUDIT_PRUNER_USER")"
    for table in "${CENTRAL_TABLES[@]}"; do
        if is_append_only_table "$table"; then
            continue
        elif is_audit_table "$table"; then
            printf 'GRANT %s ON `%s`.`%s` TO %s\n' "$AUDIT_PRUNER_TABLE_PRIVILEGES" "$CENTRAL_DB_NAME" "$table" "$pruner"
        else
            printf 'GRANT %s ON `%s`.`%s` TO %s\n' "$APP_TABLE_PRIVILEGES" "$CENTRAL_DB_NAME" "$table" "$app"
        fi
    done
}

# build_table_grants_sql < table-names -> idempotent SQL that brings the
# per-table grants to expected_table_grants. On audit tables every privilege
# of app and audit_pruner is revoked first (REVOKE IF EXISTS, MySQL >= 8.0.30),
# so a grant added by hand disappears on the next deploy; on append-only
# ledgers both accounts lose every table privilege and get none back. Runs as
# root (step 41) or as the migrator (deploy.sh; GRANT OPTION on the central DB).
# Contains no secret.
build_table_grants_sql() {
    validate_mysql_names || return 1
    read_table_names || return 1
    local app pruner table
    app="$(_acct "$MYSQL_APP_USER")"
    pruner="$(_acct "$MYSQL_AUDIT_PRUNER_USER")"
    printf -- '-- generated by scripts/ops (IDEN-1.15 append-only audit grants)\n'
    for table in "${CENTRAL_TABLES[@]}"; do
        if is_append_only_table "$table"; then
            printf 'REVOKE IF EXISTS ALL PRIVILEGES ON `%s`.`%s` FROM %s;\n' "$CENTRAL_DB_NAME" "$table" "$app"
            printf 'REVOKE IF EXISTS ALL PRIVILEGES ON `%s`.`%s` FROM %s;\n' "$CENTRAL_DB_NAME" "$table" "$pruner"
        elif is_audit_table "$table"; then
            printf 'REVOKE IF EXISTS ALL PRIVILEGES ON `%s`.`%s` FROM %s;\n' "$CENTRAL_DB_NAME" "$table" "$app"
            printf 'REVOKE IF EXISTS ALL PRIVILEGES ON `%s`.`%s` FROM %s;\n' "$CENTRAL_DB_NAME" "$table" "$pruner"
            printf 'GRANT %s ON `%s`.`%s` TO %s;\n' "$AUDIT_PRUNER_TABLE_PRIVILEGES" "$CENTRAL_DB_NAME" "$table" "$pruner"
        else
            printf 'GRANT %s ON `%s`.`%s` TO %s;\n' "$APP_TABLE_PRIVILEGES" "$CENTRAL_DB_NAME" "$table" "$app"
        fi
    done
}

# SQL that lists the central base tables (views need no table grants).
central_tables_sql() {
    printf "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '%s' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME" "$CENTRAL_DB_NAME"
}

# write_mysql_client_file FILE USER PASSWORD HOST PORT [ROLE] -> a [client]
# option file (mode 600), so a password never appears in argv or in the
# environment of child processes. Same password rule as provisioning (24+
# chars of [A-Za-z0-9], safe unquoted in an option file). Errors name the
# role only, never the value.
write_mysql_client_file() {
    local file="$1" user="$2" password="$3" host="$4" port="$5" role="${6:-account}"
    [[ "$user" =~ ^[a-z][a-z0-9_]{0,31}$ ]] || {
        printf 'ERROR %s user name must match ^[a-z][a-z0-9_]{0,31}$\n' "$role" >&2
        return 1
    }
    if [[ ${#password} -lt 24 || ! "$password" =~ ^[A-Za-z0-9]+$ ]]; then
        printf 'ERROR %s password must be at least 24 characters of [A-Za-z0-9] (openssl rand -hex 32)\n' "$role" >&2
        return 1
    fi
    [[ "$host" =~ ^[A-Za-z0-9.:-]+$ ]] || {
        printf 'ERROR %s host has invalid characters\n' "$role" >&2
        return 1
    }
    [[ "$port" =~ ^[0-9]{1,5}$ ]] || {
        printf 'ERROR %s port must be numeric\n' "$role" >&2
        return 1
    }
    (
        umask 077
        printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$user" "$password" "$host" "$port" >"$file"
    )
    chmod 600 "$file"
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

# build_users_sql apply|redact -> SQL that (re)creates the five accounts with
# exactly the expected static grants. REVOKE ALL also drops the per-table
# grants: step 41 re-applies them right after with build_table_grants_sql. Idempotent: safe to run again to rotate
# passwords or repair drifted grants. In `redact` mode passwords are replaced.
build_users_sql() {
    local mode="${1:-redact}"
    validate_mysql_names

    local user_var pass_var user password acct
    if [[ "$mode" == "apply" || "$mode" == "redact" ]]; then
        for pass_var in MYSQL_APP_PASSWORD MYSQL_MIGRATOR_PASSWORD MYSQL_PROVISIONER_PASSWORD MYSQL_BACKUP_PASSWORD MYSQL_AUDIT_PRUNER_PASSWORD; do
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

    for user_var in MYSQL_APP_USER MYSQL_MIGRATOR_USER MYSQL_PROVISIONER_USER MYSQL_BACKUP_USER MYSQL_AUDIT_PRUNER_USER; do
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

# _all_grants MYSQL_CMD... -> raw SHOW GRANTS of the five accounts. Output is
# captured before printing: a native Windows mysql.exe (local test runs under
# Git Bash) can drop output written straight into a pipe or file.
_all_grants() {
    local user out
    for user in "$MYSQL_APP_USER" "$MYSQL_MIGRATOR_USER" "$MYSQL_PROVISIONER_USER" "$MYSQL_BACKUP_USER" "$MYSQL_AUDIT_PRUNER_USER"; do
        out="$("$@" -N -B -r -e "SHOW GRANTS FOR \`$user\`@\`$MYSQL_USER_HOST\`")" || return 1
        printf '%s\n' "$out"
    done
}

# Marker of a table-level grant on the central DB:  ON `central`.`
_central_table_target() {
    printf ' ON `%s`.`' "$CENTRAL_DB_NAME"
}

# live_grants MYSQL_CMD... -> static SHOW GRANTS of the five accounts
# (per-table central grants excluded), normalized.
live_grants() {
    validate_mysql_names
    local target
    target="$(_central_table_target)"
    _all_grants "$@" | { grep -vF -- "$target" || [[ $? -eq 1 ]]; } | normalize_grants
}

# live_table_grants MYSQL_CMD... -> only the per-table central grants, normalized.
live_table_grants() {
    validate_mysql_names
    local target
    target="$(_central_table_target)"
    _all_grants "$@" | { grep -F -- "$target" || [[ $? -eq 1 ]]; } | normalize_grants
}
