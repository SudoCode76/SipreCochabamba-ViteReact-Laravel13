#!/bin/sh
set -eu

cd /var/www/html

printf "upload_max_filesize=10M\npost_max_size=35M\n" > /usr/local/etc/php/conf.d/uploads.ini

mkdir -p \
  bootstrap/cache \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs

rm -f \
  bootstrap/cache/*.php \
  bootstrap/cache/*.json

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -d vendor/laravel/sanctum ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f vendor/bin/phpunit ]; then
  composer install --no-interaction --prefer-dist
fi

php artisan migrate --force --path=database/migrations/2026_04_24_173439_create_personal_access_tokens_table.php

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan storage:link

exec php artisan serve --host=0.0.0.0 --port=8000
