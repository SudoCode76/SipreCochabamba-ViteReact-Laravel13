FROM php:8.4-fpm-alpine

ENV TZ=America/La_Paz \
    PHP_TZ=America/La_Paz \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache \
    bash curl git unzip zip \
    ghostscript \
    postgresql-dev libpq \
    freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev \
    oniguruma-dev libzip-dev libxml2-dev icu-dev tzdata

RUN docker-php-ext-configure gd \
    --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
    pdo pdo_pgsql pgsql gd mbstring bcmath pcntl zip exif intl soap

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD sh -c "\
  mkdir -p bootstrap/cache \
           storage/framework/cache \
           storage/framework/sessions \
           storage/framework/views && \
  chown -R www-data:www-data storage bootstrap/cache && \
  php-fpm \
"
# Laravel storage y cache
RUN mkdir -p /var/www/storage \
    /var/www/storage/app \
    /var/www/storage/app/public \
    /var/www/storage/framework \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
 && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
 && chmod -R 775 /var/www/storage /var/www/bootstrap/cache
