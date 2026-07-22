#!/bin/sh
set -eu

cd /var/www/html

if [ -n "${FIREBASE_CREDENTIALS:-}" ] && [ -f "${FIREBASE_CREDENTIALS}" ]; then
    chown root:www-data "${FIREBASE_CREDENTIALS}"
    chmod 0440 "${FIREBASE_CREDENTIALS}"
fi

# Fly injects secrets at runtime, so Laravel caches must be built after the
# Machine receives its environment rather than during docker build.
gosu www-data php artisan optimize

if [ "${1:-}" = "apache2-foreground" ]; then
    exec "$@"
fi

exec gosu www-data "$@"
