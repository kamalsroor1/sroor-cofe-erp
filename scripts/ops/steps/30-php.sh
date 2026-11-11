#!/usr/bin/env bash
# Step 30: PHP-FPM 8.5 + extensions + dedicated pool. Idempotent.
#
# CTO decision 2026-11-11: Ubuntu 26.04 LTS ships PHP 8.5 in its own archive,
# so PHP comes from Ubuntu (security updates through resolute-security and
# unattended-upgrades). No PPA: ondrej/php publishes no `resolute` dist, and a
# leftover entry would break `apt-get update`.
# OPcache is part of PHP 8.5 core (there is no php8.5-opcache package); its
# settings stay in php-sroor.ini.tmpl.
# Composer/Node are NOT installed: the release artifact is built in CI (OPS-3).
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars PHP_VERSION APP_USER APP_ROOT PHP_FPM_MAX_CHILDREN
[[ "$PHP_VERSION" == "8.5" ]] || die "PHP_VERSION must be 8.5 (CTO decision 2026-11-11: Ubuntu 26.04 archive; the CI release build uses the same version)"

V="$PHP_VERSION"

# Fail closed on a third-party PHP source: PHP must come from the Ubuntu archive.
if grep -rqsE 'ppa\.launchpad(content)?\.net/ondrej/php' /etc/apt/sources.list /etc/apt/sources.list.d/; then
    die "an ondrej/php PPA entry exists in /etc/apt/sources.list*; remove it (PHP $V comes from the Ubuntu archive) and re-run"
fi
apt-get update -q

apt_install "php$V-fpm" "php$V-cli" "php$V-bcmath" "php$V-intl" "php$V-mysql" "php$V-gd" \
    "php$V-zip" "php$V-mbstring" "php$V-xml" "php$V-curl" "php$V-readline" \
    "php$V-redis"

# /usr/bin/php is what supervisor (Horizon), cron (scheduler) and deploy.sh run.
update-alternatives --set php "/usr/bin/php$V"
installed="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$installed" == "$V" ]] || die "/usr/bin/php is $installed, expected $V"

for ext in bcmath intl pdo_mysql redis gd zip mbstring; do
    php -m | grep -qix "$ext" || die "PHP extension missing after install: $ext"
done
# Built into PHP 8.5 core (no separate package); listed under [Zend Modules].
php -m | grep -qix 'Zend OPcache' || die "OPcache is not loaded"

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
