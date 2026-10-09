#!/usr/bin/env bash
# Step 40: MySQL 8.4 LTS from the official MySQL APT repository
# (repo.mysql.com), loopback only. Idempotent.
#
# Ubuntu 24.04 ships MySQL 8.0; the CTO decision (W1 Q6) is 8.4 LTS, the same
# series the CI `mysql` job tests against. The repository signing key is
# downloaded over HTTPS and its fingerprint is checked BEFORE apt trusts it.
# root keeps auth_socket (only the OS root can log in as MySQL root, no password).
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/lib/common.sh"

require_root
apply_defaults
require_vars MYSQL_INNODB_BUFFER_POOL
[[ "$MYSQL_INNODB_BUFFER_POOL" =~ ^[0-9]+[MG]$ ]] || die "MYSQL_INNODB_BUFFER_POOL must look like 2G or 512M"

# "MySQL Release Engineering <mysql-build@oss.oracle.com>" key (2023).
# Verify against https://dev.mysql.com/doc/refman/8.4/en/checking-gpg-signature.html
# before the first run; override only after that check.
: "${MYSQL_APT_KEY_FINGERPRINT:=BCA43417C3B485DD128EC6D4B7B3B788A8D3785C}"
: "${MYSQL_APT_KEY_URL:=https://repo.mysql.com/RPM-GPG-KEY-mysql-2023}"
MYSQL_APT_COMPONENT="mysql-8.4-lts"
KEYRING=/usr/share/keyrings/mysql-apt.gpg
SOURCES=/etc/apt/sources.list.d/mysql-sroor.list
PIN=/etc/apt/preferences.d/mysql-sroor

# Refuse to silently upgrade an existing non-8.4 server (fresh VPS only).
if command -v mysqld >/dev/null 2>&1; then
    existing="$(mysqld --version | grep -oE 'Ver [0-9]+\.[0-9]+' | awk '{print $2}')"
    [[ "$existing" == "8.4" ]] || die "mysqld $existing is already installed; this step only installs 8.4 LTS on a fresh server (upgrade manually after a backup)"
fi

if [[ ! -s "$KEYRING" ]]; then
    info "importing the MySQL APT signing key (fingerprint checked)"
    tmp="$(mktemp -d)"
    curl -fsSL --proto '=https' --tlsv1.2 -o "$tmp/mysql.asc" "$MYSQL_APT_KEY_URL"
    fpr="$(gpg --batch --show-keys --with-colons "$tmp/mysql.asc" | awk -F: '/^fpr:/{print $10; exit}')"
    if [[ "$fpr" != "$MYSQL_APT_KEY_FINGERPRINT" ]]; then
        rm -rf "$tmp"
        die "MySQL APT key fingerprint mismatch (got ${fpr:-none}); not trusting it"
    fi
    gpg --batch --dearmor <"$tmp/mysql.asc" >"$tmp/mysql.gpg"
    install -m 0644 -o root -g root "$tmp/mysql.gpg" "$KEYRING"
    rm -rf "$tmp"
fi

# shellcheck source=/dev/null
. /etc/os-release
codename="${VERSION_CODENAME:-noble}"
tmp_src="$(mktemp)"
printf 'deb [signed-by=%s] https://repo.mysql.com/apt/ubuntu/ %s %s\n' "$KEYRING" "$codename" "$MYSQL_APT_COMPONENT" >"$tmp_src"
install_file "$tmp_src" "$SOURCES" 0644 root:root
rm -f "$tmp_src"

# Prefer the MySQL repository over Ubuntu's 8.0 packages for the server/client.
tmp_pin="$(mktemp)"
cat >"$tmp_pin" <<'EOF'
Package: mysql-* libmysqlclient*
Pin: origin repo.mysql.com
Pin-Priority: 1001
EOF
install_file "$tmp_pin" "$PIN" 0644 root:root
rm -f "$tmp_pin"
apt-get update -q

# Empty root password in the installer = root authenticates with auth_socket.
# Nothing secret is preseeded.
debconf-set-selections <<'EOF'
mysql-community-server mysql-community-server/root-pass password
mysql-community-server mysql-community-server/re-root-pass password
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
