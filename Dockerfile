FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql bcmath

# Composer hinzufügen
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/craft

RUN chown -R www-data:www-data /var/www/craft
