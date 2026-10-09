#!/usr/bin/env bash
# Step 30: PHP-FPM 8.4 + extensions + dedicated pool. Idempotent.
#
# Ubuntu 24.04 ships PHP 8.3 only. PHP 8.4 (CTO decision W1 Q6) comes from the
# ondrej/php PPA: php.net publishes no apt repository, and this PPA is
# maintained by the Debian PHP maintainer (Ondřej Surý). The PPA signing key
# is fetched by add-apt-repository from Launchpad over HTTPS.
# Composer/Node are NOT installed: the release artifact is built in CI (OPS-3).
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars PHP_VERSION APP_USER APP_ROOT PHP_FPM_MAX_CHILDREN
[[ "$PHP_VERSION" == "8.4" ]] || die "PHP_VERSION must be 8.4 (CTO decision W1 Q6; the CI release build uses the same version)"

V="$PHP_VERSION"

if ! grep -rqsE 'ppa\.launchpad(content)?\.net/ondrej/php' /etc/apt/sources.list.d/; then
    info "adding the ondrej/php PPA (PHP $V for Ubuntu 24.04)"
    apt_install software-properties-common
    add-apt-repository -y ppa:ondrej/php
fi
apt-get update -q

apt_install "php$V-fpm" "php$V-cli" "php$V-bcmath" "php$V-intl" "php$V-mysql" "php$V-gd" \
    "php$V-zip" "php$V-mbstring" "php$V-xml" "php$V-curl" "php$V-opcache" "php$V-readline" \
    "php$V-redis"

# /usr/bin/php is what supervisor (Horizon), cron (scheduler) and deploy.sh run.
update-alternatives --set php "/usr/bin/php$V"
installed="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$installed" == "$V" ]] || die "/usr/bin/php is $installed, expected $V"

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
