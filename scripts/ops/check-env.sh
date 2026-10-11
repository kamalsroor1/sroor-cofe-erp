#!/usr/bin/env bash
# Validates a production Laravel .env against the production standards
# (docs/01-overview/product-overview.md, vps-runbook.md §8) WITHOUT printing
# any value. Read-only. Exit 0 = compliant, 1 = violations, 2 = usage error.
#
#   bash scripts/ops/check-env.sh /var/www/sroor/shared/.env [--check-perms]
#
# Reused by the release pipeline (OPS-3, scripts/ops/deploy.sh preflight) as
# the gate for: TELESCOPE_ENABLED=false, BACKUP_ARCHIVE_PASSWORD set (CTO
# decision D4), the backup account + Google Drive destination (OPS-5),
# mandatory SMTP (W1 Q6), the audit_pruner account (IDEN-1.15) and the
# central hosts CENTRAL_DOMAIN / CENTRAL_ADMIN_DOMAINS / CENTRAL_PASSWORD_RESET_URL.
# WARN lines never fail the run.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"

usage() {
    printf 'usage: %s <env-file> [--check-perms]\n' "$(basename "$0")" >&2
    exit 2
}

[[ $# -ge 1 ]] || usage
ENV_FILE="$1"
CHECK_PERMS=0
if [[ "${2:-}" == "--check-perms" ]]; then
    CHECK_PERMS=1
elif [[ -n "${2:-}" ]]; then
    usage
fi

if [[ ! -f "$ENV_FILE" ]]; then
    printf 'FAIL  env file not found: %s\n' "$ENV_FILE"
    exit 1
fi

declare -A ENV_VALUES=()
if ! parse_env_file "$ENV_FILE" ENV_VALUES; then
    printf 'FAIL  %s is not a valid KEY=VALUE file\n' "$ENV_FILE"
    exit 1
fi

VIOLATIONS=0
WARNINGS=0

violation() {
    VIOLATIONS=$((VIOLATIONS + 1))
    printf 'FAIL  %s\n' "$1"
}

warning() {
    WARNINGS=$((WARNINGS + 1))
    printf 'WARN  %s\n' "$1"
}

# No subshells below: forks are slow on some hosts (Git Bash on Windows).
must_equal() {
    local key="$1" expected="$2" why="$3"
    local value="${ENV_VALUES[$key]:-}"
    if [[ "${value,,}" != "$expected" ]]; then
        violation "$key must be $expected ($why)"
    fi
}

must_be_set() {
    local key="$1" why="$2"
    if [[ -z "${ENV_VALUES[$key]:-}" ]]; then
        violation "$key must be set ($why)"
    fi
}

must_equal APP_ENV production "production standard #2"
must_equal APP_DEBUG false "production standard #2: no stack traces to clients"
must_equal TELESCOPE_ENABLED false "production standard #3: config/telescope.php defaults to true when missing"
must_equal CACHE_STORE redis "production standard #1: per-tenant cache via CacheTenancyBootstrapper"
must_equal QUEUE_CONNECTION redis "production standard #6: Horizon/supervisor"
must_equal SESSION_SECURE_COOKIE true "production standard #10: HTTPS-only cookies"

quick_login="${ENV_VALUES[QUICK_LOGIN_ENABLED]:-}"
case "${quick_login,,}" in
    "" | false | 0 | off | no) ;;
    *) violation "QUICK_LOGIN_ENABLED must be false or absent (testing-only feature)" ;;
esac

app_key="${ENV_VALUES[APP_KEY]:-}"
if [[ -z "$app_key" || "$app_key" != base64:* ]]; then
    violation "APP_KEY must be set (base64:...) - generate a fresh one, never reuse an old key"
fi

if [[ "${ENV_VALUES[APP_URL]:-}" != https://* ]]; then
    violation "APP_URL must start with https:// (production standard #10)"
fi

must_be_set DB_DATABASE "central database name"
must_be_set DB_USERNAME "least-privilege app account"
must_be_set DB_PASSWORD "fresh secret"
db_username="${ENV_VALUES[DB_USERNAME]:-}"
if [[ "${db_username,,}" == "root" ]]; then
    violation "DB_USERNAME must not be root (use the DML-only app account)"
fi
must_be_set REDIS_PASSWORD "Redis requires a password on the VPS"

# Append-only audit (IDEN-1.15): the prune runs with its own account.
must_be_set DB_AUDIT_PRUNER_USERNAME "audit retention account (vps-runbook.md §5.1)"
must_be_set DB_AUDIT_PRUNER_PASSWORD "fresh secret"
pruner_user="${ENV_VALUES[DB_AUDIT_PRUNER_USERNAME]:-}"
if [[ -n "$pruner_user" && ("${pruner_user,,}" == "root" || "$pruner_user" == "$db_username") ]]; then
    violation "DB_AUDIT_PRUNER_USERNAME must be its own account (not root, not DB_USERNAME)"
fi

# Central / platform hosts (IDEN-1.11). config/tenancy.php falls back to a
# hardcoded domain when CENTRAL_DOMAIN is empty; with CENTRAL_ADMIN_DOMAINS
# empty EnsureCentralContext 404s the whole super-admin console in production;
# with CENTRAL_PASSWORD_RESET_URL empty central reset mails are never sent.
central_domain="${ENV_VALUES[CENTRAL_DOMAIN]:-}"
if [[ -z "$central_domain" ]]; then
    violation "CENTRAL_DOMAIN must be set (platform root domain; tenants live on <slug>.<CENTRAL_DOMAIN>)"
elif [[ "$central_domain" == *"://"* || "$central_domain" == *"/"* || "$central_domain" == *" "* ]]; then
    violation "CENTRAL_DOMAIN must be a bare host name (no scheme, path or spaces)"
fi

declare -A ADMIN_HOSTS=()
admin_domains_raw="${ENV_VALUES[CENTRAL_ADMIN_DOMAINS]:-}"
IFS=',' read -r -a _admin_hosts <<<"$admin_domains_raw"
for _h in "${_admin_hosts[@]}"; do
    _h="${_h#"${_h%%[![:space:]]*}"}"
    _h="${_h%"${_h##*[![:space:]]}"}"
    if [[ -n "$_h" ]]; then ADMIN_HOSTS["${_h,,}"]=1; fi
done
if [[ ${#ADMIN_HOSTS[@]} -eq 0 ]]; then
    violation "CENTRAL_ADMIN_DOMAINS must be set (empty = every super-admin route returns 404 in production)"
fi

reset_url="${ENV_VALUES[CENTRAL_PASSWORD_RESET_URL]:-}"
if [[ -z "$reset_url" ]]; then
    violation "CENTRAL_PASSWORD_RESET_URL must be set (empty = central password-reset mails are never sent)"
elif [[ "$reset_url" != https://* ]]; then
    violation "CENTRAL_PASSWORD_RESET_URL must start with https://"
else
    reset_host="${reset_url#https://}"
    reset_host="${reset_host%%[/:?#]*}"
    if [[ -z "${ADMIN_HOSTS[${reset_host,,}]:-}" ]]; then
        violation "CENTRAL_PASSWORD_RESET_URL host must be one of CENTRAL_ADMIN_DOMAINS"
    fi
fi

# Mandatory SMTP (W1 Q6): password resets, 2FA and billing mails must leave the box.
must_equal MAIL_MAILER smtp "W1 Q6: SMTP is mandatory (Brevo or SES SMTP)"
must_be_set MAIL_HOST "SMTP host"
must_be_set MAIL_USERNAME "SMTP user"
must_be_set MAIL_PASSWORD "SMTP secret"
if [[ ! "${ENV_VALUES[MAIL_PORT]:-}" =~ ^[0-9]{2,5}$ ]]; then
    violation "MAIL_PORT must be set to a port number (SMTP)"
fi
if [[ "${ENV_VALUES[MAIL_FROM_ADDRESS]:-}" != *?@?* ]]; then
    violation "MAIL_FROM_ADDRESS must be set to an e-mail address"
fi

# Backups (OPS-5, CTO decision D4): no archive password = backups refuse to run.
backup_password="${ENV_VALUES[BACKUP_ARCHIVE_PASSWORD]:-}"
if [[ -z "$backup_password" ]]; then
    violation "BACKUP_ARCHIVE_PASSWORD must be set (D4: backups refuse to run without it)"
elif [[ ${#backup_password} -lt 24 ]]; then
    warning "BACKUP_ARCHIVE_PASSWORD is shorter than 24 characters (generate with: openssl rand -hex 32)"
fi

# Backups (OPS-5): dumps run with the read-only `sroor_backup` account and the
# archives must leave the server (production standard #8: Google Drive).
must_be_set DB_BACKUP_USERNAME "read-only mysqldump account (vps-runbook.md §5)"
must_be_set DB_BACKUP_PASSWORD "fresh secret"
backup_user="${ENV_VALUES[DB_BACKUP_USERNAME]:-}"
if [[ -n "$backup_user" && ("${backup_user,,}" == "root" || "$backup_user" == "$db_username") ]]; then
    violation "DB_BACKUP_USERNAME must be its own read-only account (not root, not DB_USERNAME)"
fi
backup_disks="${ENV_VALUES[BACKUP_TENANT_DISKS]:-${ENV_VALUES[BACKUP_DISKS]:-local}}"
backup_disks=",${backup_disks// /},"
if [[ "$backup_disks" == *",google,"* ]]; then
    must_be_set GOOGLE_DRIVE_CLIENT_ID "OAuth client of the backup Drive (backup-restore.md §2)"
    must_be_set GOOGLE_DRIVE_CLIENT_SECRET "OAuth client secret"
    must_be_set GOOGLE_DRIVE_REFRESH_TOKEN "one-time owner consent (backup-restore.md §2)"
    if [[ -z "${ENV_VALUES[BACKUP_NOTIFICATION_EMAIL]:-}" ]]; then
        warning "BACKUP_NOTIFICATION_EMAIL is empty: backup failures are mailed to MAIL_FROM_ADDRESS"
    fi
else
    violation "BACKUP_DISKS (or BACKUP_TENANT_DISKS) must include google: backups must leave the server (production standard #8)"
fi

# Sentry (OPS-7): DSN only in this file; never send PII.
if [[ -z "${ENV_VALUES[SENTRY_LARAVEL_DSN]:-}" ]]; then
    warning "SENTRY_LARAVEL_DSN is empty: error reporting is off (required before the shop cutover, OPS-7)"
fi
sentry_pii="${ENV_VALUES[SENTRY_SEND_DEFAULT_PII]:-}"
case "${sentry_pii,,}" in
    "" | false | 0) ;;
    *) violation "SENTRY_SEND_DEFAULT_PII must be false (no PII to Sentry)" ;;
esac

log_level="${ENV_VALUES[LOG_LEVEL]:-}"
case "${log_level,,}" in
    debug) violation "LOG_LEVEL must not be debug in production (tokens/PII may reach the logs)" ;;
esac

if [[ "$CHECK_PERMS" -eq 1 ]]; then
    mode="$(stat -c '%a' "$ENV_FILE")"
    if [[ "$mode" != "600" && "$mode" != "400" ]]; then
        violation "$ENV_FILE must be chmod 600 (is $mode)"
    fi
fi

if [[ "$VIOLATIONS" -gt 0 ]]; then
    printf '%d violation(s) in %s\n' "$VIOLATIONS" "$ENV_FILE"
    exit 1
fi

printf 'PASS  %s meets the production standards (%d warning(s))\n' "$ENV_FILE" "$WARNINGS"
