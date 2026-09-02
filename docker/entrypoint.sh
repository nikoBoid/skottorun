#!/bin/sh
set -eu

database_path="${DB_DATABASE:-database/database.sqlite}"
database_dir="$(dirname "$database_path")"

mkdir -p \
    "$database_dir" \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data "$database_dir" storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    touch "$database_path"
    chown www-data:www-data "$database_path"
    su-exec www-data php artisan migrate --force --no-interaction
fi

if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

exec su-exec www-data "$@"
