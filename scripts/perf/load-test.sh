#!/usr/bin/env bash
# shellcheck disable=SC2016 # single-quoted PHP snippets for `php -r` and the xargs probe: no shell expansion wanted
# PERF-04 load test for a LOCAL Sroor ERP server (never production).
#
# Scenarios (the 2026-11-12 measurement): POS browse, invoice list, dashboard alone and under
# N users, items list, treasury summary, concurrent sales across tenants, 5 cashiers on one
# branch, a heavy report next to sales, and a cross-tenant isolation burst. Prints one table
# (scenario, requests, median ms, p95 ms, failures), then runs `tenant:perf-verify` on every
# tenant against a snapshot taken before the run (no duplicate numbers, stock == start - sold,
# one voucher per cash invoice, balances).
#
# Usage:   scripts/perf/load-test.sh [--skip-verify]
# Config:  environment variables, or scripts/perf/perf.env (git-ignored; copy perf.env.example),
#          or another file named by PERF_CONFIG.
#          See docs/07-operations/perf-testing.md.
#
# Needs: bash, curl, ab (ApacheBench), php. No credentials are stored in this file.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

SKIP_VERIFY=0
for arg in "$@"; do
    case "$arg" in
        --skip-verify) SKIP_VERIFY=1 ;;
        -h|--help) sed -n '3,17p' "$0"; exit 0 ;;
        *) echo "unknown argument: $arg" >&2; exit 2 ;;
    esac
done

PERF_CONFIG="${PERF_CONFIG:-${SCRIPT_DIR}/perf.env}"
if [[ -f "$PERF_CONFIG" ]]; then
    # shellcheck source=/dev/null
    source "$PERF_CONFIG"
fi

PERF_BASE_URL="${PERF_BASE_URL:-http://127.0.0.1:8000}"
PERF_TENANTS="${PERF_TENANTS:-}"
PERF_TOKENS="${PERF_TOKENS:-}"
PERF_ARTISAN="${PERF_ARTISAN:-php ${REPO_ROOT}/backend/artisan}"
PERF_USERS="${PERF_USERS:-20}"
PERF_PERCENT="${PERF_PERCENT:-100}"
PERF_SALE_QTY="${PERF_SALE_QTY:-0.050}"
PERF_OUT="${PERF_OUT:-$(mktemp -d "${TMPDIR:-/tmp}/sroor-perf.XXXXXX")}"

die() { echo "load-test: $*" >&2; exit 1; }

for tool in curl ab php; do
    command -v "$tool" >/dev/null 2>&1 || die "$tool is required"
done

read -r -a TENANTS <<<"$PERF_TENANTS"
read -r -a TOKENS <<<"$PERF_TOKENS"
(( ${#TENANTS[@]} > 0 )) || die "set PERF_TENANTS (space-separated tenant ids; the first is the big one)"
(( ${#TENANTS[@]} == ${#TOKENS[@]} )) || die "PERF_TOKENS must hold one Sanctum token per tenant, in the same order"

# ---- Local hosts only, no override: the run opens shifts and posts real sales ---------------
HOST="$(php -r '$h = parse_url($argv[1], PHP_URL_HOST); echo is_string($h) ? strtolower(trim($h, "[]")) : "";' "$PERF_BASE_URL")"
[[ -n "$HOST" ]] || die "PERF_BASE_URL has no host: $PERF_BASE_URL"
case "$HOST" in
    localhost|127.0.0.1|::1|*.localhost|*.test) ;;
    *) die "refusing non-local host '$HOST' (localhost, 127.0.0.1, *.localhost, *.test only)" ;;
esac

# ab needs a path component.
[[ "$PERF_BASE_URL" == */ ]] && PERF_BASE_URL="${PERF_BASE_URL%/}"
API="${PERF_BASE_URL}/api/v1"
mkdir -p "$PERF_OUT"
chmod 700 "$PERF_OUT" # per-request files carry bearer tokens

# json_get <dot.path> : read JSON on stdin, print the value (empty when missing).
json_get() {
    php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach (explode(".", $argv[1]) as $k) { $d = is_array($d) && array_key_exists($k, $d) ? $d[$k] : null; } echo is_scalar($d) ? $d : "";' "$1"
}

# api <tenant index> <method> <path> [json body] : prints the body; exits on transport errors.
api() {
    local i="$1" method="$2" path="$3" body="${4:-}"
    local args=(-sS -X "$method" -H 'Accept: application/json' -H "X-Tenant: ${TENANTS[$i]}" -H "Authorization: Bearer ${TOKENS[$i]}")
    [[ -n "${STORE_IDS[$i]:-}" ]] && args+=(-H "X-Store-Id: ${STORE_IDS[$i]}")
    [[ -n "$body" ]] && args+=(-H 'Content-Type: application/json' --data "$body")
    curl "${args[@]}" "${API}${path}"
}

# ---- Discover per-tenant fixtures through the API -------------------------------------------
declare -a STORE_IDS=() SALE_BODIES=() IDENTITIES=()
for i in "${!TENANTS[@]}"; do
    me="$(api "$i" GET /auth/me)"
    identity="$(printf '%s' "$me" | json_get data.user.email)"
    [[ -n "$identity" ]] || die "tenant ${TENANTS[$i]}: /auth/me did not answer with a user (token?)"
    IDENTITIES[i]="$identity"
    STORE_IDS[i]="$(printf '%s' "$me" | php -r '$d = json_decode(stream_get_contents(STDIN), true)["data"] ?? []; echo $d["store"]["id"] ?? $d["user"]["default_store_id"] ?? $d["stores"][0]["id"] ?? "";')"
    [[ -n "${STORE_IDS[$i]}" ]] || die "tenant ${TENANTS[$i]}: no store id in /auth/me"

    items="$(api "$i" GET '/pos/items?per_page=100')"
    item_id="$(printf '%s' "$items" | php -r '
        $d = json_decode(stream_get_contents(STDIN), true);
        $rows = $d["data"]["items"] ?? $d["data"] ?? [];
        $best = null;
        foreach ($rows as $r) {
            $stock = (string) ($r["store_stock"] ?? $r["current_stock"] ?? $r["stock"] ?? "0");
            if (! empty($r["is_weighted"]) && ($best === null || bccomp($stock, $best[1], 3) > 0)) { $best = [$r["id"], $stock]; }
        }
        echo $best[0] ?? "";')"
    [[ -n "$item_id" ]] || die "tenant ${TENANTS[$i]}: no weighted item in /pos/items (seed it with tenant:seed-perf)"
    customer_id="$(api "$i" GET '/customers?per_page=1' | json_get data.0.id)"
    [[ -n "$customer_id" ]] || die "tenant ${TENANTS[$i]}: no customer"
    SALE_BODIES[i]="{\"customer_id\":${customer_id},\"payment_type\":\"cash\",\"payment_method\":\"cash\",\"items\":[{\"item_id\":${item_id},\"quantity\":\"${PERF_SALE_QTY}\"}]}"
    printf '%s' "${SALE_BODIES[$i]}" >"${PERF_OUT}/sale-${i}.json"

    # A branch that requires a shift refuses sales without one; "already open" is fine.
    api "$i" POST /shifts/open '{"opening_cash_balance":"0.000"}' >/dev/null || true
done

if (( SKIP_VERIFY == 0 )); then
    for i in "${!TENANTS[@]}"; do
        $PERF_ARTISAN tenant:perf-verify "${TENANTS[$i]}" --snapshot="${PERF_OUT}/snapshot-${i}.json" >/dev/null
    done
fi

# ---- Measurement helpers -------------------------------------------------------------------
ROWS=()

# ab_run <label> <tenant index> <requests> <concurrency> <path> [POST body file]  (background-safe)
ab_run() {
    local label="$1" i="$2" n="$3" c="$4" path="$5" body="${6:-}"
    # -l: list/report bodies change while sales run; a different length is not a failure.
    local args=(-q -l -n "$n" -c "$c" -s 120 -g "${PERF_OUT}/${label}.tsv"
        -H 'Accept: application/json' -H "X-Tenant: ${TENANTS[$i]}" -H "Authorization: Bearer ${TOKENS[$i]}" -H "X-Store-Id: ${STORE_IDS[$i]}")
    [[ -n "$body" ]] && args+=(-p "$body" -T 'application/json')
    ab "${args[@]}" "${API}${path}" >"${PERF_OUT}/${label}.ab" 2>&1 || true
}

# summarize <scenario> <label>... : merge the per-request times of several ab runs into one row.
summarize() {
    local scenario="$1"; shift
    local row
    row="$(php -r '
        $out = $argv[1]; $times = []; $fail = 0; $req = 0;
        foreach (array_slice($argv, 2) as $label) {
            $ab = (string) @file_get_contents("$out/$label.ab");
            if (preg_match("/Complete requests:\s+(\d+)/", $ab, $m)) { $req += (int) $m[1]; }
            if (preg_match("/Failed requests:\s+(\d+)/", $ab, $m)) { $fail += (int) $m[1]; }
            if (preg_match("/Non-2xx responses:\s+(\d+)/", $ab, $m)) { $fail += (int) $m[1]; }
            if (! preg_match("/Complete requests/", $ab)) { $fail += 1; }
            foreach (array_slice(file("$out/$label.tsv", FILE_IGNORE_NEW_LINES) ?: [], 1) as $line) {
                $cols = explode("\t", $line);
                if (isset($cols[4]) && is_numeric($cols[4])) { $times[] = (int) $cols[4]; }
            }
        }
        sort($times);
        $pick = function (int $p) use ($times) { return $times === [] ? "-" : (string) $times[min(count($times) - 1, (int) ceil($p / 100 * count($times)) - 1)]; };
        echo $req, "|", $pick(50), "|", $pick(95), "|", $fail;' "$PERF_OUT" "$@")"
    ROWS+=("${scenario}|${row}")
}

# Request count of a scenario, scaled by PERF_PERCENT (e.g. 25 on a busy machine), at least 2.
n() { local v=$(( $1 * PERF_PERCENT / 100 )); echo $(( v < 2 ? 2 : v )); }

YEAR_FROM="$(php -r 'echo date("Y-m-d", strtotime("-364 days"));')"
YEAR_TO="$(php -r 'echo date("Y-m-d");')"

echo "load-test: base=${PERF_BASE_URL} tenants=${#TENANTS[@]} output=${PERF_OUT}" >&2

# 1-6: reads on the big tenant (index 0).
ab_run pos_browse 0 "$(n 200)" 5 '/pos/items?per_page=50';                summarize 'POS browse (pos/items, c=5)' pos_browse
ab_run invoice_list 0 "$(n 200)" 5 '/invoices?per_page=25';               summarize 'Invoice list (c=5)' invoice_list
ab_run dashboard_alone 0 "$(n 20)" 1 '/dashboard';                        summarize 'Dashboard alone (c=1)' dashboard_alone
ab_run dashboard_users 0 "$(n 200)" "$PERF_USERS" '/dashboard';           summarize "Dashboard (${PERF_USERS} users)" dashboard_users
ab_run items_50 0 "$(n 100)" 1 '/items?per_page=50';                      summarize 'Items per_page=50 (c=1)' items_50
ab_run treasury 0 "$(n 100)" 1 '/treasury/summary';                       summarize 'Treasury summary (c=1)' treasury

# 7: concurrent sales on every tenant at the same time.
labels=()
for i in "${!TENANTS[@]}"; do
    ab_run "sales_tenant_${i}" "$i" "$(n 40)" 4 /pos/checkout "${PERF_OUT}/sale-${i}.json" &
    labels+=("sales_tenant_${i}")
done
wait
summarize "Sales across ${#TENANTS[@]} tenants (c=4 each)" "${labels[@]}"

# 8: five cashiers on one branch.
ab_run five_cashiers 0 "$(n 100)" 5 /pos/checkout "${PERF_OUT}/sale-0.json"
summarize '5 cashiers, one branch' five_cashiers

# 9: a full-year report next to sales on the same tenant.
ab_run heavy_report 0 "$(n 10)" 2 "/reports/comprehensive?from=${YEAR_FROM}&to=${YEAR_TO}" &
ab_run sales_next_to_report 0 "$(n 60)" 5 /pos/checkout "${PERF_OUT}/sale-0.json" &
wait
summarize 'Year report next to sales' heavy_report
summarize 'Sales next to a year report' sales_next_to_report

# isolation_probe "<tenant>\t<token>\t<expected email>\t<expected status>" : prints OK or FAIL.
isolation_probe() {
    local tenant token identity expect response code
    IFS=$'\t' read -r tenant token identity expect <<<"$1"
    response="$(curl -sS -w '\n%{http_code}' -H 'Accept: application/json' -H "X-Tenant: ${tenant}" \
        -H "Authorization: Bearer ${token}" "${API}/auth/me" || true)"
    code="${response##*$'\n'}"
    if [[ "$code" != "$expect" ]] || { [[ "$expect" == 200 ]] && [[ "$response" != *"\"${identity}\""* ]]; }; then
        echo FAIL
    else
        echo OK
    fi
}
export -f isolation_probe

# 10: isolation burst: mixed tenants in parallel, every answer must be the caller's own user;
#     a token on another tenant must be 401.
iso_requests=0
iso_failures=0
if (( ${#TENANTS[@]} > 1 )); then
    : >"${PERF_OUT}/isolation.jobs"
    for round in $(seq 1 "$(n 20)"); do
        for i in "${!TENANTS[@]}"; do
            other=$(( (i + 1) % ${#TENANTS[@]} ))
            printf '%s\t%s\t%s\t%s\n' "${TENANTS[$i]}" "${TOKENS[$i]}" "${IDENTITIES[$i]}" "200" >>"${PERF_OUT}/isolation.jobs"
            printf '%s\t%s\t%s\t%s\n' "${TENANTS[$other]}" "${TOKENS[$i]}" "-" "401" >>"${PERF_OUT}/isolation.jobs"
        done
        : "$round"
    done
    export API
    xargs -P 24 -n 1 -d '\n' bash -c 'isolation_probe "$0"' <"${PERF_OUT}/isolation.jobs" >"${PERF_OUT}/isolation.out"
    rm -f "${PERF_OUT}/isolation.jobs" # holds tokens
    iso_requests="$(wc -l <"${PERF_OUT}/isolation.out")"
    iso_failures="$(grep -c FAIL "${PERF_OUT}/isolation.out" || true)"
    ROWS+=("Isolation burst (${#TENANTS[@]} tenants, 24 parallel)|${iso_requests}|-|-|${iso_failures}")
fi

# ---- Report --------------------------------------------------------------------------------
printf '\n%-44s %9s %11s %9s %9s\n' 'scenario' 'requests' 'median ms' 'p95 ms' 'failures'
printf '%.0s-' {1..86}; echo
total_failures=0
for row in "${ROWS[@]}"; do
    IFS='|' read -r scenario requests median p95 failures <<<"$row"
    printf '%-44s %9s %11s %9s %9s\n' "$scenario" "$requests" "$median" "$p95" "$failures"
    total_failures=$(( total_failures + failures ))
done

verify_failed=0
if (( SKIP_VERIFY == 0 )); then
    echo
    for i in "${!TENANTS[@]}"; do
        if ! $PERF_ARTISAN tenant:perf-verify "${TENANTS[$i]}" --baseline="${PERF_OUT}/snapshot-${i}.json"; then
            verify_failed=1
        fi
    done
fi

echo
echo "load-test: raw ab output and per-request times in ${PERF_OUT}" >&2
if (( verify_failed == 1 )); then
    die "consistency check FAILED"
fi
if (( total_failures > 0 )); then
    die "${total_failures} failed request(s)"
fi
