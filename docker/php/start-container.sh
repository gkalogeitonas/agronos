#!/bin/bash
set -e

cd /var/www

chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force
php artisan optimize

exec php-fpm
