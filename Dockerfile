FROM node:22-bookworm AS web
WORKDIR /src
COPY web/package.json web/package-lock.json ./
RUN npm ci
COPY web/ ./
ENV VITE_API_URL=
RUN npm run build

FROM php:8.4-fpm-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx libpq-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/api
COPY api/ ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=web /src/dist /var/www/web
COPY docker/nginx.conf /etc/nginx/conf.d/app.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-render.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh \
    && rm -f /etc/nginx/sites-enabled/default

EXPOSE 10000
CMD ["/start.sh"]
