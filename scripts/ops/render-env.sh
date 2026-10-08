#!/usr/bin/env bash
# Renders the production Laravel .env from a key template plus values taken
# from the ENVIRONMENT (OPS-4). In the release workflow those environment
# variables come from the GitHub Environment `production` (secrets + vars),
# mapped one by one in the step's `env:` block. Nothing sensitive is read
# from the repo, passed in argv, or printed.
#
#   bash scripts/ops/render-env.sh <template> <output> [--optional KEY[,KEY...]]
#
#   <template>  KEY=VALUE skeleton, e.g. scripts/ops/templates/production.env.example
#   <output>    file to (re)write atomically with mode 600, e.g. $RUNNER_TEMP/prod.env
#   --optional  keys that may stay empty (default: every key that is empty in
#               the template is REQUIRED and must be provided non-empty)
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
#
# Exit codes: 0 rendered, 1 missing/unsafe value, 2 usage error.
# Validate the result afterwards with scripts/ops/check-env.sh (OPS-1).

set -euo pipefail

usage() {
    printf 'usage: %s <template> <output> [--optional KEY[,KEY...]]\n' "$(basename "$0")" >&2
    exit 2
}

[[ $# -ge 2 ]] || usage
TEMPLATE="$1"
OUTPUT="$2"
shift 2

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
                OPTIONAL["$_k"]=1
            done
            shift 2
            ;;
        *) usage ;;
    esac
done

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

printf 'render-env: wrote %s (%d keys, %d from the environment)\n' "$OUTPUT" "$SEEN_KEYS" "$PROVIDED"
