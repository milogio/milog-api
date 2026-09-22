FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libxml2-dev libzip-dev \
    && docker-php-ext-install bcmath mbstring pdo_pgsql xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

COPY . ./
COPY --from=vendor /app/vendor ./vendor
COPY docker/php-fpm/production-entrypoint.sh /usr/local/bin/milog-production-entrypoint

RUN chmod +x /usr/local/bin/milog-production-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["milog-production-entrypoint"]
CMD ["php-fpm"]
