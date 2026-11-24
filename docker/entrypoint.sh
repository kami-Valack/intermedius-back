#!/bin/sh
set -eu

if [ ! -f .env ]; then
    echo "Missing .env. Copy .env.example to .env before starting Compose." >&2
    exit 1
fi

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

if [ "${APP_ENV:-local}" = "local" ]; then
    php artisan db:seed --force
fi

exec "$@"
