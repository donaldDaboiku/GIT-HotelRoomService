#!/bin/sh
set -eu

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
    export FRONTEND_URL="$RENDER_EXTERNAL_URL"
    export CORS_ALLOWED_ORIGINS="$RENDER_EXTERNAL_URL"
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
