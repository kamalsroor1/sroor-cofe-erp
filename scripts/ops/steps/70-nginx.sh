#!/usr/bin/env bash
# Step 70: nginx site (HTTPS only, apex + wildcard). Needs step 60's certificate. Idempotent.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars PLATFORM_DOMAIN APP_ROOT HSTS_MAX_AGE
[[ "$HSTS_MAX_AGE" =~ ^[0-9]+$ ]] || die "HSTS_MAX_AGE must be numeric"
[[ -f "/etc/letsencrypt/live/$PLATFORM_DOMAIN/fullchain.pem" ]] ||
    die "certificate for $PLATFORM_DOMAIN not found - run step 60-tls first"

apt_install nginx

OPS_CHANGED=0
install_template nginx-hardening.conf.tmpl /etc/nginx/conf.d/00-sroor-hardening.conf 0644 root:root
install_template nginx-site.conf.tmpl /etc/nginx/sites-available/sroor.conf 0644 root:root
if [[ "$(readlink -f /etc/nginx/sites-enabled/sroor.conf 2>/dev/null)" != "/etc/nginx/sites-available/sroor.conf" ]]; then
    ln -sfn /etc/nginx/sites-available/sroor.conf /etc/nginx/sites-enabled/sroor.conf
    OPS_CHANGED=1
fi
if [[ -L /etc/nginx/sites-enabled/default ]]; then
    rm -f /etc/nginx/sites-enabled/default
    OPS_CHANGED=1
fi

nginx -t
systemctl enable nginx
if [[ "$OPS_CHANGED" -eq 1 ]]; then
    systemctl reload-or-restart nginx
else
    systemctl start nginx
fi

info "nginx OK (serving $APP_ROOT/current/backend/public once the first release is deployed)"
