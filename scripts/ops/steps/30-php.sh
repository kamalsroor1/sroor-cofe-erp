#!/usr/bin/env bash
# Step 30: PHP-FPM 8.3 (Ubuntu 24.04 packages) + extensions + dedicated pool. Idempotent.
# Composer/Node are NOT installed: the release artifact is built in CI (OPS-3).
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars PHP_VERSION APP_USER APP_ROOT PHP_FPM_MAX_CHILDREN
[[ "$PHP_VERSION" == "8.3" ]] || die "PHP_VERSION must be 8.3 (Ubuntu 24.04 package set; Laravel 13 needs >= 8.3)"

V="$PHP_VERSION"
apt_install "php$V-fpm" "php$V-cli" "php$V-bcmath" "php$V-intl" "php$V-mysql" "php$V-gd" \
    "php$V-zip" "php$V-mbstring" "php$V-xml" "php$V-curl" "php$V-opcache" "php$V-readline" \
    php-redis

for ext in bcmath intl pdo_mysql redis gd zip mbstring; do
    php -m | grep -qix "$ext" || die "PHP extension missing after install: $ext"
done

OPS_CHANGED=0
install_template php-sroor.ini.tmpl "/etc/php/$V/fpm/conf.d/99-sroor.ini" 0644 root:root
install_template php-sroor.ini.tmpl "/etc/php/$V/cli/conf.d/99-sroor.ini" 0644 root:root
install_template php-fpm-pool.conf.tmpl "/etc/php/$V/fpm/pool.d/sroor.conf" 0644 root:root

# The stock www pool is not used.
if [[ -f "/etc/php/$V/fpm/pool.d/www.conf" ]]; then
    mv "/etc/php/$V/fpm/pool.d/www.conf" "/etc/php/$V/fpm/pool.d/www.conf.disabled"
    OPS_CHANGED=1
fi

"php-fpm$V" -t
systemctl enable "php$V-fpm"
if [[ "$OPS_CHANGED" -eq 1 ]]; then
    systemctl restart "php$V-fpm"
else
    systemctl start "php$V-fpm"
fi

info "php OK ($(php -r 'echo PHP_VERSION;'))"
