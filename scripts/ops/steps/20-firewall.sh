#!/usr/bin/env bash
# Step 20: ufw (deny in, allow SSH/80/443) + fail2ban. Idempotent.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars SSH_PORT
[[ "$SSH_PORT" =~ ^[0-9]+$ ]] || die "SSH_PORT must be numeric"

apt_install ufw fail2ban

info "ufw rules"
ufw default deny incoming
ufw default allow outgoing
# SSH first, so enabling the firewall can never cut the current session.
ufw limit "$SSH_PORT/tcp" comment 'ssh'
ufw allow 80/tcp comment 'http (redirect to https)'
ufw allow 443/tcp comment 'https'
if [[ "$SSH_PORT" != "22" ]]; then
    # Remove the default port only once the custom one is allowed.
    if ufw status | grep -qE '^22/tcp'; then
        ufw delete limit 22/tcp
    fi
fi
ufw --force enable

info "fail2ban"
OPS_CHANGED=0
install_template fail2ban-jail.local.tmpl /etc/fail2ban/jail.local 0644 root:root
systemctl enable fail2ban
if [[ "$OPS_CHANGED" -eq 1 ]]; then
    systemctl restart fail2ban
else
    systemctl start fail2ban
fi

info "firewall OK"
