FROM composer:2 AS composer-base

WORKDIR /app

COPY composer.json composer.lock ./

FROM composer-base AS vendor-dev

RUN composer install \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-req=ext-calendar

FROM composer-base AS vendor-prod

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-req=ext-calendar

FROM php:8.3-fpm-alpine AS php-base

RUN docker-php-ext-install calendar

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY public ./public
COPY src ./src
COPY templates ./templates

FROM php-base AS test

COPY --from=vendor-dev /app/vendor ./vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY phpstan.neon phpunit.xml ./
COPY tests ./tests

FROM php-base AS runtime

COPY --from=vendor-prod /app/vendor ./vendor

FROM mcr.microsoft.com/playwright:v1.61.1-noble AS browser-test

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY playwright.config.js ./
COPY tests/e2e ./tests/e2e
