#!/bin/sh
set -e
php artisan optimize:clear
php artisan config:cache
php artisan migrate --force
# Solo siembra una base vacía (el seeder se salta si ya hay productos)
php artisan db:seed --force || true
php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
