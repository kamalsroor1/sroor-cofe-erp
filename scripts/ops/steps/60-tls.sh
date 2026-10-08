#!/usr/bin/env bash
# Step 60: wildcard certificate (apex + *.domain) via certbot DNS-01. Idempotent
# (--keep-until-expiring). Renewal: the packaged certbot.timer; the deploy hook
# reloads nginx after each renewal.
#
# Prerequisite (CTO): a DNS API token limited to editing this zone, stored in
# CERTBOT_DNS_CREDENTIALS_FILE (root:root 0600). See vps-runbook.md §7.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars PLATFORM_DOMAIN CERTBOT_EMAIL CERTBOT_DNS_PLUGIN CERTBOT_DNS_CREDENTIALS_FILE CERTBOT_DNS_PROPAGATION_SECONDS
[[ "$PLATFORM_DOMAIN" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]] ||
    die "PLATFORM_DOMAIN must be a lowercase apex domain like example.com"
[[ "$CERTBOT_DNS_PROPAGATION_SECONDS" =~ ^[0-9]+$ ]] || die "CERTBOT_DNS_PROPAGATION_SECONDS must be numeric"

# TODO(CTO): confirm the DNS provider of the platform domain. Only plugins
# packaged in Ubuntu 24.04 that take a credentials file are supported.
case "$CERTBOT_DNS_PLUGIN" in
    cloudflare | digitalocean | linode | ovh | rfc2136) ;;
    *) die "CERTBOT_DNS_PLUGIN must be one of: cloudflare digitalocean linode ovh rfc2136" ;;
esac

assert_private_file "$CERTBOT_DNS_CREDENTIALS_FILE"

apt_install certbot "python3-certbot-dns-$CERTBOT_DNS_PLUGIN"

staging_flag=()
if [[ "$CERTBOT_STAGING" == "1" ]]; then
    staging_flag=(--staging)
    warn "Let's Encrypt STAGING certificate (rehearsal only, browsers will not trust it)"
fi

info "requesting/keeping certificate for $PLATFORM_DOMAIN and *.$PLATFORM_DOMAIN"
certbot certonly --non-interactive --agree-tos \
    --email "$CERTBOT_EMAIL" \
    --cert-name "$PLATFORM_DOMAIN" \
    --keep-until-expiring \
    "--dns-$CERTBOT_DNS_PLUGIN" \
    "--dns-$CERTBOT_DNS_PLUGIN-credentials" "$CERTBOT_DNS_CREDENTIALS_FILE" \
    "--dns-$CERTBOT_DNS_PLUGIN-propagation-seconds" "$CERTBOT_DNS_PROPAGATION_SECONDS" \
    "${staging_flag[@]}" \
    -d "$PLATFORM_DOMAIN" -d "*.$PLATFORM_DOMAIN"

hook=/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
tmp_hook="$(mktemp)"
cat >"$tmp_hook" <<'EOF'
#!/bin/sh
# Managed by scripts/ops (OPS-1): pick up renewed certificates.
set -eu
if systemctl is-active --quiet nginx; then
    nginx -t -q
    systemctl reload nginx
fi
EOF
install_file "$tmp_hook" "$hook" 0755 root:root
rm -f "$tmp_hook"

systemctl enable --now certbot.timer
info "tls OK"
