FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-req=ext-calendar

FROM php:8.3-fpm-alpine

RUN docker-php-ext-install calendar

WORKDIR /var/www/html

COPY . .

COPY --from=dependencies /app/vendor ./vendor
