#!/usr/bin/env bash
# Read-only post-provisioning checks (OPS-1 acceptance). Changes nothing.
#
#   bash verify.sh --env-file /root/sroor-provision.env
#
# Prints PASS/FAIL per check (never a secret) and exits 1 if anything failed.
# The MySQL check is the acceptance test "SHOW GRANTS per user == runbook".
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"
# shellcheck source=lib/mysql-grants.sh
source "$SCRIPT_DIR/lib/mysql-grants.sh"

[[ "${1:-}" == "--env-file" && -n "${2:-}" ]] || die "usage: verify.sh --env-file FILE"
require_root
assert_private_file "$2"
load_env_file "$2"
apply_defaults
require_vars PLATFORM_DOMAIN APP_ROOT APP_USER ADMIN_USER SSH_PORT PHP_VERSION QUEUE_MODE

FAILURES=0
check() {
    local name="$1"
    shift
    if "$@" >/dev/null 2>&1; then
        printf 'PASS  %s\n' "$name"
    else
        printf 'FAIL  %s\n' "$name"
        FAILURES=$((FAILURES + 1))
    fi
}

sshd_effective() {
    local key="$1" expected="$2"
    # Capture first: `sshd -T | grep -q` lets grep exit early, sshd dies of
    # SIGPIPE and pipefail turns a match into a failure (keys near the top).
    local effective
    effective="$(sshd -T 2>/dev/null)" || return 1
    grep -qix "$key $expected" <<<"$effective"
}

listens_only_on_loopback() {
    local port="$1"
    local addrs
    addrs="$(ss -Hltn "sport = :$port" | awk '{print $4}')"
    [[ -n "$addrs" ]] || return 1
    ! grep -vqE '^(127\.0\.0\.1|\[::1\]):' <<<"$addrs"
}

php_has_extensions() {
    local ext
    for ext in bcmath intl pdo_mysql redis gd zip mbstring; do
        php -m | grep -qix "$ext" || return 1
    done
}

grants_match_runbook() {
    local expected actual
    expected="$(expected_grants_all | normalize_grants)"
    actual="$(live_grants mysql --protocol=socket -uroot -N -B)"
    if [[ "$expected" != "$actual" ]]; then
        diff <(printf '%s\n' "$expected") <(printf '%s\n' "$actual") >&2 || [[ $? -eq 1 ]]
        return 1
    fi
}

cert_valid_14_days() {
    openssl x509 -checkend $((14 * 86400)) -noout -in "/etc/letsencrypt/live/$PLATFORM_DOMAIN/fullchain.pem"
}

cert_covers_wildcard() {
    openssl x509 -noout -ext subjectAltName -in "/etc/letsencrypt/live/$PLATFORM_DOMAIN/fullchain.pem" |
        grep -qF "DNS:*.$PLATFORM_DOMAIN"
}

ufw_rules_ok() {
    local status
    status="$(ufw status verbose)"
    grep -q '^Status: active' <<<"$status" &&
        grep -q 'Default: deny (incoming)' <<<"$status" &&
        grep -qE "^$SSH_PORT/tcp +LIMIT IN" <<<"$status" &&
        grep -qE '^80/tcp +ALLOW IN' <<<"$status" &&
        grep -qE '^443/tcp +ALLOW IN' <<<"$status"
}

env_file_compliant() {
    bash "$SCRIPT_DIR/check-env.sh" "$APP_ROOT/shared/.env" --check-perms
}

mysql_is_84() {
    [[ "$(mysql --protocol=socket -uroot -N -B -e 'SELECT VERSION()')" == 8.4.* ]]
}

# BUG-07: the app runs its money/stock writes at READ COMMITTED (DB_WRITE_ISOLATION, default on;
# empty = server level). InnoDB refuses such writes when the binary log is ON with
# binlog_format=STATEMENT (error 1665: every sale would fail). Same rule as
# `php artisan db:check-write-isolation`, checked in SQL here because no release exists yet.
write_isolation_binlog_ok() {
    local isolation="READ COMMITTED" row log_bin format
    declare -A WI_ENV=()
    if [[ -s "$APP_ROOT/shared/.env" ]] && parse_env_file "$APP_ROOT/shared/.env" WI_ENV && [[ -v 'WI_ENV[DB_WRITE_ISOLATION]' ]]; then
        isolation="${WI_ENV[DB_WRITE_ISOLATION]}"
    fi
    [[ "${isolation^^}" == "READ COMMITTED" ]] || return 0
    row="$(mysql --protocol=socket -uroot -N -B -e 'SELECT @@GLOBAL.log_bin, @@GLOBAL.binlog_format')" || return 1
    read -r log_bin format <<<"$row"
    ! [[ ("$log_bin" == 1 || "${log_bin^^}" == ON) && "${format^^}" == STATEMENT ]]
}

php_is_expected() {
    [[ "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" == "$PHP_VERSION" ]]
}

# OPS-23: backup:tenants dumps with whatever `mysqldump` is on PATH. On many distributions that
# name is the MariaDB client (mariadb-dump): another INSERT layout and a MariaDB-only first line.
# Production pins both clients to Oracle MySQL 8.4, like the server (backup-restore.md section 1.1).
client_version() {
    # Empty output (not a failure) when the client is missing: the check below reports it.
    command -v "$1" >/dev/null 2>&1 || return 0
    "$1" --version 2>/dev/null | head -n 1
}

client_is_oracle_84() {
    local version
    version="$(client_version "$1")"
    [[ -n "$version" ]] || return 1
    ! grep -qi mariadb <<<"$version" && grep -qE "^$1 +Ver 8\.4\." <<<"$version"
}

central_grants_append_only() {
    bash "$SCRIPT_DIR/sync-central-grants.sh" --as-root --check-only
}

echo "== services"
for svc in nginx "php$PHP_VERSION-fpm" mysql redis-server supervisor cron fail2ban unattended-upgrades certbot.timer; do
    check "service active: $svc" systemctl is-active --quiet "$svc"
done

check "unattended-upgrades holds mysql-* (manual maintenance window)" bash -c "apt-config dump Unattended-Upgrade::Package-Blacklist | grep -qF '\"^mysql-\"'"

echo "== ssh"
check "sshd: passwordauthentication no" sshd_effective passwordauthentication no
check "sshd: kbdinteractiveauthentication no" sshd_effective kbdinteractiveauthentication no
check "sshd: permitrootlogin no" sshd_effective permitrootlogin no
check "sshd: port $SSH_PORT" sshd_effective port "$SSH_PORT"
check "admin $ADMIN_USER has an authorized key" test -s "$(getent passwd "$ADMIN_USER" | cut -d: -f6)/.ssh/authorized_keys"
check "app $APP_USER has an authorized key" test -s "$(getent passwd "$APP_USER" | cut -d: -f6)/.ssh/authorized_keys"
check "sudoers drop-in is valid" visudo -c -f /etc/sudoers.d/sroor-app

echo "== network"
check "ufw: deny incoming, allow $SSH_PORT/80/443 only" ufw_rules_ok
check "mysql listens on loopback only" listens_only_on_loopback 3306
check "redis listens on loopback only" listens_only_on_loopback 6379
check "mysql X protocol disabled" bash -c '! ss -Hltn "sport = :33060" | grep -q .'

echo "== php"
check "/usr/bin/php is PHP $PHP_VERSION" php_is_expected
check "php extensions: bcmath intl pdo_mysql redis gd zip mbstring" php_has_extensions
check "php-fpm config test" "php-fpm$PHP_VERSION" -t

echo "== mysql"
check "MySQL server is 8.4 LTS" mysql_is_84
for client in mysqldump mysql; do
    # Recorded for the backup runbook, never a secret: the client name and version string only.
    printf 'INFO  %s --version: %s\n' "$client" "$(client_version "$client")"
    check "$client client is Oracle MySQL 8.4 (not the MariaDB client)" client_is_oracle_84 "$client"
done
check "SHOW GRANTS of app/migrator/provisioner/backup/audit_pruner == runbook" grants_match_runbook
check "central database exists" mysql --protocol=socket -uroot -N -B -e "USE \`$CENTRAL_DB_NAME\`"
check "binlog_format is ROW/MIXED when log_bin is ON (READ COMMITTED writes, BUG-07)" write_isolation_binlog_ok
check "per-table central grants: app has no UPDATE/DELETE on $CENTRAL_AUDIT_TABLES $CENTRAL_APPEND_ONLY_TABLES" central_grants_append_only

echo "== tls + nginx"
check "certificate valid for 14+ days" cert_valid_14_days
check "certificate covers *.$PLATFORM_DOMAIN" cert_covers_wildcard
check "nginx config test" nginx -t

echo "== queue + scheduler"
check "scheduler cron installed" test -f /etc/cron.d/sroor-scheduler
check "supervisor program for $QUEUE_MODE installed" test -f "/etc/supervisor/conf.d/sroor-$QUEUE_MODE.conf"

echo "== app layout"
check "releases/ and shared/storage exist" test -d "$APP_ROOT/releases" -a -d "$APP_ROOT/shared/storage/logs"
if [[ -s "$APP_ROOT/shared/.env" ]]; then
    check "shared .env meets production standards (TELESCOPE_ENABLED=false, APP_DEBUG=false...)" env_file_compliant
    # [CTO-2026-11-11] A rehearsal .env passes the check above with waivers: say so loudly.
    declare -A SHARED_ENV=()
    if parse_env_file "$APP_ROOT/shared/.env" SHARED_ENV && [[ "${SHARED_ENV[RELEASE_REHEARSAL]:-}" == [Tt][Rr][Uu][Ee] ]]; then
        printf 'WARN  REHEARSAL MODE: shared .env has RELEASE_REHEARSAL=true (SMTP / Telegram / Google Drive waived, see check-env.sh output) - NOT production-ready\n'
    fi
else
    printf 'SKIP  shared .env is empty (filled by OPS-4)\n'
fi

echo
if [[ "$FAILURES" -gt 0 ]]; then
    printf '%d check(s) failed\n' "$FAILURES"
    exit 1
fi
echo "all checks passed"
