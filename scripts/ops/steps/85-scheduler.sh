#!/usr/bin/env bash
# Step 85: Laravel scheduler via cron, every minute, as the app user. Idempotent.
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars APP_ROOT APP_USER

apt_install cron
# cron.d file names must not contain dots; mode 0644, owned by root.
install_template cron-scheduler.tmpl /etc/cron.d/sroor-scheduler 0644 root:root
systemctl enable --now cron

info "scheduler OK"
