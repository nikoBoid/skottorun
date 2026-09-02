FROM php:8.4-fpm-alpine AS php-base

RUN apk add --no-cache icu-dev libzip-dev sqlite-dev su-exec \
    && docker-php-ext-install intl opcache pcntl pdo_sqlite zip

WORKDIR /var/www/html

FROM php-base AS builder

ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN apk add --no-cache git nodejs npm unzip \
    && npm install --global pnpm@11.19.0

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && pnpm install --frozen-lockfile \
    && BROADCAST_CONNECTION=null pnpm run build \
    && CACHE_STORE=array php artisan optimize:clear

FROM php-base AS runtime

COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/scottorun-entrypoint

RUN chmod +x /usr/local/bin/scottorun-entrypoint \
    && mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data database storage bootstrap/cache

ENTRYPOINT ["scottorun-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.29-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=builder /var/www/html/public /var/www/html/public
