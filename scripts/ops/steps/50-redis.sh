#!/usr/bin/env bash
# Step 50: Redis (cache, sessions, queue/Horizon), loopback only, password. Idempotent.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars REDIS_MAXMEMORY
require_secret REDIS_PASSWORD
[[ "$REDIS_MAXMEMORY" =~ ^[0-9]+(mb|gb)$ ]] || die "REDIS_MAXMEMORY must look like 1gb or 512mb"

apt_install redis-server

OPS_CHANGED=0
install_template redis-sroor.conf.tmpl /etc/redis/sroor.conf 0640 root:redis
# Our file is included LAST so its values win over the stock redis.conf.
ensure_line /etc/redis/redis.conf 'include /etc/redis/sroor.conf'

systemctl enable redis-server
if [[ "$OPS_CHANGED" -eq 1 ]]; then
    systemctl restart redis-server
else
    systemctl start redis-server
fi

# REDISCLI_AUTH keeps the password out of argv / ps.
pong="$(REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli -h 127.0.0.1 ping)"
[[ "$pong" == "PONG" ]] || die "redis did not answer PING with the configured password"

info "redis OK"
