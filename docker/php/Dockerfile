# LOCAL DEVELOPMENT ONLY - never used to build or run production.
# Production runs native PHP 8.5 from the Ubuntu 26.04 archive (scripts/ops/steps/30-php.sh);
# this image mirrors that runtime (PHP 8.5, same extensions, MySQL 8.4 client) for dev.
#
# Build args SROOR_UID / SROOR_GID = the host user (docker/bin/dev.sh passes `id -u` / `id -g`),
# so everything the containers write into the bind-mounted repo (storage/, vendor/,
# node_modules/, public/hot) stays owned by the developer, never by root.

ARG PHP_IMAGE=php:8.5.12-fpm-trixie
ARG NODE_IMAGE=node:24.22.2-trixie-slim
ARG COMPOSER_IMAGE=composer:2.11.2
ARG EXT_INSTALLER_IMAGE=mlocati/php-extension-installer:2.13.2

FROM ${NODE_IMAGE} AS node
FROM ${COMPOSER_IMAGE} AS composer
FROM ${EXT_INSTALLER_IMAGE} AS ext-installer

FROM ${PHP_IMAGE}

ARG SROOR_UID=1000
ARG SROOR_GID=1000
# Oracle MySQL APT signing key (RPM-GPG-KEY-mysql-2025, same key as 2023 with a renewed expiry).
ARG MYSQL_APT_KEY_FPR=BCA43417C3B485DD128EC6D4B7B3B788A8D3785C

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

# Same extension set as the VPS (30-php.sh): bcmath intl pdo_mysql redis gd zip mbstring
# (+ pcntl/posix for Horizon, exif for media). mbstring and OPcache are built into the
# official PHP 8.5 image; the installer is a no-op for them.
COPY --from=ext-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl pdo_mysql redis gd zip pcntl exif \
    && php -m | grep -qix 'Zend OPcache' \
    && for ext in bcmath intl pdo_mysql redis gd zip mbstring pcntl pdo_sqlite; do php -m | grep -qix "$ext" || { echo "missing $ext" >&2; exit 1; }; done

# Oracle MySQL 8.4 client (mysql + mysqldump), the same major as the server: needed by
# spatie/laravel-backup (mysqldump), the restore drill and scripts/ops/sync-central-grants.sh.
# Debian's default-mysql-client is MariaDB and does not handle MySQL 8.4 TLS/auth defaults.
RUN apt-get update \
    && apt-get install -y --no-install-recommends gnupg ca-certificates curl unzip git \
    && curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 -o /tmp/mysql.asc \
    && gpg --show-keys --with-colons /tmp/mysql.asc | grep -q "^fpr:::::::::${MYSQL_APT_KEY_FPR}:" \
    && install -d -m 0755 /etc/apt/keyrings \
    && gpg --dearmor </tmp/mysql.asc >/etc/apt/keyrings/mysql.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/mysql.gpg] https://repo.mysql.com/apt/debian trixie mysql-8.4-lts" >/etc/apt/sources.list.d/mysql.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends mysql-community-client \
    && apt-get purge -y --auto-remove gnupg \
    && rm -rf /var/lib/apt/lists/* /tmp/mysql.asc \
    && mysql --version && mysqldump --version

# Node 24 (same major as CI) for `npm run dev` / `npm run build` (build runs `php artisan lang:export`
# first, so node and PHP must live in one image).
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version && npm --version

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY conf.d/sroor-dev.ini /usr/local/etc/php/conf.d/zz-sroor-dev.ini
COPY php-fpm.d/zz-sroor-dev.conf /usr/local/etc/php-fpm.d/zz-sroor-dev.conf

# Host-matching user. A GID/UID already present in the image (e.g. 33 www-data) is reused.
RUN set -eux; \
    if ! getent group "${SROOR_GID}" >/dev/null; then groupadd -g "${SROOR_GID}" sroor; fi; \
    if ! getent passwd "${SROOR_UID}" >/dev/null; then \
        useradd -M -d /home/sroor -u "${SROOR_UID}" -g "${SROOR_GID}" -s /bin/bash sroor; \
    fi; \
    mkdir -p /home/sroor/.composer/cache /home/sroor/.npm; \
    chown -R "${SROOR_UID}:${SROOR_GID}" /home/sroor

ENV HOME=/home/sroor \
    COMPOSER_HOME=/home/sroor/.composer \
    COMPOSER_CACHE_DIR=/home/sroor/.composer/cache \
    npm_config_cache=/home/sroor/.npm

USER ${SROOR_UID}:${SROOR_GID}
WORKDIR /srv/sroor/backend

EXPOSE 9000 5173
CMD ["php-fpm"]
