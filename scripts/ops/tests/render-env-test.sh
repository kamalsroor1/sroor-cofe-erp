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
        TELEGRAM_BOT_TOKEN TELEGRAM_CHAT_ID CENTRAL_DOMAIN CENTRAL_ADMIN_DOMAINS CENTRAL_PASSWORD_RESET_URL; do
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

    # ------------------------------------------------------------------------------
    # 10. [CTO-2026-11-11] Rehearsal mode (--rehearsal) on the real template.
    # ------------------------------------------------------------------------------
    CHECK_ENV="$OPS_DIR/check-env.sh"
    WAIVABLE=" MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS BACKUP_NOTIFICATION_EMAIL TELEGRAM_BOT_TOKEN TELEGRAM_CHAT_ID GOOGLE_DRIVE_CLIENT_ID GOOGLE_DRIVE_CLIENT_SECRET GOOGLE_DRIVE_REFRESH_TOKEN "
    OPT=SESSION_DOMAIN,SENTRY_LARAVEL_DSN,GOOGLE_DRIVE_FOLDER_ID
    # Realistic values for the keys check-env.sh validates by shape.
    real_vars=()
    for v in "${vars[@]}"; do
        k="${v%%=*}"
        case "$k" in
            APP_KEY) real_vars+=("APP_KEY=base64:Fixture$(rand)") ;;
            APP_URL) real_vars+=("APP_URL=https://example.test") ;;
            CENTRAL_DOMAIN) real_vars+=("CENTRAL_DOMAIN=example.test") ;;
            CENTRAL_ADMIN_DOMAINS) real_vars+=("CENTRAL_ADMIN_DOMAINS=admin.example.test") ;;
            CENTRAL_PASSWORD_RESET_URL) real_vars+=("CENTRAL_PASSWORD_RESET_URL=https://admin.example.test/super-admin/reset-password") ;;
            MAIL_PORT) real_vars+=("MAIL_PORT=587") ;;
            MAIL_FROM_ADDRESS) real_vars+=("MAIL_FROM_ADDRESS=noreply@mail.example.test") ;;
            BACKUP_ARCHIVE_PASSWORD) real_vars+=("BACKUP_ARCHIVE_PASSWORD=Fixture$(rand)$(rand)") ;;
            *) real_vars+=("$v") ;;
        esac
    done
    # Only what the CTO has today: every waivable key absent.
    today_vars=()
    for v in "${real_vars[@]}"; do
        [[ "$WAIVABLE" == *" ${v%%=*} "* ]] || today_vars+=("$v")
    done
    secret_values=()
    for v in "${today_vars[@]}"; do secret_values+=("${v#*=}"); done

    no_value_printed() {
        local sv
        for sv in "${secret_values[@]}"; do
            case "$sv" in example.test | admin.example.test | https://* | 587) continue ;; esac
            [[ "$OUT" != *"$sv"* ]] || return 1
        done
    }

    REH="$WORK/out/rehearsal.env"
    run_render "${today_vars[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$REH" --optional "$OPT" --rehearsal
    if [[ $RC -eq 0 && -f "$REH" ]]; then
        ok "rehearsal: renders without SMTP, Telegram, Google Drive and BACKUP_NOTIFICATION_EMAIL"
        declare -A reh=()
        parse_env_file "$REH" reh
        if [[ "${reh[RELEASE_REHEARSAL]}" == "true" ]]; then ok "rehearsal: RELEASE_REHEARSAL=true is written"; else ko "rehearsal: RELEASE_REHEARSAL=true is written"; fi
        if [[ "${reh[MAIL_MAILER]}" == "log" ]]; then ok "rehearsal: incomplete SMTP -> MAIL_MAILER=log"; else ko "rehearsal: incomplete SMTP -> MAIL_MAILER=log" "got ${reh[MAIL_MAILER]}"; fi
        if [[ "${reh[MAIL_FROM_ADDRESS]}" == "noreply@example.test" ]]; then ok "rehearsal: empty MAIL_FROM_ADDRESS -> noreply@<CENTRAL_DOMAIN>"; else ko "rehearsal: MAIL_FROM_ADDRESS default"; fi
        if [[ "${reh[BACKUP_DISKS]}" == "local" ]]; then ok "rehearsal: incomplete Drive OAuth -> BACKUP_DISKS=local"; else ko "rehearsal: BACKUP_DISKS=local" "got ${reh[BACKUP_DISKS]}"; fi
        if [[ -n "${reh[BACKUP_ARCHIVE_PASSWORD]}" && -n "${reh[DB_BACKUP_PASSWORD]}" ]]; then ok "rehearsal: archive password + backup account still rendered"; else ko "rehearsal: archive password + backup account still rendered"; fi
        missing_warn=()
        for k in $WAIVABLE; do
            grep -q "^WARN  rehearsal: empty and waived: .*\b$k\b" <<<"$OUT" || missing_warn+=("$k")
        done
        if [[ ${#missing_warn[@]} -eq 0 ]]; then ok "rehearsal: a WARN line names every waived key"; else ko "rehearsal: a WARN line names every waived key" "missing: ${missing_warn[*]}"; fi
        if grep -q '^WARN  REHEARSAL MODE' <<<"$OUT" && grep -q 'MAIL_MAILER=log' <<<"$OUT" && grep -q 'BACKUP_DISKS=local' <<<"$OUT"; then
            ok "rehearsal: banner + MAIL_MAILER/BACKUP_DISKS overrides are announced"
        else
            ko "rehearsal: banner + overrides are announced" "$OUT"
        fi
        if no_value_printed; then ok "rehearsal: no value is printed"; else ko "rehearsal: a value was printed"; fi
        # The gate agrees with the render.
        set +e
        gate_out="$(bash "$CHECK_ENV" "$REH" --mode rehearsal 2>&1)"; gate_rc=$?
        set -e
        if [[ $gate_rc -eq 0 && "$gate_out" == *"REHEARSAL MODE"* && "$gate_out" == *"meets the REHEARSAL standards only"* ]]; then
            ok "rehearsal: check-env.sh --mode rehearsal accepts it and says REHEARSAL"
        else
            ko "rehearsal: check-env.sh --mode rehearsal accepts it" "$gate_out"
        fi
        set +e
        gate_out="$(bash "$CHECK_ENV" "$REH" --mode strict 2>&1)"; gate_rc=$?
        set -e
        if [[ $gate_rc -eq 1 ]]; then ok "rehearsal: check-env.sh --mode strict refuses a rehearsal .env"; else ko "rehearsal: --mode strict refuses a rehearsal .env" "$gate_out"; fi
    else
        ko "rehearsal: renders without SMTP, Telegram, Google Drive" "$OUT"
    fi

    # Strict render of the same input still fails, naming the same keys as before.
    run_render "${today_vars[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/strict-today.env" --optional "$OPT"
    strict_missing=()
    for k in $WAIVABLE; do
        [[ "$OUT" == *"$k is required"* ]] || strict_missing+=("$k")
    done
    if [[ $RC -eq 1 && ${#strict_missing[@]} -eq 0 && ! -e "$WORK/out/strict-today.env" ]]; then
        ok "strict: the same input fails and names every key the rehearsal waives"
    else
        ko "strict: the same input fails and names every waived key" "rc=$RC missing=${strict_missing[*]}"
    fi

    # Strict render with everything: RELEASE_REHEARSAL stays false, SMTP + Drive kept, gate PASS (strict).
    run_render "${real_vars[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/strict-full.env" --optional "$OPT"
    declare -A sf=()
    if [[ $RC -eq 0 ]] && parse_env_file "$WORK/out/strict-full.env" sf \
        && [[ "${sf[RELEASE_REHEARSAL]}" == "false" && "${sf[MAIL_MAILER]}" == "smtp" && "${sf[BACKUP_DISKS]}" == "google" ]] \
        && [[ "$OUT" != *WARN* ]]; then
        ok "strict: full render keeps RELEASE_REHEARSAL=false, smtp, google and prints no WARN"
    else
        ko "strict: full render" "rc=$RC $OUT"
    fi
    set +e
    gate_out="$(bash "$CHECK_ENV" "$WORK/out/strict-full.env" --mode strict 2>&1)"; gate_rc=$?
    set -e
    if [[ $gate_rc -eq 0 && "$gate_out" != *REHEARSAL* && "$gate_out" == *"meets the production standards"* ]]; then
        ok "strict: check-env.sh --mode strict passes with no REHEARSAL line"
    else
        ko "strict: check-env.sh --mode strict passes" "$gate_out"
    fi
    set +e
    gate_out="$(bash "$CHECK_ENV" "$WORK/out/strict-full.env" --mode rehearsal 2>&1)"; gate_rc=$?
    set -e
    if [[ $gate_rc -eq 1 ]]; then ok "strict: check-env.sh --mode rehearsal refuses a strict .env"; else ko "strict: --mode rehearsal refuses a strict .env"; fi

    # Rehearsal with everything provided: nothing is relaxed, only the marker.
    run_render "${real_vars[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/reh-full.env" --optional "$OPT" --rehearsal
    declare -A rf=()
    if [[ $RC -eq 0 ]] && parse_env_file "$WORK/out/reh-full.env" rf \
        && [[ "${rf[RELEASE_REHEARSAL]}" == "true" && "${rf[MAIL_MAILER]}" == "smtp" && "${rf[BACKUP_DISKS]}" == "google" \
            && "${rf[MAIL_FROM_ADDRESS]}" == "noreply@mail.example.test" && "$OUT" == *"no key was waived"* ]]; then
        ok "rehearsal with every key: smtp + google + provided MAIL_FROM_ADDRESS kept, only the marker set"
    else
        ko "rehearsal with every key" "rc=$RC $OUT"
    fi

    # Partial SMTP (host only) still falls back to the log mailer.
    partial=()
    for v in "${today_vars[@]}"; do partial+=("$v"); done
    partial+=("MAIL_HOST=smtp.example.test")
    run_render "${partial[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/reh-partial.env" --optional "$OPT" --rehearsal
    declare -A rp=()
    if [[ $RC -eq 0 ]] && parse_env_file "$WORK/out/reh-partial.env" rp && [[ "${rp[MAIL_MAILER]}" == "log" && "$OUT" == *"SMTP incomplete (MAIL_PORT MAIL_USERNAME MAIL_PASSWORD)"* ]]; then
        ok "rehearsal: partial SMTP -> log mailer, missing keys named"
    else
        ko "rehearsal: partial SMTP -> log mailer" "rc=$RC $OUT"
    fi

    # Never waived, even in rehearsal.
    for must in BACKUP_ARCHIVE_PASSWORD DB_BACKUP_PASSWORD APP_KEY DB_PASSWORD REDIS_PASSWORD CENTRAL_DOMAIN; do
        less=()
        for v in "${today_vars[@]}"; do [[ "$v" == "$must="* ]] || less+=("$v"); done
        run_render "${less[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/reh-missing.env" --optional "$OPT" --rehearsal
        if [[ $RC -eq 1 && "$OUT" == *"$must is required"* && ! -e "$WORK/out/reh-missing.env" ]]; then
            ok "rehearsal still requires $must"
        else
            ko "rehearsal still requires $must" "rc=$RC"
        fi
    done

    # --optional refusals are unchanged by --rehearsal.
    run_render "${today_vars[@]}" bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/reh-opt.env" --optional "$OPT,MAIL_HOST" --rehearsal
    if [[ $RC -eq 2 && "$OUT" == *"MAIL_HOST is mandatory"* ]]; then ok "rehearsal: --optional MAIL_HOST is still refused"; else ko "rehearsal: --optional MAIL_HOST is still refused" "rc=$RC"; fi

    # RELEASE_REHEARSAL is reserved: never from the environment, in either mode.
    for mode_flag in "" "--rehearsal"; do
        run_render "${real_vars[@]}" RELEASE_REHEARSAL=true bash "$RENDER" "$PROD_TEMPLATE" "$WORK/out/reserved.env" --optional "$OPT" $mode_flag
        if [[ $RC -eq 2 && "$OUT" == *"RELEASE_REHEARSAL is reserved"* && ! -e "$WORK/out/reserved.env" ]]; then
            ok "RELEASE_REHEARSAL from the environment is refused (${mode_flag:-strict})"
        else
            ko "RELEASE_REHEARSAL from the environment is refused (${mode_flag:-strict})" "rc=$RC"
        fi
    done
else
    printf '  skip  production template not present\n'
fi

# --rehearsal needs the marker key in the template.
run_render APP_KEY="$APP_KEY_V" DB_PASSWORD="$DB_V" bash "$RENDER" "$TEMPLATE" "$WORK/out/nomarker.env" --optional SESSION_DOMAIN --rehearsal
if [[ $RC -eq 2 && "$OUT" == *"needs a RELEASE_REHEARSAL= line"* && ! -e "$WORK/out/nomarker.env" ]]; then
    ok "--rehearsal refuses a template without the RELEASE_REHEARSAL marker"
else
    ko "--rehearsal refuses a template without the marker" "rc=$RC $OUT"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
