#!/usr/bin/env bash
# Step 40: MySQL 8 (Ubuntu 24.04 package), loopback only. Idempotent.
# root keeps auth_socket (only the OS root can log in as MySQL root, no password).
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars MYSQL_INNODB_BUFFER_POOL
[[ "$MYSQL_INNODB_BUFFER_POOL" =~ ^[0-9]+[MG]$ ]] || die "MYSQL_INNODB_BUFFER_POOL must look like 2G or 512M"

apt_install mysql-server

OPS_CHANGED=0
install_template mysql-sroor.cnf.tmpl /etc/mysql/mysql.conf.d/99-sroor.cnf 0644 root:root
systemctl enable mysql
if [[ "$OPS_CHANGED" -eq 1 ]]; then
    systemctl restart mysql
else
    systemctl start mysql
fi

version="$(mysql --protocol=socket -uroot -N -B -e 'SELECT VERSION()')"
[[ "$version" == 8.* ]] || die "MySQL 8 expected, found $version"

info "removing anonymous accounts (fresh-install hardening; no data is touched)"
mysql --protocol=socket -uroot -N -B <<'SQL'
DROP USER IF EXISTS ''@'localhost';
DROP USER IF EXISTS ''@'%';
SQL

remote_root="$(mysql --protocol=socket -uroot -N -B -e "SELECT COUNT(*) FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1')")"
[[ "$remote_root" == "0" ]] || die "a remote root account exists - remove it manually after review"

info "mysql OK ($version)"
