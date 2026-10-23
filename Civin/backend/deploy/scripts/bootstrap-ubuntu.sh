#!/usr/bin/env bash
set -Eeuo pipefail

PHP_VERSION="${PHP_VERSION:-8.3}"
NODE_MAJOR="${NODE_MAJOR:-22}"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run this script as root: sudo bash $0" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y \
    ca-certificates curl gnupg lsb-release software-properties-common \
    nginx mysql-server redis-server supervisor certbot python3-certbot-nginx \
    git unzip

# Ubuntu 24.04 ships PHP 8.3. The PPA also supports Ubuntu releases whose
# default repositories do not contain the requested PHP version.
if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update
fi

apt-get install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-opcache" \
    "php${PHP_VERSION}-redis" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-zip"

# Vite 7 requires a recent Node release. Build in CI instead if Node should
# not be installed on the application server.
install -d -m 0755 /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
    | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" \
    > /etc/apt/sources.list.d/nodesource.list
apt-get update
apt-get install -y nodejs

# Install Composer only after verifying the installer signature.
EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"

if [[ "${EXPECTED_CHECKSUM}" != "${ACTUAL_CHECKSUM}" ]]; then
    rm -f /tmp/composer-setup.php
    echo "Composer installer checksum verification failed." >&2
    exit 1
fi

php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php

systemctl enable --now \
    nginx mysql redis-server supervisor "php${PHP_VERSION}-fpm"

echo "Installed:"
php -v | sed -n '1p'
composer --version
node --version
nginx -v
echo "Next: secure MySQL, create the database/user, clone Civin, and configure .env."
