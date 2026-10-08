#!/usr/bin/env bash
# Step 00: OS baseline (Ubuntu 24.04 LTS). Idempotent.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars TIMEZONE SWAP_SIZE_GB

# shellcheck source=/dev/null
. /etc/os-release
if [[ "${ID:-}" != "ubuntu" || "${VERSION_ID:-}" != "24.04" ]]; then
    die "supported OS: Ubuntu 24.04 LTS (found ${ID:-unknown} ${VERSION_ID:-unknown})"
fi

info "apt update + upgrade"
apt-get update -q
DEBIAN_FRONTEND=noninteractive apt-get -y -q -o Dpkg::Options::=--force-confold upgrade

apt_install ca-certificates curl gnupg unzip git acl gettext-base openssl \
    unattended-upgrades apt-listchanges logrotate cron rsync

info "timezone -> $TIMEZONE (the app stores UTC; tenant timezones are display-only)"
timedatectl set-timezone "$TIMEZONE"

info "unattended security upgrades"
install_template auto-upgrades.tmpl /etc/apt/apt.conf.d/20auto-upgrades 0644 root:root
systemctl enable --now unattended-upgrades

if [[ "$SWAP_SIZE_GB" =~ ^[0-9]+$ && "$SWAP_SIZE_GB" -gt 0 ]]; then
    if swapon --show=NAME --noheadings | grep -q .; then
        info "swap already active"
    else
        info "creating ${SWAP_SIZE_GB}G swap file"
        if [[ ! -f /swapfile ]]; then
            fallocate -l "${SWAP_SIZE_GB}G" /swapfile
            chmod 600 /swapfile
            mkswap /swapfile
        fi
        swapon /swapfile
        ensure_line /etc/fstab '/swapfile none swap sw 0 0'
    fi
    ensure_line /etc/sysctl.d/99-sroor.conf 'vm.swappiness = 10'
    sysctl -q -p /etc/sysctl.d/99-sroor.conf
fi

info "base OK"
