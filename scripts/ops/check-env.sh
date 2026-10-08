#!/usr/bin/env bash
# Validates a production Laravel .env against the production standards
# (docs/01-overview/product-overview.md, vps-runbook.md §8) WITHOUT printing
# any value. Read-only. Exit 0 = compliant, 1 = violations, 2 = usage error.
#
#   bash scripts/ops/check-env.sh /var/www/sroor/shared/.env [--check-perms]
#
# Reused by the release pipeline (OPS-3) as the "TELESCOPE_ENABLED must be
# false in production" gate.

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

violation() {
    VIOLATIONS=$((VIOLATIONS + 1))
    printf 'FAIL  %s\n' "$1"
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

printf 'PASS  %s meets the production standards\n' "$ENV_FILE"
