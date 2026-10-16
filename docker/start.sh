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
if [ -n "${DB_URL:-}" ] && [ -z "${DB_CONNECTION:-}" ]; then
    export DB_CONNECTION=pgsql
fi
if [ "${APP_ENV:-production}" = "production" ] && [ "${DB_CONNECTION:-sqlite}" != "pgsql" ]; then
    echo "Refusing to migrate SQLite in production. In Render, add a PostgreSQL database and connect it to this service so DATABASE_URL is set."
    exit 1
fi

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
