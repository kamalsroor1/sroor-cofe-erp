#!/usr/bin/env bash
# Step 90: release layout used by the deploy pipeline (OPS-3). Idempotent.
#
#   $APP_ROOT/
#     releases/<sha>/      one directory per release (artifact from CI)
#     current -> releases/<sha>   (switched atomically by OPS-3)
#     shared/.env          production env, owner app user, chmod 600 (filled by OPS-4)
#     shared/storage/      persistent storage, symlinked into every release
#
# Directories are setgid www-data so nginx can read public/ assets; .env is
# readable by the app user only.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars APP_ROOT APP_USER
[[ "$APP_ROOT" == /* && "$APP_ROOT" != "/" ]] || die "APP_ROOT must be an absolute path other than /"

install -d -m 2750 -o "$APP_USER" -g www-data "$APP_ROOT" "$APP_ROOT/releases" "$APP_ROOT/shared"

for dir in storage storage/app storage/app/public storage/app/private storage/framework \
    storage/framework/cache storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs; do
    install -d -m 2770 -o "$APP_USER" -g www-data "$APP_ROOT/shared/$dir"
done

if [[ ! -f "$APP_ROOT/shared/.env" ]]; then
    install -m 0600 -o "$APP_USER" -g "$APP_USER" /dev/null "$APP_ROOT/shared/.env"
    warn "created an EMPTY $APP_ROOT/shared/.env - fill it from GitHub Environment secrets (OPS-4), then run check-env.sh"
else
    chown "$APP_USER:$APP_USER" "$APP_ROOT/shared/.env"
    chmod 0600 "$APP_ROOT/shared/.env"
fi

info "app layout OK ($APP_ROOT)"
