#!/usr/bin/env bash
# Shared helpers for scripts/ops. Source it; do not execute it.
#
# Rules every helper follows:
#   - never print a secret value (only variable NAMES appear in messages)
#   - never use `set -x`, never pass secrets in argv (they would show in `ps`)
#   - idempotent: installing an unchanged file is a no-op

set -euo pipefail

OPS_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OPS_DIR="$(cd "$OPS_LIB_DIR/.." && pwd)"
OPS_TEMPLATES_DIR="$OPS_DIR/templates"
export OPS_DIR OPS_TEMPLATES_DIR

# Set to 1 by install_file when the destination changed (callers reload services).
# shellcheck disable=SC2034 # read by the step scripts that source this file
OPS_CHANGED=0

log() { printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
info() { log "INFO  $*"; }
warn() { log "WARN  $*" >&2; }
die() {
    log "ERROR $*" >&2
    exit 1
}

require_root() {
    [[ "$(id -u)" -eq 0 ]] || die "run this as root (sudo)"
}

# require_vars VAR...  -> every variable must be set and non-empty.
require_vars() {
    local var missing=()
    for var in "$@"; do
        if [[ -z "${!var:-}" ]]; then
            missing+=("$var")
        fi
    done
    if [[ ${#missing[@]} -gt 0 ]]; then
        die "missing required variable(s): ${missing[*]} (see scripts/ops/provision.env.example)"
    fi
}

# require_secret VAR... -> set, 24+ chars, only [A-Za-z0-9].
# The strict charset keeps secrets safe inside SQL literals and config files
# without any quoting. Generate with: openssl rand -hex 32
require_secret() {
    local var value
    for var in "$@"; do
        value="${!var:-}"
        [[ -n "$value" ]] || die "missing required secret: $var"
        if [[ ${#value} -lt 24 || ! "$value" =~ ^[A-Za-z0-9]+$ ]]; then
            die "$var must be at least 24 characters of [A-Za-z0-9] (generate with: openssl rand -hex 32)"
        fi
    done
}

# require_identifier VAR... -> safe MySQL/Unix identifier.
require_identifier() {
    local var value
    for var in "$@"; do
        value="${!var:-}"
        [[ -n "$value" ]] || die "missing required variable: $var"
        [[ "$value" =~ ^[a-z][a-z0-9_]{0,31}$ ]] || die "$var must match ^[a-z][a-z0-9_]{0,31}\$"
    done
}

# Parses KEY=VALUE lines like phpdotenv (quotes, inline comments, CRLF) and
# exports them. Values are stored literally: nothing is ever evaluated.
load_env_file() {
    local file="$1"
    [[ -f "$file" ]] || die "env file not found: $file"
    local line key lineno=0
    while IFS= read -r line || [[ -n "$line" ]]; do
        lineno=$((lineno + 1))
        line="${line%$'\r'}"
        [[ "$line" =~ ^[[:space:]]*(#.*)?$ ]] && continue
        if [[ "$line" =~ ^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
            key="${BASH_REMATCH[2]}"
            _env_unquote "${BASH_REMATCH[3]}"
            printf -v "$key" '%s' "$ENV_UNQUOTED"
            export "${key?}"
        else
            die "$file:$lineno is not a KEY=VALUE line"
        fi
    done <"$file"
}

# Reads an env file into an associative array without exporting anything.
# usage: declare -A vals; parse_env_file file vals
parse_env_file() {
    local file="$1"
    local -n __target="$2"
    [[ -f "$file" ]] || return 1
    local line key lineno=0
    while IFS= read -r line || [[ -n "$line" ]]; do
        lineno=$((lineno + 1))
        line="${line%$'\r'}"
        [[ "$line" =~ ^[[:space:]]*(#.*)?$ ]] && continue
        if [[ "$line" =~ ^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
            key="${BASH_REMATCH[2]}"
            _env_unquote "${BASH_REMATCH[3]}"
            __target["$key"]="$ENV_UNQUOTED"
        else
            printf 'line %d is not a KEY=VALUE line\n' "$lineno" >&2
            return 2
        fi
    done <"$file"
}

# Sets ENV_UNQUOTED (no subshell: forks are slow on some hosts).
_env_unquote() {
    local raw="$1"
    local re_double='^"(([^"\\]|\\.)*)"[[:space:]]*(#.*)?$'
    local re_single="^'([^']*)'[[:space:]]*(#.*)?\$"
    # Leading whitespace after '=' is not part of the value.
    raw="${raw#"${raw%%[![:space:]]*}"}"
    if [[ "$raw" =~ $re_double ]]; then
        ENV_UNQUOTED="${BASH_REMATCH[1]}"
    elif [[ "$raw" =~ $re_single ]]; then
        ENV_UNQUOTED="${BASH_REMATCH[1]}"
    else
        # Unquoted: a ' #' starts a comment, trailing whitespace is trimmed.
        raw="${raw%%[[:space:]]#*}"
        raw="${raw%"${raw##*[![:space:]]}"}"
        ENV_UNQUOTED="$raw"
    fi
}

# Refuse env files that other users can read (they hold fresh secrets).
assert_private_file() {
    local file="$1"
    [[ -f "$file" ]] || die "file not found: $file"
    local owner mode
    owner="$(stat -c '%U' "$file")"
    mode="$(stat -c '%a' "$file")"
    [[ "$owner" == "root" ]] || die "$file must be owned by root (is: $owner)"
    [[ "$mode" == "600" || "$mode" == "400" ]] || die "$file must be chmod 600 (is: $mode)"
}

# Renders ${UPPER_CASE} placeholders only, so nginx/supervisor runtime
# variables ($host, %(program_name)s) are left untouched. Fails if any
# referenced variable is unset. The output is created with mode 600 first.
render_template() {
    local template="$1" dest="$2"
    [[ -f "$template" ]] || die "template not found: $template"
    local vars var list=""
    vars="$(grep -oE '\$\{[A-Z_][A-Z0-9_]*\}' "$template" | sort -u | tr -d '${}')" || vars=""
    for var in $vars; do
        [[ -n "${!var:-}" ]] || die "template $(basename "$template") needs \$$var"
        export "${var?}"
        list+="\${$var} "
    done
    (
        umask 077
        if [[ -n "$list" ]]; then
            envsubst "$list" <"$template" >"$dest"
        else
            cat "$template" >"$dest"
        fi
    )
}

# install_file SRC DEST MODE OWNER:GROUP -> copies only when the content
# differs and sets OPS_CHANGED=1 in that case. Permissions are always enforced.
install_file() {
    local src="$1" dest="$2" mode="$3" owner="$4"
    if [[ -f "$dest" ]] && cmp -s "$src" "$dest"; then
        :
    else
        install -D -m "$mode" -o "${owner%%:*}" -g "${owner##*:}" "$src" "$dest"
        OPS_CHANGED=1
        info "updated $dest"
    fi
    chmod "$mode" "$dest"
    chown "$owner" "$dest"
}

# install_template TEMPLATE_NAME DEST MODE OWNER:GROUP
install_template() {
    local name="$1" dest="$2" mode="$3" owner="$4"
    local tmp
    tmp="$(mktemp)"
    render_template "$OPS_TEMPLATES_DIR/$name" "$tmp"
    install_file "$tmp" "$dest" "$mode" "$owner"
    rm -f "$tmp"
}

apt_install() {
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@"
}

# ensure_line FILE LINE -> appends LINE if it is not already present verbatim.
ensure_line() {
    local file="$1" line="$2"
    touch "$file"
    if ! grep -qxF -- "$line" "$file"; then
        printf '%s\n' "$line" >>"$file"
        # shellcheck disable=SC2034 # read by the step scripts that source this file
        OPS_CHANGED=1
    fi
}

# Defaults for optional, non-secret settings (documented in provision.env.example).
apply_defaults() {
    : "${SSH_PORT:=22}"
    : "${APP_USER:=sroor}"
    : "${ADMIN_USER:=opsadmin}"
    : "${APP_ROOT:=/var/www/sroor}"
    : "${PHP_VERSION:=8.3}"
    : "${PHP_FPM_MAX_CHILDREN:=20}"
    : "${TIMEZONE:=UTC}"
    : "${SWAP_SIZE_GB:=4}"
    : "${CENTRAL_DB_NAME:=sroor_central}"
    : "${TENANT_DB_PREFIX:=tenant_}"
    : "${MYSQL_USER_HOST:=localhost}"
    : "${MYSQL_APP_USER:=sroor_app}"
    : "${MYSQL_MIGRATOR_USER:=sroor_migrator}"
    : "${MYSQL_PROVISIONER_USER:=sroor_provisioner}"
    : "${MYSQL_BACKUP_USER:=sroor_backup}"
    : "${MYSQL_INNODB_BUFFER_POOL:=2G}"
    : "${REDIS_MAXMEMORY:=1gb}"
    : "${QUEUE_MODE:=horizon}"
    : "${QUEUE_WORKER_PROCESSES:=2}"
    : "${HSTS_MAX_AGE:=31536000}"
    : "${CERTBOT_STAGING:=0}"
    export SSH_PORT APP_USER ADMIN_USER APP_ROOT PHP_VERSION PHP_FPM_MAX_CHILDREN TIMEZONE SWAP_SIZE_GB \
        CENTRAL_DB_NAME TENANT_DB_PREFIX MYSQL_USER_HOST MYSQL_APP_USER MYSQL_MIGRATOR_USER \
        MYSQL_PROVISIONER_USER MYSQL_BACKUP_USER MYSQL_INNODB_BUFFER_POOL REDIS_MAXMEMORY QUEUE_MODE \
        QUEUE_WORKER_PROCESSES HSTS_MAX_AGE CERTBOT_STAGING
}
