#!/bin/sh

set -eu

mkdir -p \
    /var/www/html/bootstrap/cache \
    /var/www/html/database \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

touch /var/www/html/database/database.sqlite
chown -R www-data:www-data \
    /var/www/html/bootstrap/cache \
    /var/www/html/database \
    /var/www/html/storage

exec docker-php-entrypoint "$@"
