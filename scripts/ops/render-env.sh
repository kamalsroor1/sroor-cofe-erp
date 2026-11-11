#!/usr/bin/env bash
# Renders the production Laravel .env from a key template plus values taken
# from the ENVIRONMENT (OPS-4). In the release workflow those environment
# variables come from the GitHub Environment `production` (secrets + vars),
# mapped one by one in the step's `env:` block. Nothing sensitive is read
# from the repo, passed in argv, or printed.
#
#   bash scripts/ops/render-env.sh <template> <output> [--optional KEY[,KEY...]] [--rehearsal]
#
#   <template>  KEY=VALUE skeleton, e.g. scripts/ops/templates/production.env.example
#   <output>    file to (re)write atomically with mode 600, e.g. $RUNNER_TEMP/prod.env
#   --optional  keys that may stay empty (default: every key that is empty in
#               the template is REQUIRED and must be provided non-empty).
#               MAIL_*, BACKUP_ARCHIVE_PASSWORD, DB_BACKUP_PASSWORD and the
#               GOOGLE_DRIVE_* OAuth keys are refused here (exit 2).
#   --rehearsal [CTO-2026-11-11] first-deploy rehearsal only (GitHub Environment
#               var RELEASE_REHEARSAL=true): REHEARSAL_WAIVABLE keys may be empty;
#               SMTP incomplete -> MAIL_MAILER=log, MAIL_FROM_ADDRESS empty ->
#               noreply@<CENTRAL_DOMAIN>, Drive OAuth incomplete -> BACKUP_DISKS=local;
#               writes RELEASE_REHEARSAL=true; WARN line per relaxation (names only).
#
# Rules:
#   - For every KEY in the template: if an environment variable KEY is set,
#     its value replaces the template value; otherwise the template value stays.
#   - Provided values are written single-quoted, so phpdotenv stores them
#     literally (no ${VAR} interpolation, no escapes). Values containing a
#     single quote, a newline or any control character are refused.
#   - Keys that are not in the template are ignored: the template is the
#     allowlist of what reaches the server.
#   - Errors name the KEY only, never the value. On any error the previous
#     output file is left untouched.
#   - RELEASE_REHEARSAL is reserved: set there = exit 2; only --rehearsal sets it.
#
# Exit codes: 0 rendered, 1 missing/unsafe value, 2 usage error.
# Validate the result afterwards with scripts/ops/check-env.sh (OPS-1).

set -euo pipefail

usage() {
    printf 'usage: %s <template> <output> [--optional KEY[,KEY...]] [--rehearsal]\n' "$(basename "$0")" >&2
    exit 2
}

[[ $# -ge 2 ]] || usage
TEMPLATE="$1"
OUTPUT="$2"
shift 2

# Keys that may never be waived with --optional when the template has them:
# SMTP is mandatory (W1 Q6), an empty backup password disables backups
# (CTO decision D4) and backups need their account + Drive credentials (OPS-5).
NEVER_OPTIONAL=(MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS BACKUP_ARCHIVE_PASSWORD
    DB_BACKUP_PASSWORD GOOGLE_DRIVE_CLIENT_ID GOOGLE_DRIVE_CLIENT_SECRET GOOGLE_DRIVE_REFRESH_TOKEN)

# [CTO-2026-11-11] Keys a --rehearsal render may leave empty (the CTO adds them
# before the first real customer; docs/07-operations/secrets.md §2). Nothing else.
REHEARSAL_WAIVABLE=(MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS BACKUP_NOTIFICATION_EMAIL
    TELEGRAM_BOT_TOKEN TELEGRAM_CHAT_ID GOOGLE_DRIVE_CLIENT_ID GOOGLE_DRIVE_CLIENT_SECRET GOOGLE_DRIVE_REFRESH_TOKEN)
REHEARSAL_KEY=RELEASE_REHEARSAL
REHEARSAL=0

declare -A OPTIONAL=()
while [[ $# -gt 0 ]]; do
    case "$1" in
        --optional)
            [[ $# -ge 2 ]] || usage
            IFS=',' read -r -a _keys <<<"$2"
            for _k in "${_keys[@]}"; do
                [[ "$_k" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || {
                    printf 'invalid key in --optional: must match [A-Za-z_][A-Za-z0-9_]*\n' >&2
                    exit 2
                }
                for _never in "${NEVER_OPTIONAL[@]}"; do
                    if [[ "$_k" == "$_never" ]]; then
                        printf '%s is mandatory in production and cannot be --optional\n' "$_k" >&2
                        exit 2
                    fi
                done
                OPTIONAL["$_k"]=1
            done
            shift 2
            ;;
        --rehearsal)
            REHEARSAL=1
            shift
            ;;
        *) usage ;;
    esac
done

if [[ -n "${!REHEARSAL_KEY+x}" ]]; then
    printf '%s is reserved: it is set by --rehearsal only, never from the environment\n' "$REHEARSAL_KEY" >&2
    exit 2
fi

# Rehearsal: waived keys + values derived from what is missing (names only in WARN lines).
declare -A OVERRIDE=()
WARNINGS=()
if [[ "$REHEARSAL" -eq 1 ]]; then
    OVERRIDE["$REHEARSAL_KEY"]=true
    waived=()
    for _k in "${REHEARSAL_WAIVABLE[@]}"; do
        if [[ -z "${!_k:-}" ]]; then
            waived+=("$_k")
            OPTIONAL["$_k"]=1
        fi
    done
    WARNINGS+=("REHEARSAL MODE (--rehearsal): this .env is NOT production-ready; $REHEARSAL_KEY=true is written into it")
    if [[ ${#waived[@]} -gt 0 ]]; then
        WARNINGS+=("rehearsal: empty and waived: ${waived[*]}")
    else
        WARNINGS+=("rehearsal: no key was waived (everything is provided); delete RELEASE_REHEARSAL to go strict")
    fi
    smtp_missing=()
    for _k in MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD; do
        [[ -n "${!_k:-}" ]] || smtp_missing+=("$_k")
    done
    if [[ ${#smtp_missing[@]} -gt 0 ]]; then
        OVERRIDE[MAIL_MAILER]=log
        WARNINGS+=("rehearsal: SMTP incomplete (${smtp_missing[*]}) -> MAIL_MAILER=log: no mail leaves the server")
    fi
    if [[ -z "${MAIL_FROM_ADDRESS:-}" ]]; then
        if [[ "${CENTRAL_DOMAIN:-}" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]]; then
            OVERRIDE[MAIL_FROM_ADDRESS]="noreply@$CENTRAL_DOMAIN"
            WARNINGS+=("rehearsal: MAIL_FROM_ADDRESS empty -> noreply@<CENTRAL_DOMAIN>")
        fi
    fi
    drive_missing=()
    for _k in GOOGLE_DRIVE_CLIENT_ID GOOGLE_DRIVE_CLIENT_SECRET GOOGLE_DRIVE_REFRESH_TOKEN; do
        [[ -n "${!_k:-}" ]] || drive_missing+=("$_k")
    done
    if [[ ${#drive_missing[@]} -gt 0 ]]; then
        OVERRIDE[BACKUP_DISKS]=local
        WARNINGS+=("rehearsal: Google Drive OAuth incomplete (${drive_missing[*]}) -> BACKUP_DISKS=local: encrypted archives stay on this server")
    fi
fi

[[ -f "$TEMPLATE" ]] || {
    printf 'template not found: %s\n' "$TEMPLATE" >&2
    exit 2
}
OUT_DIR="$(dirname "$OUTPUT")"
[[ -d "$OUT_DIR" ]] || {
    printf 'output directory does not exist: %s\n' "$OUT_DIR" >&2
    exit 2
}

ERRORS=()
RENDERED=()
SEEN_KEYS=0
PROVIDED=0
SEEN_REHEARSAL_KEY=0

re_kv='^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$'
re_blank='^[[:space:]]*(#.*)?$'
re_unsafe=$'[\'\001-\037\177]'

# Template value is "empty" when it is nothing, "" or ''.
template_value_is_empty() {
    local raw="$1"
    raw="${raw#"${raw%%[![:space:]]*}"}"
    raw="${raw%%[[:space:]]#*}"
    raw="${raw%"${raw##*[![:space:]]}"}"
    [[ -z "$raw" || "$raw" == '""' || "$raw" == "''" ]]
}

lineno=0
while IFS= read -r line || [[ -n "$line" ]]; do
    lineno=$((lineno + 1))
    line="${line%$'\r'}"

    if [[ "$line" =~ $re_blank ]]; then
        RENDERED+=("$line")
        continue
    fi
    if [[ ! "$line" =~ $re_kv ]]; then
        printf 'template line %d is not a KEY=VALUE line\n' "$lineno" >&2
        exit 2
    fi

    key="${BASH_REMATCH[2]}"
    tpl_raw="${BASH_REMATCH[3]}"
    SEEN_KEYS=$((SEEN_KEYS + 1))
    [[ "$key" != "$REHEARSAL_KEY" ]] || SEEN_REHEARSAL_KEY=1

    if [[ -n "${OVERRIDE[$key]+x}" ]]; then
        if [[ "${OVERRIDE[$key]}" =~ $re_unsafe ]]; then
            ERRORS+=("$key: derived value contains a single quote, newline or control character")
            continue
        fi
        RENDERED+=("$key='${OVERRIDE[$key]}'")
        continue
    fi

    if [[ -n "${!key+x}" ]]; then
        value="${!key}"
        if [[ -z "$value" ]]; then
            if [[ -z "${OPTIONAL[$key]:-}" ]]; then
                ERRORS+=("$key is required but empty")
            fi
            RENDERED+=("$key=")
            continue
        fi
        if [[ "$value" =~ $re_unsafe ]]; then
            ERRORS+=("$key contains a single quote, newline or control character (regenerate it)")
            continue
        fi
        RENDERED+=("$key='$value'")
        PROVIDED=$((PROVIDED + 1))
    else
        if template_value_is_empty "$tpl_raw" && [[ -z "${OPTIONAL[$key]:-}" ]]; then
            ERRORS+=("$key is required but not provided")
        fi
        RENDERED+=("$line")
    fi
done <"$TEMPLATE"

if [[ $SEEN_KEYS -eq 0 ]]; then
    printf 'template has no KEY=VALUE lines: %s\n' "$TEMPLATE" >&2
    exit 2
fi
if [[ "$REHEARSAL" -eq 1 && "$SEEN_REHEARSAL_KEY" -eq 0 ]]; then
    printf -- '--rehearsal needs a %s= line in the template (the marker the gates read)\n' "$REHEARSAL_KEY" >&2
    exit 2
fi

if [[ ${#ERRORS[@]} -gt 0 ]]; then
    printf 'render-env: %d problem(s), nothing written:\n' "${#ERRORS[@]}" >&2
    printf '  - %s\n' "${ERRORS[@]}" >&2
    exit 1
fi

umask 077
TMP="$(mktemp "$OUTPUT.tmp.XXXXXX")"
trap 'rm -f "$TMP"' EXIT
printf '%s\n' "${RENDERED[@]}" >"$TMP"
chmod 600 "$TMP"
mv -f "$TMP" "$OUTPUT"
trap - EXIT

if [[ ${#WARNINGS[@]} -gt 0 ]]; then
    printf 'WARN  %s\n' "${WARNINGS[@]}"
fi
printf 'render-env: wrote %s (%d keys, %d from the environment)\n' "$OUTPUT" "$SEEN_KEYS" "$PROVIDED"
