FROM composer:2 AS vendor

WORKDIR /app

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "php artisan config:clear && php artisan route:clear && php artisan serve --host=0.0.0.0 --port=8000"]
