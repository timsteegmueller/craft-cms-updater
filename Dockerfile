FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip \
    git \
    zip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    mariadb-client \
    && docker-php-ext-install pdo pdo_mysql zip mbstring exif gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
