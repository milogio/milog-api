FROM php:8.1-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        mbstring \
        pdo_mysql \
        xml \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
