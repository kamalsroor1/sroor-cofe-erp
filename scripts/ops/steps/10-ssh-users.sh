#!/usr/bin/env bash
# Step 10: accounts + SSH hardening (keys only). Idempotent.
#
# Lock-out guard: password/root login is only disabled after the admin
# account has a valid authorized key. Keep your current root session open
# until you have logged in as ADMIN_USER from a second terminal.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars ADMIN_USER APP_USER ADMIN_SSH_PUBKEY_FILE DEPLOY_SSH_PUBKEY_FILE SSH_PORT PHP_VERSION
require_identifier ADMIN_USER APP_USER
[[ "$SSH_PORT" =~ ^[0-9]+$ ]] || die "SSH_PORT must be numeric"
[[ "$ADMIN_USER" != "$APP_USER" ]] || die "ADMIN_USER and APP_USER must differ"

# Validates a public key file and installs it as the only authorized key.
install_authorized_key() {
    local user="$1" pubkey_file="$2"
    [[ -f "$pubkey_file" ]] || die "public key file not found: $pubkey_file"
    ssh-keygen -l -f "$pubkey_file" >/dev/null || die "$pubkey_file is not a valid SSH public key"
    if grep -q 'PRIVATE KEY' "$pubkey_file"; then
        die "$pubkey_file contains a PRIVATE key - use the .pub file"
    fi
    local home
    home="$(getent passwd "$user" | cut -d: -f6)"
    install -d -m 0700 -o "$user" -g "$user" "$home/.ssh"
    install_file "$pubkey_file" "$home/.ssh/authorized_keys" 0600 "$user:$user"
}

if ! id "$ADMIN_USER" >/dev/null 2>&1; then
    info "creating admin account $ADMIN_USER"
    adduser --disabled-password --gecos "" "$ADMIN_USER"
fi
usermod -aG sudo "$ADMIN_USER"
# Key-only SSH, but sudo needs a password: the admin sets one interactively
# (`passwd opsadmin` from the root session). Never scripted, never stored.
if passwd -S "$ADMIN_USER" | awk '{print $2}' | grep -qE '^(L|NP)$'; then
    warn "$ADMIN_USER has no password yet: run 'passwd $ADMIN_USER' before closing the root session (needed for sudo)"
fi
install_authorized_key "$ADMIN_USER" "$ADMIN_SSH_PUBKEY_FILE"

if ! id "$APP_USER" >/dev/null 2>&1; then
    info "creating service account $APP_USER"
    adduser --disabled-password --gecos "" "$APP_USER"
fi
usermod -aG www-data "$APP_USER"
install_authorized_key "$APP_USER" "$DEPLOY_SSH_PUBKEY_FILE"

info "sudoers for $APP_USER (php-fpm reload + own supervisor programs only)"
tmp_sudoers="$(mktemp)"
render_template "$OPS_TEMPLATES_DIR/sudoers-app.tmpl" "$tmp_sudoers"
visudo -cf "$tmp_sudoers" >/dev/null || die "rendered sudoers file is invalid"
install_file "$tmp_sudoers" /etc/sudoers.d/sroor-app 0440 root:root
rm -f "$tmp_sudoers"

info "sshd hardening"
# If the firewall is already on (re-run with a new SSH_PORT), open the port first.
if command -v ufw >/dev/null && ufw status | grep -q '^Status: active'; then
    ufw limit "$SSH_PORT/tcp" comment 'ssh'
fi
OPS_CHANGED=0
install_template sshd-hardening.conf.tmpl /etc/ssh/sshd_config.d/00-sroor-hardening.conf 0644 root:root
sshd -t || die "sshd config test failed - not reloading"
if [[ "$OPS_CHANGED" -eq 1 ]]; then
    # Ubuntu 24.04 uses socket activation: the port lives in ssh.socket.
    systemctl daemon-reload
    if systemctl is-active --quiet ssh.socket; then
        systemctl restart ssh.socket
    fi
    systemctl reload-or-restart ssh.service
    warn "sshd reloaded: verify 'ssh -p $SSH_PORT $ADMIN_USER@<server>' works in a NEW terminal before closing this one"
fi

info "ssh/users OK"
