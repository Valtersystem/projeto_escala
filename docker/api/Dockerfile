FROM composer:2.8 AS vendor

WORKDIR /var/www/html

COPY api/composer.json api/composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-install \
        bcmath \
        mbstring \
        pdo_sqlite

WORKDIR /var/www/html

COPY api/ ./
COPY --from=vendor /var/www/html/vendor ./vendor
COPY docker/api/entrypoint.sh /usr/local/bin/schedule-entrypoint

RUN php artisan package:discover --ansi --no-interaction \
    && mkdir -p bootstrap/cache database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && touch database/database.sqlite \
    && chown -R www-data:www-data bootstrap/cache database storage \
    && chmod +x /usr/local/bin/schedule-entrypoint

ENTRYPOINT ["schedule-entrypoint"]

CMD ["php-fpm"]
