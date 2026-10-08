#!/usr/bin/env bash
# Self-test for the gitleaks gate (OPS-4). Proves that .gitleaks.toml:
#   1. stays green on a clean commit full of placeholders / env references,
#   2. FAILS on every planted secret shape this repo has leaked before,
#   3. never prints a planted value (output is --redact'ed),
#   4. a .gitleaksignore fingerprint silences ONE historical finding only:
#      a new commit adding a secret to the same file still fails.
#
# Runs in a throwaway git repo under mktemp; never touches the real repo,
# never needs network, never needs a server.
#
#   bash scripts/ops/tests/gitleaks-selftest.sh [path/to/gitleaks]
#
# Planted values are random and generated at runtime, so no credential-shaped
# literal lives in this file.

set -euo pipefail

TESTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$TESTS_DIR/../../.." && pwd)"
CONFIG="$REPO_ROOT/.gitleaks.toml"
GITLEAKS="${1:-gitleaks}"

if ! command -v "$GITLEAKS" >/dev/null 2>&1 && [[ ! -x "$GITLEAKS" ]]; then
    printf 'gitleaks binary not found: %s\n' "$GITLEAKS" >&2
    exit 2
fi
if [[ ! -f "$CONFIG" ]]; then
    printf 'missing %s\n' "$CONFIG" >&2
    exit 2
fi

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
}

# 40 random hex chars (no fork-heavy tools needed beyond od).
rand_hex() {
    od -An -N20 -tx1 /dev/urandom | tr -d ' \n'
}
rand_b64_43() {
    # 43 chars from the base64 alphabet, built from random hex.
    local h
    h="$(rand_hex)$(rand_hex)"
    printf '%s' "${h:0:43}"
}

REPO="$WORK/repo"
mkdir -p "$REPO"
git -C "$REPO" init -q
git -C "$REPO" config user.email selftest@example.invalid
git -C "$REPO" config user.name selftest
git -C "$REPO" config commit.gpgsign false
git -C "$REPO" config core.autocrlf false
cp "$CONFIG" "$REPO/.gitleaks.toml"
git -C "$REPO" add .gitleaks.toml
git -C "$REPO" commit -q -m init

commit_file() {
    local path="$1" content="$2"
    mkdir -p "$(dirname "$REPO/$path")"
    printf '%s\n' "$content" >"$REPO/$path"
    git -C "$REPO" add -- "$path"
    git -C "$REPO" commit -q -m "add $path"
}

# Scans the last commit only (same mode as the CI job on a push / PR range).
# Sets SCAN_RC, SCAN_OUT (stdout+stderr) and SCAN_REPORT (json text).
scan_last_commit() {
    local report="$WORK/report.json"
    rm -f "$report"
    set +e
    SCAN_OUT="$(cd "$REPO" && "$GITLEAKS" git --config .gitleaks.toml --redact --no-banner --no-color \
        --ignore-gitleaks-allow --log-opts="HEAD~1..HEAD" -f json -r "$report" . 2>&1)"
    SCAN_RC=$?
    set -e
    SCAN_REPORT="$(cat "$report" 2>/dev/null || printf '[]')"
}

scan_full_history() {
    local report="$WORK/report.json"
    rm -f "$report"
    set +e
    SCAN_OUT="$(cd "$REPO" && "$GITLEAKS" git --config .gitleaks.toml --redact --no-banner --no-color \
        --ignore-gitleaks-allow --gitleaks-ignore-path .gitleaksignore -f json -r "$report" . 2>&1)"
    SCAN_RC=$?
    set -e
    SCAN_REPORT="$(cat "$report" 2>/dev/null || printf '[]')"
}

expect_clean() {
    local name="$1"
    scan_last_commit
    if [[ $SCAN_RC -eq 0 ]]; then
        ok "$name"
    else
        ko "$name (exit $SCAN_RC; rules: $(rules_in_report))"
    fi
}

rules_in_report() {
    printf '%s' "$SCAN_REPORT" | grep -o '"RuleID": *"[^"]*"' | sed 's/.*"\([^"]*\)"$/\1/' | sort -u | tr '\n' ' '
}

expect_leak() {
    local name="$1" rule="$2" value="$3"
    scan_last_commit
    if [[ $SCAN_RC -ne 1 ]]; then
        ko "$name: expected exit 1, got $SCAN_RC"
        return
    fi
    if ! printf '%s' "$SCAN_REPORT" | grep -q "\"RuleID\": *\"$rule\""; then
        ko "$name: rule $rule did not fire (fired: $(rules_in_report))"
        return
    fi
    if [[ "$SCAN_OUT$SCAN_REPORT" == *"$value"* ]]; then
        ko "$name: planted value appeared in gitleaks output (redaction broken)"
        return
    fi
    ok "$name -> $rule"
}

printf 'gitleaks self-test (%s)\n' "$("$GITLEAKS" version 2>/dev/null || printf 'unknown')"

# --- 1. clean commit: placeholders and references must not fire ------------
CLEAN_ENV='APP_KEY=
DB_PASSWORD=
REDIS_PASSWORD=null
MAIL_PASSWORD=null
TELEGRAM_BOT_TOKEN=
QUICK_LOGIN_TOKEN_TTL=480
DEPLOY_WEBHOOK_HMAC_SECRET='
commit_file "backend/.env.example" "$CLEAN_ENV"
expect_clean "clean .env.example with empty / null secrets"

CLEAN_WF='env:
  DB_PASSWORD: '"''"'
  APP_KEY: ${{ secrets.APP_KEY }}
  TELEGRAM_BOT_TOKEN: ${{ secrets.TELEGRAM_BOT_TOKEN }}
run: |
  DB_PASSWORD="$DB_PASSWORD" php artisan migrate'
commit_file ".github/workflows/release.yml" "$CLEAN_WF"
expect_clean "workflow that references secrets instead of holding them"

CLEAN_PY='import os
password = os.environ["SSH_PASSWORD"]
client.connect(host, username=user, password=password)'
commit_file "tools/ok.py" "$CLEAN_PY"
expect_clean "script that reads its password from the environment"

CLEAN_FIXTURE='export MYSQL_APP_PASSWORD="FixtureAppPasswordOnly0000"
FAKE_SECRET="fixture-secret-value-must-never-be-printed"
DB_PASSWORD=FixtureDbPassword'
commit_file "scripts/ops/tests/fixture.sh" "$CLEAN_FIXTURE"
expect_clean "fixture-prefixed fake credentials in tests"

CLEAN_PHP_FIXTURE="<?php
\$payload = ['current_password' => 'FixtureCurrentPass123', 'new_password' => 'FixtureNewSecretPass123'];"
commit_file "backend/tests/Feature/Api/FixtureApiTest.php" "$CLEAN_PHP_FIXTURE"
expect_clean "fixture-prefixed values also pass the upstream generic rules"

# --- 2. planted secrets: each must fail ------------------------------------
V="$(rand_hex)"
commit_file "deploy_x.py" "client.connect(host, username='u', password='$V')"
expect_leak "hardcoded SSH password in a Python script" "sroor-hardcoded-password" "$V"

V="$(rand_hex)"
commit_file "sync_x.py" "HOST = 'h'
PASS = \"$V\""
expect_leak "module-level PASS constant in a Python script" "sroor-hardcoded-password" "$V"

V="$(rand_hex)"
commit_file "backend/.env.production" "DB_PASSWORD=$V"
expect_leak "dotenv secret committed" "sroor-dotenv-secret" "$V"

V="$(rand_hex)"
commit_file ".github/workflows/leak.yml" "env:
  DEPLOY_WEBHOOK_TOKEN: '$V'"
expect_leak "secret inlined in a workflow env block" "sroor-dotenv-secret" "$V"

V="$(rand_hex)"
commit_file ".agents/mcp_config.json" "{\"env\": {\"PERSONAL_ACCESS_TOKEN\": \"$V\"}}"
expect_leak "token in a JSON tool config" "sroor-json-credential" "$V"

V="$(rand_hex)"
commit_file "scripts/notify.sh" "curl -fsS \"https://example.invalid/hook.php?token=$V\""
expect_leak "token in a URL query string" "sroor-url-token" "$V"

V="$(rand_b64_43)="
commit_file "config/notes.txt" "key: base64:$V"
expect_leak "Laravel APP_KEY" "sroor-laravel-app-key" "$V"

V="1//0$(rand_hex)"
commit_file "docs/drive.txt" "refresh $V"
expect_leak "Google OAuth refresh token" "sroor-google-refresh-token" "$V"

V="$(rand_hex)"
commit_file "tools/inline_allow.py" "password='$V'  # gitleaks:allow"
expect_leak "inline gitleaks:allow comment cannot bypass the CI gate" "sroor-hardcoded-password" "$V"

# --- 3. baseline semantics ---------------------------------------------------
# Rebuild a small history: one legacy secret, baselined by fingerprint.
REPO="$WORK/repo2"
mkdir -p "$REPO"
git -C "$REPO" init -q
git -C "$REPO" config user.email selftest@example.invalid
git -C "$REPO" config user.name selftest
git -C "$REPO" config commit.gpgsign false
git -C "$REPO" config core.autocrlf false
cp "$CONFIG" "$REPO/.gitleaks.toml"
git -C "$REPO" add .gitleaks.toml
git -C "$REPO" commit -q -m init

V="$(rand_hex)"
commit_file "legacy_deploy.py" "host = 'h'
password='$V'"
LEGACY_SHA="$(git -C "$REPO" rev-parse HEAD)"
printf '%s:legacy_deploy.py:sroor-hardcoded-password:2\n' "$LEGACY_SHA" >"$REPO/.gitleaksignore"
git -C "$REPO" add .gitleaksignore
git -C "$REPO" commit -q -m "baseline legacy finding"

scan_full_history
if [[ $SCAN_RC -eq 0 ]]; then
    ok "baselined legacy finding is ignored in a full-history scan"
else
    ko "baselined legacy finding still fails (exit $SCAN_RC; rules: $(rules_in_report))"
fi

V2="$(rand_hex)"
printf "ssh_password='%s'\n" "$V2" >>"$REPO/legacy_deploy.py"
git -C "$REPO" add legacy_deploy.py
git -C "$REPO" commit -q -m "touch legacy file"
scan_full_history
if [[ $SCAN_RC -eq 1 ]] && [[ "$SCAN_OUT$SCAN_REPORT" != *"$V2"* ]]; then
    ok "a NEW secret in a baselined legacy file still fails"
else
    ko "a new secret in a baselined legacy file was not caught (exit $SCAN_RC)"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
