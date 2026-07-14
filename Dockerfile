FROM php:8.3-fpm-alpine

RUN docker-php-ext-install calendar

WORKDIR /var/www/html

COPY . .
