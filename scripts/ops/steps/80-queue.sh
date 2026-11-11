#!/usr/bin/env bash
# Step 80: supervisor running Horizon (default) or plain queue:work. Idempotent.
# Before the first release exists the program simply stays in BACKOFF/FATAL;
# the first deploy (OPS-3) starts it.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars APP_ROOT APP_USER QUEUE_MODE QUEUE_WORKER_PROCESSES
[[ "$QUEUE_WORKER_PROCESSES" =~ ^[1-9][0-9]*$ ]] || die "QUEUE_WORKER_PROCESSES must be a positive integer"

apt_install supervisor
systemctl enable --now supervisor

[[ "$APP_ROOT" == /* && "$APP_ROOT" != "/" ]] || die "APP_ROOT must be an absolute path other than /"
# supervisor refuses a program whose log directory is missing (CANT_REREAD),
# and step 90 runs after this one: create the log path with step 90's modes.
install -d -m 2750 -o "$APP_USER" -g www-data "$APP_ROOT" "$APP_ROOT/shared"
install -d -m 2770 -o "$APP_USER" -g www-data "$APP_ROOT/shared/storage" "$APP_ROOT/shared/storage/logs"

OPS_CHANGED=0
case "$QUEUE_MODE" in
    horizon)
        install_template supervisor-horizon.conf.tmpl /etc/supervisor/conf.d/sroor-horizon.conf 0644 root:root
        stale=/etc/supervisor/conf.d/sroor-worker.conf
        ;;
    worker)
        install_template supervisor-worker.conf.tmpl /etc/supervisor/conf.d/sroor-worker.conf 0644 root:root
        stale=/etc/supervisor/conf.d/sroor-horizon.conf
        ;;
    *) die "QUEUE_MODE must be horizon or worker" ;;
esac
if [[ -f "$stale" ]]; then
    rm -f "$stale"
    OPS_CHANGED=1
fi

# Always reconcile: a previous run may have installed the file but failed at
# reread. `update` only restarts programs whose config changed.
supervisorctl reread
supervisorctl update

if [[ ! -e "$APP_ROOT/current" ]]; then
    warn "no release deployed yet: the queue program starts after the first deploy"
fi
info "queue OK (mode: $QUEUE_MODE)"
