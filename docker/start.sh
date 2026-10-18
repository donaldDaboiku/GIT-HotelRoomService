#!/bin/sh
set -eu

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
    export FRONTEND_URL="$RENDER_EXTERNAL_URL"
    export CORS_ALLOWED_ORIGINS="$RENDER_EXTERNAL_URL"
fi

if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
    export DB_URL="$DATABASE_URL"
fi
export DB_CONNECTION=pgsql
if [ -z "${DB_URL:-}" ]; then
    echo "PostgreSQL is required. Connect a Render Postgres database to this service so DATABASE_URL is set."
    exit 1
fi
case "${APP_KEY:-}" in
    ""|base64:)
        echo "APP_KEY is required. Generate one with: php artisan key:generate --show"
        exit 1
        ;;
esac

sed "s/__PORT__/${PORT:-10000}/" /etc/nginx/conf.d/app.conf > /tmp/app.conf
mv /tmp/app.conf /etc/nginx/conf.d/app.conf

cd /var/www/api
php artisan package:discover --ansi
php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan view:cache

php-fpm --daemonize
exec nginx -g 'daemon off;'
