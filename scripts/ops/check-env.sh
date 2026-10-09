#!/usr/bin/env bash
# Validates a production Laravel .env against the production standards
# (docs/01-overview/product-overview.md, vps-runbook.md §8) WITHOUT printing
# any value. Read-only. Exit 0 = compliant, 1 = violations, 2 = usage error.
#
#   bash scripts/ops/check-env.sh /var/www/sroor/shared/.env [--check-perms]
#
# Reused by the release pipeline (OPS-3, scripts/ops/deploy.sh preflight) as
# the gate for: TELESCOPE_ENABLED=false, BACKUP_ARCHIVE_PASSWORD set (CTO
# decision D4), mandatory SMTP (W1 Q6) and the audit_pruner account (IDEN-1.15).
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
