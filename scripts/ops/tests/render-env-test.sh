#!/usr/bin/env bash
# Offline tests for scripts/ops/render-env.sh (OPS-4).
#
#   bash scripts/ops/tests/render-env-test.sh
#
# Never touches a server or the network. Every value used here is a random,
# fixture-prefixed fake generated at runtime.

set -euo pipefail

TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OPS_DIR="$(cd "$TESTS_DIR/.." && pwd)"
RENDER="$OPS_DIR/render-env.sh"
# shellcheck source=../lib/common.sh
source "$OPS_DIR/lib/common.sh"

PASS=0
FAIL=0
WORK="$(mktemp -d)"
cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

ok() {
    PASS=$((PASS + 1))
    printf '  ok    %s\n' "$1"
}
ko() {
    FAIL=$((FAIL + 1))
    printf '  FAIL  %s\n' "$1"
    if [[ -n "${2:-}" ]]; then
        printf '        %s\n' "$2"
    fi
}

rand() { od -An -N12 -tx1 /dev/urandom | tr -d ' \n'; }

TEMPLATE="$WORK/production.env.example"
cat >"$TEMPLATE" <<'EOF'
# Header comment stays.
APP_NAME="Retail ERP"
APP_ENV=production
APP_KEY=
APP_DEBUG=false

DB_PASSWORD=
SESSION_DOMAIN=
TELEGRAM_BOT_TOKEN=
EOF

# Runs render-env.sh in a clean environment (only PATH/HOME + given vars).
# Sets OUT (stdout+stderr) and RC.
run_render() {
    set +e
    OUT="$(env -i PATH="$PATH" HOME="${HOME:-/tmp}" "$@" 2>&1)"
    RC=$?
    set -e
}

APP_KEY_V="base64:Fixture$(rand)"
DB_V="Fixture$(rand)"
TG_V="Fixture$(rand)"

# 1. Happy path: provided values replace template values, defaults survive.
OUTFILE="$WORK/out/.env"
mkdir -p "$WORK/out"
run_render APP_KEY="$APP_KEY_V" DB_PASSWORD="$DB_V" TELEGRAM_BOT_TOKEN="$TG_V" APP_DEBUG=false \
    bash "$RENDER" "$TEMPLATE" "$OUTFILE" --optional SESSION_DOMAIN
if [[ $RC -eq 0 && -f "$OUTFILE" ]]; then
    declare -A got=()
    parse_env_file "$OUTFILE" got
    if [[ "${got[APP_KEY]}" == "$APP_KEY_V" && "${got[DB_PASSWORD]}" == "$DB_V" \
        && "${got[TELEGRAM_BOT_TOKEN]}" == "$TG_V" && "${got[APP_NAME]}" == "Retail ERP" \
        && "${got[APP_ENV]}" == "production" && "${got[SESSION_DOMAIN]}" == "" ]]; then
        ok "renders provided values and keeps template defaults"
    else
        ko "rendered values do not round-trip"
    fi
else
    ko "happy path failed (rc=$RC)" "$OUT"
fi

if grep -q '^# Header comment stays.$' "$OUTFILE" 2>/dev/null; then
    ok "comments are preserved"
else
    ko "comments are preserved"
fi

if [[ "$OUT" != *"$DB_V"* && "$OUT" != *"$APP_KEY_V"* && "$OUT" != *"$TG_V"* ]]; then
    ok "no value is ever printed"
else
    ko "a value was printed"
fi

# 2. Output permissions: 600 (skipped where the filesystem cannot express it).
case "$(uname -s)" in
    MINGW* | MSYS* | CYGWIN*) printf '  skip  output mode 600 (no POSIX modes on this filesystem)\n' ;;
    *)
        mode="$(stat -c '%a' "$OUTFILE" 2>/dev/null || stat -f '%Lp' "$OUTFILE")"
        if [[ "$mode" == "600" ]]; then
            ok "output file is chmod 600"
        else
            ko "output file is chmod 600" "mode=$mode"
        fi
        ;;
esac

# 3. A required (empty-in-template) key that is not provided fails, names the key only.
run_render APP_KEY="$APP_KEY_V" TELEGRAM_BOT_TOKEN="$TG_V" \
    bash "$RENDER" "$TEMPLATE" "$WORK/out/missing.env" --optional SESSION_DOMAIN
if [[ $RC -eq 1 && "$OUT" == *DB_PASSWORD* && ! -e "$WORK/out/missing.env" ]]; then
    ok "missing required secret fails, names the key, writes nothing"
else
    ko "missing required secret" "rc=$RC"
fi

# 4. An explicitly empty provided value counts as missing.
run_render APP_KEY="$APP_KEY_V" DB_PASSWORD="" TELEGRAM_BOT_TOKEN="$TG_V" \
    bash "$RENDER" "$TEMPLATE" "$WORK/out/empty.env" --optional SESSION_DOMAIN
if [[ $RC -eq 1 && "$OUT" == *DB_PASSWORD* ]]; then
    ok "empty value for a required key fails"
else
    ko "empty value for a required key fails" "rc=$RC"
fi

# 5. Unsafe values are refused without echoing them.
BAD="Fixture$(rand)'quote"
run_render APP_KEY="$APP_KEY_V" DB_PASSWORD="$BAD" TELEGRAM_BOT_TOKEN="$TG_V" \
    bash "$RENDER" "$TEMPLATE" "$WORK/out/bad.env" --optional SESSION_DOMAIN
if [[ $RC -eq 1 && "$OUT" == *DB_PASSWORD* && "$OUT" != *"$BAD"* ]]; then
    ok "single quote in a value is refused without echoing it"
else
    ko "single quote in a value is refused" "rc=$RC"
fi

BAD="Fixture$(rand)"$'\n'"INJECTED=1"
run_render APP_KEY="$APP_KEY_V" DB_PASSWORD="$BAD" TELEGRAM_BOT_TOKEN="$TG_V" \
    bash "$RENDER" "$TEMPLATE" "$WORK/out/nl.env" --optional SESSION_DOMAIN
if [[ $RC -eq 1 && ! -e "$WORK/out/nl.env" ]]; then
    ok "newline in a value is refused (no KEY injection)"
else
    ko "newline in a value is refused" "rc=$RC"
fi

# 6. Shell / dotenv metacharacters are stored literally and never executed.
MARKER="$WORK/pwned"
META="Fixture\$(touch $MARKER)\${HOME}#\"x\\y z"
run_render APP_KEY="$APP_KEY_V" DB_PASSWORD="$META" TELEGRAM_BOT_TOKEN="$TG_V" \
    bash "$RENDER" "$TEMPLATE" "$WORK/out/meta.env" --optional SESSION_DOMAIN
if [[ $RC -eq 0 && ! -e "$MARKER" ]]; then
    declare -A meta=()
    parse_env_file "$WORK/out/meta.env" meta
    if [[ "${meta[DB_PASSWORD]}" == "$META" ]]; then
        ok "metacharacters stored literally, nothing executed"
    else
        ko "metacharacters did not round-trip"
    fi
else
    ko "metacharacter value" "rc=$RC marker=$([[ -e $MARKER ]] && echo yes || echo no)"
fi

# 7. Existing output is replaced atomically (no partial file on failure).
printf 'OLD=1\n' >"$WORK/out/keep.env"
run_render APP_KEY="$APP_KEY_V" TELEGRAM_BOT_TOKEN="$TG_V" \
    bash "$RENDER" "$TEMPLATE" "$WORK/out/keep.env" --optional SESSION_DOMAIN
if [[ $RC -eq 1 ]] && grep -qx 'OLD=1' "$WORK/out/keep.env"; then
    ok "a failed render leaves the previous file untouched"
else
    ko "a failed render leaves the previous file untouched" "rc=$RC"
fi
leftovers="$(find "$WORK/out" -name '*.tmp.*' | wc -l | tr -d ' ')"
if [[ "$leftovers" == "0" ]]; then
    ok "no temp files left behind"
else
    ko "no temp files left behind" "$leftovers left"
fi

# 8. Usage errors.
run_render bash "$RENDER"
if [[ $RC -eq 2 ]]; then ok "no arguments -> usage error (2)"; else ko "usage error" "rc=$RC"; fi
run_render bash "$RENDER" "$WORK/nope.example" "$WORK/out/x.env"
if [[ $RC -eq 2 ]]; then ok "missing template -> usage error (2)"; else ko "missing template" "rc=$RC"; fi
run_render bash "$RENDER" "$TEMPLATE" "$WORK/out/x.env" --optional 'bad key'
if [[ $RC -eq 2 ]]; then ok "invalid --optional key -> usage error (2)"; else ko "invalid --optional" "rc=$RC"; fi

# 8b. SMTP (W1 Q6) and the backup archive password (D4) can never be waived.
for never in MAIL_HOST MAIL_PASSWORD MAIL_FROM_ADDRESS BACKUP_ARCHIVE_PASSWORD DB_BACKUP_PASSWORD GOOGLE_DRIVE_REFRESH_TOKEN; do
    run_render bash "$RENDER" "$TEMPLATE" "$WORK/out/never.env" --optional "SESSION_DOMAIN,$never"
    if [[ $RC -eq 2 && "$OUT" == *"$never is mandatory"* && ! -e "$WORK/out/never.env" ]]; then
        ok "--optional $never is refused (mandatory in production)"
    else
        ko "--optional $never is refused" "rc=$RC out=$OUT"
    fi
done

# 9. Real production template: with every required key provided it renders
#    and the OPS-1 validator accepts the shape (when both files exist).
PROD_TEMPLATE="$OPS_DIR/templates/production.env.example"
if [[ -f "$PROD_TEMPLATE" ]]; then
    declare -A tpl=()
    parse_env_file "$PROD_TEMPLATE" tpl
    vars=()
    for k in "${!tpl[@]}"; do
        if [[ -z "${tpl[$k]}" ]]; then
            vars+=("$k=Fixture$(rand)")
        fi
    done
    run_render "${vars[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/prod.env"
    if [[ $RC -eq 0 ]]; then
        ok "production template renders when every empty key is provided"
    else
        ko "production template renders" "$OUT"
    fi
    # The keys W2 made mandatory are required by the real template.
    for must in MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS BACKUP_ARCHIVE_PASSWORD DB_AUDIT_PRUNER_PASSWORD \
        DB_BACKUP_PASSWORD GOOGLE_DRIVE_CLIENT_ID GOOGLE_DRIVE_CLIENT_SECRET GOOGLE_DRIVE_REFRESH_TOKEN BACKUP_NOTIFICATION_EMAIL \
        CENTRAL_DOMAIN CENTRAL_ADMIN_DOMAINS CENTRAL_PASSWORD_RESET_URL; do
        missing_vars=()
        for v in "${vars[@]}"; do
            [[ "$v" == "$must="* ]] || missing_vars+=("$v")
        done
        run_render "${missing_vars[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/prod-missing.env" --optional SESSION_DOMAIN,SENTRY_LARAVEL_DSN,GOOGLE_DRIVE_FOLDER_ID
        if [[ $RC -eq 1 && "$OUT" == *"$must is required"* ]]; then
            ok "production template requires $must"
        else
            ko "production template requires $must" "rc=$RC"
        fi
    done
    if [[ "${tpl[MAIL_MAILER]:-}" == "smtp" ]]; then
        ok "production template defaults MAIL_MAILER to smtp"
    else
        ko "production template defaults MAIL_MAILER to smtp"
    fi
    # Deploy-only template: the migrator password is required, nothing else leaks in.
    DEPLOY_TEMPLATE="$OPS_DIR/templates/deploy.env.example"
    run_render bash "$RENDER" "$DEPLOY_TEMPLATE" "$WORK/out/deploy.env"
    if [[ $RC -eq 1 && "$OUT" == *"DB_MIGRATOR_PASSWORD is required"* ]]; then
        ok "deploy template requires DB_MIGRATOR_PASSWORD"
    else
        ko "deploy template requires DB_MIGRATOR_PASSWORD" "rc=$RC"
    fi
else
    printf '  skip  production template not present\n'
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
