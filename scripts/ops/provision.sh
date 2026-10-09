#!/usr/bin/env bash
# Provisions a fresh Ubuntu 24.04 LTS VPS for the SaaS (OPS-1).
# Idempotent: every step can be re-run safely. Run ON THE SERVER as root:
#
#   bash provision.sh --list
#   bash provision.sh --env-file /root/sroor-provision.env
#   bash provision.sh --env-file /root/sroor-provision.env --only 41-mysql-users
#   bash provision.sh --env-file /root/sroor-provision.env --from 60-tls
#
# Full procedure, prerequisites and rollback: docs/07-operations/vps-runbook.md
# All secrets come from the env file (root-owned, chmod 600). Nothing secret is
# ever printed or written to the log.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"

STEPS=(
    "00-base|apt baseline, timezone, swap, unattended security upgrades"
    "10-ssh-users|admin + app accounts, SSH keys only, root login off, sudoers"
    "20-firewall|ufw (SSH, 80, 443) + fail2ban"
    "30-php|PHP-FPM ${PHP_VERSION:-8.4} (ondrej/php PPA) + extensions, dedicated pool, production ini"
    "40-mysql|MySQL 8.4 LTS (repo.mysql.com), loopback only, fresh-install hardening"
    "41-mysql-users|central database + least-privilege accounts: app, migrator, provisioner, backup, audit_pruner"
    "50-redis|Redis, loopback only, password, noeviction"
    "60-tls|wildcard certificate via certbot DNS-01"
    "70-nginx|nginx site for apex + *.domain, HTTPS only"
    "80-queue|supervisor running Horizon (or queue:work)"
    "85-scheduler|cron: schedule:run every minute"
    "90-app-layout|releases/ + shared/ layout, shared .env placeholder"
)

LOG_FILE="/var/log/sroor-provision.log"

usage() {
    cat <<'EOF'
usage: provision.sh --list
       provision.sh --env-file FILE [--only STEP]... [--from STEP]
EOF
    exit 2
}

list_steps() {
    local entry
    for entry in "${STEPS[@]}"; do
        printf '%-16s %s\n' "${entry%%|*}" "${entry#*|}"
    done
}

step_names() {
    local entry
    for entry in "${STEPS[@]}"; do
        printf '%s\n' "${entry%%|*}"
    done
}

ENV_FILE=""
ONLY=()
FROM=""
LIST=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --list) LIST=1 ;;
        --env-file)
            [[ $# -ge 2 ]] || usage
            ENV_FILE="$2"
            shift
            ;;
        --only)
            [[ $# -ge 2 ]] || usage
            ONLY+=("$2")
            shift
            ;;
        --from)
            [[ $# -ge 2 ]] || usage
            FROM="$2"
            shift
            ;;
        -h | --help) usage ;;
        *) usage ;;
    esac
    shift
done

if [[ "$LIST" -eq 1 ]]; then
    list_steps
    exit 0
fi

ALL_STEPS="$(step_names)"
for step in "${ONLY[@]}" ${FROM:+"$FROM"}; do
    grep -qxF -- "$step" <<<"$ALL_STEPS" || die "unknown step: $step (see --list)"
done

[[ -n "$ENV_FILE" ]] || usage
require_root
assert_private_file "$ENV_FILE"
load_env_file "$ENV_FILE"
apply_defaults
require_vars PLATFORM_DOMAIN

SELECTED=()
started=0
if [[ -z "$FROM" ]]; then
    started=1
fi
while IFS= read -r step; do
    if [[ ${#ONLY[@]} -gt 0 ]]; then
        if printf '%s\n' "${ONLY[@]}" | grep -qxF -- "$step"; then
            SELECTED+=("$step")
        fi
        continue
    fi
    if [[ "$step" == "$FROM" ]]; then
        started=1
    fi
    if [[ "$started" -eq 1 ]]; then
        SELECTED+=("$step")
    fi
done <<<"$ALL_STEPS"

touch "$LOG_FILE"
chmod 600 "$LOG_FILE"

for step in "${SELECTED[@]}"; do
    info "==> $step" | tee -a "$LOG_FILE"
    # Each step runs in its own bash process with the exported settings.
    bash "$SCRIPT_DIR/steps/$step.sh" 2>&1 | tee -a "$LOG_FILE"
    info "<== $step done" | tee -a "$LOG_FILE"
done

info "provisioning finished. Next: bash $SCRIPT_DIR/verify.sh --env-file $ENV_FILE"
