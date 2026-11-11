#!/usr/bin/env bash
# Step 40: MySQL 8.4 LTS from the Ubuntu 26.04 archive, loopback only. Idempotent.
#
# CTO decision 2026-11-11: Ubuntu 26.04 ships MySQL 8.4 LTS (`mysql-server`,
# source package mysql-8.4, component main), the same series the CI `mysql`
# job tests against. Using the archive instead of repo.mysql.com means no
# third-party signing key or apt pin, and security updates arrive through
# resolute-security (Canonical security support for main). Rationale and the
# trade-off (unattended upgrades now restart mysqld): vps-runbook.md §1 + §11.
# root keeps auth_socket (only the OS root can log in as MySQL root, no password).
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars MYSQL_INNODB_BUFFER_POOL
[[ "$MYSQL_INNODB_BUFFER_POOL" =~ ^[0-9]+[MG]$ ]] || die "MYSQL_INNODB_BUFFER_POOL must look like 2G or 512M"

# Refuse to silently upgrade/downgrade an existing server that is not 8.4
# (fresh VPS only; a different major needs a manual upgrade after a backup).
if command -v mysqld >/dev/null 2>&1; then
    existing="$(mysqld --version | grep -oE 'Ver [0-9]+\.[0-9]+' | awk '{print $2}')"
    [[ "$existing" == "8.4" ]] || die "mysqld $existing is already installed; this step only installs 8.4 LTS on a fresh server (upgrade manually after a backup)"
fi

# Fail closed on a second MySQL source: mixing repo.mysql.com (mysql-community-*)
# with the Ubuntu packages would let apt pick either origin.
if grep -rqsE 'repo\.mysql\.com' /etc/apt/sources.list /etc/apt/sources.list.d/; then
    die "a repo.mysql.com apt source exists in /etc/apt/sources.list*; MySQL comes from the Ubuntu archive - remove that source (and any mysql pin in /etc/apt/preferences.d/) and re-run"
fi
apt-get update -q

# Check the series BEFORE installing anything (Ubuntu versions look like 8.4.N-0ubuntu...).
candidate="$(apt-cache policy mysql-server | awk '/Candidate:/{print $2}')"
[[ "$candidate" == 8.4.* ]] || die "apt candidate for mysql-server is '${candidate:-none}', expected 8.4.x from the Ubuntu archive"

# Empty root password in the installer = root authenticates with auth_socket
# (Ubuntu's mysql-server postinst). Nothing secret is preseeded.
debconf-set-selections <<'EOF'
mysql-server mysql-server/root_password password
mysql-server mysql-server/root_password_again password
EOF
apt_install mysql-server mysql-client

OPS_CHANGED=0
install_template mysql-sroor.cnf.tmpl /etc/mysql/mysql.conf.d/99-sroor.cnf 0644 root:root
systemctl enable mysql
if [[ "$OPS_CHANGED" -eq 1 ]]; then
    systemctl restart mysql
else
    systemctl start mysql
fi

version="$(mysql --protocol=socket -uroot -N -B -e 'SELECT VERSION()')"
[[ "$version" == 8.4.* ]] || die "MySQL 8.4 LTS expected, found $version"

info "removing anonymous accounts (fresh-install hardening; no data is touched)"
mysql --protocol=socket -uroot -N -B <<'SQL'
DROP USER IF EXISTS ''@'localhost';
DROP USER IF EXISTS ''@'%';
SQL

remote_root="$(mysql --protocol=socket -uroot -N -B -e "SELECT COUNT(*) FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1')")"
[[ "$remote_root" == "0" ]] || die "a remote root account exists - remove it manually after review"

root_plugin="$(mysql --protocol=socket -uroot -N -B -e "SELECT plugin FROM mysql.user WHERE User='root' AND Host='localhost'")"
[[ "$root_plugin" == "auth_socket" ]] || warn "root@localhost uses $root_plugin, expected auth_socket (see vps-runbook.md §4.3)"

info "mysql OK ($version)"
