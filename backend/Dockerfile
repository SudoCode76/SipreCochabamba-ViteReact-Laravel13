FROM composer:2 AS vendor

WORKDIR /app

COPY . .

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY docker/start-backend.sh /usr/local/bin/start-backend

RUN chmod +x /usr/local/bin/start-backend \
    && mkdir -p /var/www/html/storage/framework/views /var/www/html/storage/framework/cache/data /var/www/html/storage/framework/sessions /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["sh", "/usr/local/bin/start-backend"]
