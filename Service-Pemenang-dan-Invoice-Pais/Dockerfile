FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-scripts --ignore-platform-reqs

COPY . .

# Ensure storage exists and change permissions
RUN mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data database

EXPOSE 80
