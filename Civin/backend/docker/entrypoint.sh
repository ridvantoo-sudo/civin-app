#!/bin/sh
set -eu

cd /var/www/html

firebase_credentials_path="${FIREBASE_CREDENTIALS:-/var/www/html/storage/app/firebase-service-account.json}"

if [ -n "${FIREBASE_CREDENTIALS_BASE64:-}" ]; then
    printf '%s' "${FIREBASE_CREDENTIALS_BASE64}" | base64 --decode > "${firebase_credentials_path}"
    export FIREBASE_CREDENTIALS="${firebase_credentials_path}"
elif [ -n "${FIREBASE_CREDENTIALS_JSON:-}" ]; then
    printf '%s' "${FIREBASE_CREDENTIALS_JSON}" > "${firebase_credentials_path}"
    export FIREBASE_CREDENTIALS="${firebase_credentials_path}"
fi

if [ -n "${FIREBASE_CREDENTIALS:-}" ] && [ -f "${FIREBASE_CREDENTIALS}" ]; then
    chown root:www-data "${FIREBASE_CREDENTIALS}"
    chmod 0440 "${FIREBASE_CREDENTIALS}"
fi

php artisan optimize:clear

if [ "${BACK4APP_RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --isolated --force
fi

if [ "${BACK4APP_RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --class=CountrySeeder --force
    php artisan db:seed --class=SettingSeeder --force
    php artisan db:seed --class=AdminRoleSeeder --force
fi

# Runtime environments and secrets must be present before the cache is built.
gosu www-data php artisan optimize

if [ "${1:-}" = "apache2-foreground" ] || [ "${1:-}" = "/usr/bin/supervisord" ]; then
    exec "$@"
fi

exec gosu www-data "$@"
