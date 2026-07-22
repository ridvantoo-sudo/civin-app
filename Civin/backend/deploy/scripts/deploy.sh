#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/civin/backend}"
BRANCH="${BRANCH:-main}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"

cd "${APP_DIR}"

if [[ ! -f artisan ]] || ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "${APP_DIR} must be the Laravel backend inside a Git working tree." >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    echo "Missing ${APP_DIR}/.env; configure it before deploying." >&2
    exit 1
fi

php artisan down --retry=60
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

git fetch origin "${BRANCH}"
git checkout "${BRANCH}"
git pull --ff-only origin "${BRANCH}"

composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# This repository currently has no package-lock.json. Commit one and replace
# npm install with npm ci for deterministic production builds.
npm install --no-audit --no-fund --package-lock=false
npm run build

php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan queue:restart

chgrp -R www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

sudo systemctl reload "${PHP_FPM_SERVICE}"
sudo systemctl reload nginx

php artisan up
trap - EXIT

curl --fail --silent --show-error "${HEALTH_URL:-https://api.example.com/up}" >/dev/null
echo "Deployment completed; health check passed."
